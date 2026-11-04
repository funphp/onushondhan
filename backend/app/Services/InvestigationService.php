<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileVault;
use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Exceptions\ApiException;
use App\Jobs\NotifyFollowersOfPublication;
use App\Models\Investigation;
use App\Models\InvestigationTag;
use App\Models\InvestigationVersion;
use App\Models\Investigator;
use App\Models\PublishConfirmation;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Files\FileInspector;
use App\Services\Files\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Investigation lifecycle: Draft -> Preview -> Responsibility confirmation -> Published
 * -> Updated / Withdrawn / Archived. Admin restrict/remove lives in ModerationService.
 */
class InvestigationService
{
    public const CONFIRMATION_STATEMENTS = [
        'responsible_for_claims' => 'I am solely responsible for this report\'s claims, statements and supporting material.',
        'has_rights_to_material' => 'I have the rights and permissions needed for all submitted material, including evidence and sources.',
        'subject_to_policies_and_law' => 'Publication is subject to onusondhan.com\'s policies and applicable law.',
        'no_platform_verification' => 'Publication by onusondhan.com does not mean onusondhan.com has independently verified or endorsed this report.',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AnalyticsService $analytics,
        private readonly FileVault $vault,
        private readonly FileInspector $inspector,
        private readonly ImageSanitizer $sanitizer,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(Investigator $investigator, array $data, User $actor): Investigation
    {
        return DB::transaction(function () use ($investigator, $data, $actor): Investigation {
            $investigation = new Investigation;
            $investigation->investigator_id = $investigator->id;
            $investigation->title = (string) ($data['title'] ?? __('Untitled investigation'));
            $this->applyWorkingCopy($investigation, $data);
            $investigation->save();
            $this->syncChildren($investigation, $data);

            $this->audit->log('investigation.created', $investigation, null, ['public_id' => $investigation->public_id, 'title' => $investigation->title], $actor);

            return $investigation->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function saveWorkingCopy(Investigation $investigation, array $data, User $actor): Investigation
    {
        if (! $investigation->isEditableByOwner()) {
            throw ApiException::invalidState('This investigation can no longer be edited.', 'not_editable');
        }

        return DB::transaction(function () use ($investigation, $data, $actor): Investigation {
            /** @var Investigation $locked */
            $locked = Investigation::query()->lockForUpdate()->findOrFail($investigation->id);
            if (isset($data['revision']) && (int) $data['revision'] !== $locked->draft_revision) {
                throw ApiException::conflict('This investigation was changed elsewhere. Reload to get the latest version.', 'revision_conflict');
            }

            $this->applyWorkingCopy($locked, $data);
            $this->syncChildren($locked, $data);
            $locked->draft_revision++;
            if ($locked->current_version > 0) {
                $locked->has_unpublished_changes = true;
            }
            $locked->save();

            // Autosaves are frequent; audit only the fact of a change (content lives in versions).
            if ($locked->status !== InvestigationStatus::Draft) {
                $this->audit->log('investigation.working_copy_updated', $locked, null, ['revision' => $locked->draft_revision], $actor);
            }

            return $locked;
        });
    }

    /**
     * @param  array<string, bool>  $statements
     */
    public function publish(Investigation $investigation, User $actor, array $statements, ?string $changeReason): Investigation
    {
        if (! in_array($investigation->status, [InvestigationStatus::Draft, InvestigationStatus::Published, InvestigationStatus::Withdrawn], true)) {
            throw ApiException::invalidState('This investigation cannot be published in its current state.', 'not_publishable');
        }
        $investigator = $investigation->investigator;
        if (! $investigator->isActive()) {
            throw ApiException::forbidden('Your investigator profile is not active.', 'investigator_inactive');
        }
        foreach (array_keys(self::CONFIRMATION_STATEMENTS) as $key) {
            if (($statements[$key] ?? false) !== true) {
                throw new ApiException('You must confirm every responsibility statement before publishing.', 'confirmation_required', 422, ['confirmations.'.$key => ['This confirmation is required.']]);
            }
        }
        if ($investigation->current_version > 0 && blank($changeReason)) {
            throw new ApiException('Describe what changed in this update.', 'change_reason_required', 422, ['change_reason' => ['A change summary is required for updates.']]);
        }
        $this->assertPublishable($investigation);

        return DB::transaction(function () use ($investigation, $actor, $statements, $changeReason): Investigation {
            /** @var Investigation $inv */
            $inv = Investigation::query()->lockForUpdate()->findOrFail($investigation->id);
            $inv->load(['findings', 'timeline', 'sources', 'tags', 'locations', 'category', 'subcategory', 'investigator']);
            $isFirst = $inv->published_at === null;
            $before = ['status' => $inv->status->value, 'version' => $inv->current_version];
            $versionNo = $inv->current_version + 1;
            $identity = $inv->investigator->identitySnapshot();

            InvestigationVersion::query()->create([
                'investigation_id' => $inv->id,
                'version_no' => $versionNo,
                'title' => $inv->title,
                'summary' => $inv->summary,
                'body' => $inv->body,
                'findings_json' => $inv->findings->map(fn ($f) => ['title' => $f->title, 'body' => $f->body])->values()->all(),
                'recommendations_json' => array_values($inv->recommendations ?? []),
                'timeline_json' => $inv->timeline->map(fn ($t) => [
                    'event_date' => $t->event_date?->toDateString(), 'title' => $t->title, 'description' => $t->description,
                ])->values()->all(),
                // Only sources the investigator marked public are ever snapshotted for display.
                'sources_json' => $inv->sources->where('is_public', true)->map(fn ($s) => [
                    'title' => $s->title, 'source_type' => $s->source_type, 'publisher' => $s->publisher, 'url' => $s->url,
                    'citation_text' => $s->citation_text, 'accessed_at' => $s->accessed_at?->toDateString(),
                ])->values()->all(),
                'entities_json' => array_values($inv->entities ?? []),
                'meta_json' => [
                    'category' => $inv->category?->slug,
                    'subcategory' => $inv->subcategory?->slug,
                    'tags' => $inv->tags->pluck('slug')->all(),
                    'location_name' => $inv->location_name,
                    'location_country' => $inv->location_country,
                    'period_start' => $inv->period_start?->toDateString(),
                    'period_end' => $inv->period_end?->toDateString(),
                ],
                'identity_snapshot' => $identity,
                'changed_by' => $actor->id,
                'change_reason' => $isFirst ? ($changeReason ?: __('Initial publication')) : $changeReason,
                'created_at' => now(),
            ]);

            $terms = TermsVersion::currentFor('investigator_responsibility');
            PublishConfirmation::query()->create([
                'investigation_id' => $inv->id,
                'user_id' => $actor->id,
                'version_no' => $versionNo,
                'terms_version_id' => $terms?->id,
                'statements_json' => array_intersect_key($statements, self::CONFIRMATION_STATEMENTS),
                'confirmed_at' => now(),
                'ip_address' => Request::ip(),
                'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            ]);

            $previousCover = $inv->published_cover_path;
            $inv->forceFill([
                'current_version' => $versionNo,
                'status' => InvestigationStatus::Published,
                'slug' => Investigation::makeSlug($inv->title),
                'published_title' => $inv->title,
                'published_summary' => $inv->summary,
                'published_body' => $inv->body,
                'published_cover_path' => $inv->cover_path,
                'identity_snapshot' => $identity,
                'published_at' => $inv->published_at ?? now(),
                'last_published_at' => now(),
                'withdrawn_at' => null,
                'withdrawal_reason' => null,
                'public_status_note' => null,
                'has_unpublished_changes' => false,
            ])->save();

            if ($previousCover !== null && $previousCover !== $inv->cover_path) {
                DB::afterCommit(fn () => $this->deleteCoverFile($previousCover));
            }

            $this->recountInvestigator($inv->investigator);
            $this->audit->log($isFirst ? 'investigation.published' : 'investigation.updated', $inv, $before, [
                'status' => 'published', 'version' => $versionNo, 'change_reason' => $changeReason,
            ], $actor);
            $this->analytics->record('investigation_published', ['investigation_id' => $inv->id, 'investigator_id' => $inv->investigator_id, 'user_id' => $actor->id], ['version' => $versionNo]);

            if ($isFirst && $inv->visibility === InvestigationVisibility::Public) {
                NotifyFollowersOfPublication::dispatch($inv->id)->afterCommit();
            }

            return $inv;
        });
    }

    public function withdraw(Investigation $investigation, User $actor, string $reason): Investigation
    {
        if (! in_array($investigation->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true)) {
            throw ApiException::invalidState('Only published or archived investigations can be withdrawn.');
        }

        return $this->transition($investigation, $actor, InvestigationStatus::Withdrawn, 'investigation.withdrawn', [
            'withdrawn_at' => now(),
            'withdrawal_reason' => mb_substr($reason, 0, 1000),
        ], $reason);
    }

    public function archive(Investigation $investigation, User $actor): Investigation
    {
        if ($investigation->status !== InvestigationStatus::Published) {
            throw ApiException::invalidState('Only published investigations can be archived.');
        }

        return $this->transition($investigation, $actor, InvestigationStatus::Archived, 'investigation.archived', [
            'archived_at' => now(),
            'comments_locked' => true,
        ]);
    }

    public function deleteDraft(Investigation $investigation, User $actor): void
    {
        if ($investigation->status !== InvestigationStatus::Draft || $investigation->current_version > 0) {
            throw ApiException::invalidState('Only never-published drafts can be deleted. Withdraw published investigations instead.');
        }
        DB::transaction(function () use ($investigation, $actor): void {
            $this->audit->log('investigation.draft_deleted', $investigation, ['title' => $investigation->title], null, $actor);
            $investigation->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(Investigation $investigation, ?User $actor, InvestigationStatus $to, string $auditAction, array $attributes = [], ?string $reason = null): Investigation
    {
        return DB::transaction(function () use ($investigation, $actor, $to, $auditAction, $attributes, $reason): Investigation {
            /** @var Investigation $inv */
            $inv = Investigation::query()->lockForUpdate()->findOrFail($investigation->id);
            $before = ['status' => $inv->status->value];
            $inv->forceFill(['status' => $to] + $attributes)->save();
            $this->recountInvestigator($inv->investigator);
            $this->audit->log($auditAction, $inv, $before, ['status' => $to->value, 'reason' => $reason], $actor);
            if ($to === InvestigationStatus::Withdrawn) {
                $this->analytics->record('investigation_withdrawn', ['investigation_id' => $inv->id, 'investigator_id' => $inv->investigator_id, 'user_id' => $actor?->id]);
            }

            return $inv;
        });
    }

    public function recountInvestigator(Investigator $investigator): void
    {
        $count = Investigation::query()
            ->where('investigator_id', $investigator->id)
            ->whereIn('status', [InvestigationStatus::Published, InvestigationStatus::Archived])
            ->where('visibility', InvestigationVisibility::Public)
            ->count();
        Investigator::query()->whereKey($investigator->id)->update(['published_investigations_count' => $count]);
    }

    private function assertPublishable(Investigation $investigation): void
    {
        $errors = [];
        if (mb_strlen(trim($investigation->title)) < 5 || in_array($investigation->title, ['Untitled investigation', __('Untitled investigation')], true)) {
            $errors['title'] = ['Add a descriptive title (at least 5 characters).'];
        }
        // The summary is generated from the body, so a too-short summary means too little readable text.
        if (mb_strlen(trim((string) $investigation->body)) < 100 || mb_strlen(trim((string) $investigation->summary)) < 20) {
            $errors['body'] = ['The report body must be at least 100 characters.'];
        }
        if ($investigation->category_id === null) {
            $errors['category'] = ['Choose a category.'];
        }
        if ($errors !== []) {
            throw new ApiException('This investigation is not ready to publish.', 'not_ready', 422, $errors);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyWorkingCopy(Investigation $investigation, array $data): void
    {
        $fields = Arr::only($data, [
            'title', 'body', 'category_id', 'subcategory_id', 'visibility', 'location_name',
            'location_country', 'location_lat', 'location_lng', 'period_start', 'period_end',
        ]);
        foreach (['title', 'body', 'location_name'] as $text) {
            if (array_key_exists($text, $fields) && is_string($fields[$text])) {
                $fields[$text] = self::cleanText($fields[$text]);
            }
        }
        if (array_key_exists('location_country', $fields) && is_string($fields['location_country'])) {
            $fields['location_country'] = strtoupper($fields['location_country']);
        }
        $investigation->fill($fields);
        if (array_key_exists('body', $fields)) {
            $investigation->summary = self::summarize((string) $fields['body']);
        }

        if (array_key_exists('recommendations', $data)) {
            $investigation->recommendations = array_values(array_filter(array_map(
                fn ($r) => self::cleanText((string) $r), (array) $data['recommendations']
            ), fn ($r) => $r !== ''));
        }
        if (array_key_exists('entities', $data)) {
            $investigation->entities = array_values(array_map(fn ($e) => [
                'name' => self::cleanText((string) ($e['name'] ?? '')),
                'type' => (string) ($e['type'] ?? 'other'),
                'role' => isset($e['role']) ? self::cleanText((string) $e['role']) : null,
            ], (array) $data['entities']));
        }
    }

    /** @param array<string, mixed> $data */
    private function syncChildren(Investigation $investigation, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $ids = collect((array) $data['tags'])
                ->map(fn ($t) => trim((string) $t))->filter()->unique()->take(15)
                ->map(fn ($name) => InvestigationTag::findOrCreateByName($name)->id)->all();
            $investigation->tags()->sync($ids);
        }
        if (array_key_exists('findings', $data)) {
            $investigation->findings()->delete();
            foreach (array_values((array) $data['findings']) as $i => $f) {
                $investigation->findings()->create([
                    'title' => self::cleanText((string) $f['title']), 'body' => isset($f['body']) ? self::cleanText((string) $f['body']) : null, 'sort_order' => $i,
                ]);
            }
        }
        if (array_key_exists('timeline', $data)) {
            $investigation->timeline()->delete();
            foreach (array_values((array) $data['timeline']) as $i => $t) {
                $investigation->timeline()->create([
                    'event_date' => $t['event_date'] ?? null, 'title' => self::cleanText((string) $t['title']),
                    'description' => isset($t['description']) ? self::cleanText((string) $t['description']) : null, 'sort_order' => $i,
                ]);
            }
        }
        if (array_key_exists('sources', $data)) {
            $investigation->sources()->delete();
            foreach (array_values((array) $data['sources']) as $i => $s) {
                $investigation->sources()->create([
                    'title' => self::cleanText((string) $s['title']),
                    'source_type' => $s['source_type'] ?? 'other',
                    'publisher' => isset($s['publisher']) ? self::cleanText((string) $s['publisher']) : null,
                    'url' => $s['url'] ?? null,
                    'citation_text' => isset($s['citation_text']) ? self::cleanText((string) $s['citation_text']) : null,
                    'accessed_at' => $s['accessed_at'] ?? null,
                    'is_public' => (bool) ($s['is_public'] ?? true),
                    'sort_order' => $i,
                ]);
            }
        }
        if (array_key_exists('locations', $data)) {
            $investigation->locations()->delete();
            foreach (array_values((array) $data['locations']) as $i => $l) {
                $investigation->locations()->create([
                    'country' => isset($l['country']) ? strtoupper((string) $l['country']) : null,
                    'region' => $l['region'] ?? null, 'district' => $l['district'] ?? null,
                    'city' => $l['city'] ?? null, 'area' => $l['area'] ?? null,
                    'latitude' => $l['latitude'] ?? null, 'longitude' => $l['longitude'] ?? null,
                    'is_primary' => (bool) ($l['is_primary'] ?? $i === 0),
                ]);
            }
        }
    }

    /**
     * Set the working copy's header image. The upload is type/size checked and re-encoded
     * (EXIF/GPS dropped) before it is stored in the private vault; it goes live on publish.
     */
    public function updateCover(Investigation $investigation, UploadedFile $file, User $actor): Investigation
    {
        if (! $investigation->isEditableByOwner()) {
            throw ApiException::invalidState('This investigation can no longer be edited.', 'not_editable');
        }
        $inspected = $this->inspector->inspect($file, [
            'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'],
        ], 8 * 1024 * 1024, 'cover');
        $clean = $this->sanitizer->sanitize($inspected->path, $inspected->mime);
        if ($clean === null) {
            throw new ApiException('The image could not be processed.', 'image_unprocessable', 422, ['cover' => ['The image could not be processed.']]);
        }
        if ($clean['width'] < 600 || $clean['height'] < 250) {
            @unlink($clean['path']);
            throw new ApiException('The header image is too small.', 'image_too_small', 422, ['cover' => ['Use an image at least 600 × 250 pixels.']]);
        }
        try {
            $key = $this->vault->putFile($clean['path'], 'covers');
        } finally {
            @unlink($clean['path']);
        }

        return $this->replaceCover($investigation, $key, $actor);
    }

    public function removeCover(Investigation $investigation, User $actor): Investigation
    {
        if (! $investigation->isEditableByOwner()) {
            throw ApiException::invalidState('This investigation can no longer be edited.', 'not_editable');
        }

        return $this->replaceCover($investigation, null, $actor);
    }

    private function replaceCover(Investigation $investigation, ?string $key, User $actor): Investigation
    {
        return DB::transaction(function () use ($investigation, $key, $actor): Investigation {
            /** @var Investigation $locked */
            $locked = Investigation::query()->lockForUpdate()->findOrFail($investigation->id);
            $old = $locked->cover_path;
            // The revision is not bumped: the cover is saved on its own, so it must not conflict with text autosaves.
            $locked->forceFill([
                'cover_path' => $key,
                'has_unpublished_changes' => $locked->current_version > 0 ? true : $locked->has_unpublished_changes,
            ])->save();
            // The published copy keeps its own file until the next publish.
            if ($old !== null && $old !== $locked->published_cover_path) {
                DB::afterCommit(fn () => $this->deleteCoverFile($old));
            }
            $this->audit->log($key === null ? 'investigation.cover_removed' : 'investigation.cover_updated', $locked, null, null, $actor);

            return $locked;
        });
    }

    private function deleteCoverFile(string $key): void
    {
        if ($this->vault->exists($key)) {
            $this->vault->delete($key);
        }
    }

    /**
     * Plain-text summary generated from the Markdown body: the opening of the report, cut at a
     * sentence end (., !, ?, ।) when one falls late enough, otherwise at a word boundary.
     */
    public static function summarize(string $markdown, int $limit = 300): ?string
    {
        $text = preg_replace([
            '/```.*?```/su',                       // fenced code
            '/^\s{0,3}#{1,6}\s.*$/mu',             // headings (section titles read badly in a summary)
            '/!\[[^\]]*\]\([^)]*\)/u',             // images
            '/\[([^\]]*)\]\([^)]*\)/u',            // links → their text
            '/^\s{0,3}(#{1,6}|>+|[-*+]|\d+[.)])\s+/mu', // quotes, list markers
            '/^\s*([-*_]\s*){3,}$/mu',             // horizontal rules
            '/^\s*\|?(\s*:?-+:?\s*\|)+\s*:?-*:?\s*$/mu', // table separators
            '/[*_~`|]+/u',                         // emphasis, code and table pipes
            '/\\\\(?=\S)/u',                       // escapes
        ], ['', '', '', '$1', '', '', '', '', ''], $markdown) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        if (preg_match('/^(.*[.!?।])(?:\s|$)/su', $cut, $m) && mb_strlen($m[1]) >= (int) ($limit * 0.4)) {
            return $m[1];
        }
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space !== false && $space > $limit * 0.6 ? $space : $limit), ' ,;:—-').'…';
    }

    /** Strip NUL/control characters (except newlines/tabs); content is Markdown rendered without raw HTML. */
    public static function cleanText(string $value): string
    {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
    }
}

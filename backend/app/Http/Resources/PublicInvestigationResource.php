<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EvidenceVisibility;
use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\InvestigationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public rendering of an investigation, built exclusively from its current published
 * version snapshot plus live taxonomy and public-safe attribution. Pass
 * $previewVersion to render an unsaved working copy for the owner's preview.
 */
class PublicInvestigationResource extends JsonResource
{
    public const DISCLAIMER = 'This report was published by an independent investigator. Onushondhan is a publishing platform: it has not independently verified the claims in this report and does not endorse them. The investigator is responsible for the report, its evidence and its sources.';

    public ?InvestigationVersion $previewVersion = null;

    /** @var iterable<Evidence>|null */
    public ?iterable $previewEvidence = null;

    /** @var array<string, mixed>|null */
    public ?array $viewer = null;

    public function toArray(Request $request): array
    {
        /** @var Investigation $inv */
        $inv = $this->resource;
        $isPreview = $this->previewVersion !== null;
        $version = $this->previewVersion ?? $inv->currentVersion;
        $notice = $isPreview ? null : $this->notice($inv);
        $hideContent = ! $isPreview && in_array($inv->status, [InvestigationStatus::Withdrawn, InvestigationStatus::Restricted, InvestigationStatus::Removed], true);
        $showContent = $version !== null && ! $hideContent;

        $indexable = ! $isPreview
            && in_array($inv->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true)
            && $inv->visibility === InvestigationVisibility::Public;

        $meta = $version?->meta_json ?? [];

        return [
            'id' => $inv->public_id,
            'slug' => $inv->slug ?? Investigation::makeSlug($version?->title ?? $inv->title),
            'url' => '/investigation/'.$inv->public_id.'/'.($inv->slug ?? ''),
            'canonical_url' => $inv->publicUrl(),
            'status' => $inv->status->value,
            'visibility' => $inv->visibility->value,
            'is_preview' => $isPreview,
            'robots' => ['index' => $indexable, 'follow' => $indexable],
            'title' => $version?->title ?? $inv->published_title ?? $inv->title,
            'summary' => $showContent ? $version->summary : null,
            'body' => $showContent ? $version->body : null,
            'findings' => $showContent ? ($version->findings_json ?? []) : [],
            'recommendations' => $showContent ? ($version->recommendations_json ?? []) : [],
            'timeline' => $showContent ? ($version->timeline_json ?? []) : [],
            'sources' => $showContent ? ($version->sources_json ?? []) : [],
            'entities' => $showContent ? ($version->entities_json ?? []) : [],
            'category' => $inv->category ? ['name' => $inv->category->name, 'slug' => $inv->category->slug] : null,
            'subcategory' => $inv->subcategory ? ['name' => $inv->subcategory->name, 'slug' => $inv->subcategory->slug] : null,
            'tags' => $inv->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug])->values(),
            'locations' => $showContent ? $inv->locations->map(fn ($l) => [
                'country' => $l->country, 'region' => $l->region, 'district' => $l->district, 'city' => $l->city, 'area' => $l->area,
            ])->values() : [],
            'location_name' => $meta['location_name'] ?? $inv->location_name,
            'location_country' => $meta['location_country'] ?? $inv->location_country,
            'period_start' => $meta['period_start'] ?? null,
            'period_end' => $meta['period_end'] ?? null,
            // Attribution always uses the investigator's *current* public identity.
            'investigator' => new InvestigatorSummaryResource($inv->investigator),
            'published_at' => $inv->published_at?->toIso8601String(),
            'updated_at' => $inv->last_published_at?->toIso8601String(),
            'version' => $version?->version_no ?? 0,
            'change_reason' => $version?->change_reason,
            'counts' => [
                'views' => $inv->views_count,
                'shares' => $inv->shares_count,
                'comments' => $inv->comments_count,
                'bookmarks' => $inv->bookmarks_count,
                'reactions' => $inv->reactions_count,
            ],
            'comments_locked' => $inv->comments_locked || $inv->status !== InvestigationStatus::Published,
            'evidence' => $showContent ? EvidenceResource::collection($this->previewEvidence ?? self::publicEvidence($inv)) : [],
            'notice' => $notice,
            'disclaimer' => self::DISCLAIMER,
            'viewer' => $this->viewer,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Evidence> */
    public static function publicEvidence(Investigation $inv): \Illuminate\Support\Collection
    {
        return $inv->evidence()
            ->where('visibility', EvidenceVisibility::Public)
            ->where('moderation_status', 'visible')
            ->get()
            ->filter(fn (Evidence $e) => $e->isScanAcceptableForPublic())
            ->values();
    }

    /** @return array<string, string|null>|null */
    private function notice(Investigation $inv): ?array
    {
        return match ($inv->status) {
            InvestigationStatus::Withdrawn => [
                'type' => 'withdrawn',
                'message' => 'This investigation was withdrawn by its investigator.',
                'detail' => $inv->withdrawal_reason,
                'date' => $inv->withdrawn_at?->toIso8601String(),
            ],
            InvestigationStatus::Archived => [
                'type' => 'archived',
                'message' => 'This investigation has been archived by its investigator and is no longer being updated.',
                'detail' => null,
                'date' => $inv->archived_at?->toIso8601String(),
            ],
            InvestigationStatus::Restricted => [
                'type' => 'restricted',
                'message' => 'This investigation is temporarily unavailable while it is reviewed under the platform\'s content policy. This is not a finding about the accuracy of the report.',
                'detail' => $inv->public_status_note,
                'date' => $inv->restricted_at?->toIso8601String(),
            ],
            InvestigationStatus::Removed => [
                'type' => 'removed',
                'message' => 'This investigation has been removed under the platform\'s content policy.',
                'detail' => $inv->public_status_note,
                'date' => $inv->removed_at?->toIso8601String(),
            ],
            default => null,
        };
    }
}

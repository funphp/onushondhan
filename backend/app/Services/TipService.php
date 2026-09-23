<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileVault;
use App\Enums\ContactPreference;
use App\Enums\InvestigationStatus;
use App\Enums\ScanStatus;
use App\Enums\TipSenderType;
use App\Enums\TipStatus;
use App\Exceptions\ApiException;
use App\Jobs\ScanTipAttachment;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\Tip;
use App\Models\TipAttachment;
use App\Models\TipMessage;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Files\FileInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Tips distinguish two kinds of anonymity:
 *  - anonymous to the investigator: a signed-in user whose identity is withheld from
 *    the investigator (the platform still knows the account);
 *  - anonymous to the platform: a guest with no account. No account, IP or contact
 *    detail is stored; follow-up works only through a one-time access code whose
 *    hash is kept.
 */
class TipService
{
    public function __construct(
        private readonly FileVault $vault,
        private readonly FileInspector $inspector,
        private readonly AnalyticsService $analytics,
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array{investigator?: string|null, investigation?: string|null, subject: string, message: string, anonymous_to_investigator?: bool, contact_preference?: string|null, contact_value?: string|null}  $data
     * @param  list<UploadedFile>  $files
     * @return array{tip: Tip, access_code: string|null}
     */
    public function submit(array $data, ?User $sender, array $files = []): array
    {
        [$investigator, $investigation] = $this->resolveTarget($data);
        if ($sender === null && ! $this->settings->get('tips.allow_guest')) {
            throw new ApiException('Please sign in to send a tip.', 'guest_tips_disabled', 403);
        }
        if ($sender !== null && $investigator->user_id === $sender->id) {
            throw ApiException::invalidState('You cannot send a tip to yourself.', 'self_tip');
        }

        $preference = ContactPreference::tryFrom((string) ($data['contact_preference'] ?? '')) ?? ContactPreference::PlatformMessaging;
        $contactValue = in_array($preference, [ContactPreference::Email, ContactPreference::Phone], true) ? ($data['contact_value'] ?? null) : null;

        // Inspect all files before storing anything.
        $maxBytes = (int) config('onushondhan.tips.max_attachment_mb') * 1024 * 1024;
        $inspected = array_map(fn (UploadedFile $f) => $this->inspector->inspect($f, config('onushondhan.evidence.allowed'), $maxBytes, 'attachments'), $files);

        $code = $sender === null ? $this->newAccessCode() : null;
        $storedKeys = [];

        try {
            $tip = DB::transaction(function () use ($data, $sender, $investigator, $investigation, $preference, $contactValue, $code, $inspected, &$storedKeys): Tip {
                $tip = new Tip;
                $tip->fill([
                    'subject' => InvestigationService::cleanText($data['subject']),
                    'message' => InvestigationService::cleanText($data['message']),
                    'contact_preference' => $preference,
                    'contact_value' => $contactValue,
                    'anonymous_to_investigator' => $sender === null ? true : (bool) ($data['anonymous_to_investigator'] ?? true),
                ]);
                $tip->investigator_id = $investigator->id;
                $tip->investigation_id = $investigation?->id;
                $tip->sender_user_id = $sender?->id;
                $tip->guest_access_hash = $code !== null ? hash('sha256', $code) : null;
                $tip->last_message_at = now();
                $tip->save();

                foreach ($inspected as $file) {
                    $key = $this->vault->putFile($file->path, 'tips');
                    $storedKeys[] = $key;
                    TipAttachment::query()->create([
                        'tip_id' => $tip->id,
                        'storage_disk' => $this->vault->diskName(),
                        'storage_path' => $key,
                        'original_name' => $file->safeName,
                        'mime_type' => $file->mime,
                        'size_bytes' => $file->size,
                        'sha256' => $file->sha256,
                        'scan_status' => ScanStatus::Pending,
                        'created_at' => now(),
                    ]);
                }

                if ($investigation !== null) {
                    Investigation::query()->whereKey($investigation->id)->increment('tips_count');
                }

                return $tip;
            });
        } catch (\Throwable $e) {
            foreach ($storedKeys as $key) {
                $this->vault->delete($key);
            }
            throw $e;
        }

        foreach ($tip->attachments as $attachment) {
            ScanTipAttachment::dispatch($attachment->id);
        }
        // Deliberately no user id on tip analytics.
        $this->analytics->record('tip_submitted', ['investigation_id' => $investigation?->id, 'investigator_id' => $investigator->id]);
        $investigator->user->notify(new PlatformNotification('new_tip', 'You received a new tip', ['tip_id' => $tip->public_id], '/dashboard/tips/'.$tip->public_id, emailCategory: 'tips'));

        return ['tip' => $tip, 'access_code' => $code];
    }

    public function findByAccessCode(string $code): Tip
    {
        $tip = Tip::query()->where('guest_access_hash', hash('sha256', strtoupper(trim($code))))->first();
        if ($tip === null) {
            throw new ApiException('No tip matches this access code.', 'tip_not_found', 404);
        }

        return $tip;
    }

    public function replyAsTipster(Tip $tip, ?User $user, string $body): TipMessage
    {
        if ($tip->status === TipStatus::Closed) {
            throw ApiException::invalidState('This conversation has been closed by the investigator.', 'tip_closed');
        }
        $message = DB::transaction(function () use ($tip, $user, $body): TipMessage {
            $message = TipMessage::query()->create([
                'tip_id' => $tip->id,
                'sender_user_id' => $user?->id,
                'sender_type' => TipSenderType::Tipster,
                'body' => InvestigationService::cleanText($body),
                'created_at' => now(),
            ]);
            $tip->forceFill(['last_message_at' => now(), 'status' => $tip->status === TipStatus::Archived ? TipStatus::Read : $tip->status])->save();

            return $message;
        });
        $this->analytics->record('tip_message_sent', ['investigator_id' => $tip->investigator_id]);
        $tip->investigator->user->notify(new PlatformNotification('tip_message', 'New message on a tip', ['tip_id' => $tip->public_id], '/dashboard/tips/'.$tip->public_id, emailCategory: 'tips'));

        return $message;
    }

    public function replyAsInvestigator(Tip $tip, User $investigatorUser, string $body): TipMessage
    {
        if ($tip->status === TipStatus::Closed) {
            throw ApiException::invalidState('Re-open the tip before replying.', 'tip_closed');
        }
        $message = DB::transaction(function () use ($tip, $investigatorUser, $body): TipMessage {
            $message = TipMessage::query()->create([
                'tip_id' => $tip->id,
                'sender_user_id' => $investigatorUser->id,
                'sender_type' => TipSenderType::Investigator,
                'body' => InvestigationService::cleanText($body),
                'created_at' => now(),
            ]);
            $tip->forceFill(['last_message_at' => now()])->save();

            return $message;
        });
        $this->analytics->record('tip_message_sent', ['investigator_id' => $tip->investigator_id]);
        $tip->sender?->notify(new PlatformNotification('tip_message', 'The investigator replied to your tip', ['tip_id' => $tip->public_id], '/account/tips/'.$tip->public_id, emailCategory: 'tips'));

        return $message;
    }

    public function setStatus(Tip $tip, TipStatus $status, User $actor): Tip
    {
        $before = $tip->status->value;
        $tip->forceFill(['status' => $status])->save();
        $this->audit->log('tip.status_changed', $tip, ['status' => $before], ['status' => $status->value], $actor);

        return $tip;
    }

    /** Opening a new tip marks it read; tipster messages are marked read too. */
    public function markReadByInvestigator(Tip $tip): void
    {
        if ($tip->status === TipStatus::New) {
            $tip->forceFill(['status' => TipStatus::Read])->save();
        }
        TipMessage::query()->where('tip_id', $tip->id)->where('sender_type', TipSenderType::Tipster)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** @return array{0: Investigator, 1: Investigation|null} */
    private function resolveTarget(array $data): array
    {
        if (! empty($data['investigation'])) {
            $investigation = Investigation::query()->where('public_id', strtoupper((string) $data['investigation']))
                ->where('status', InvestigationStatus::Published)->where('visibility', '!=', 'private')->first();
            if ($investigation === null || ! $investigation->investigator->isActive()) {
                throw new ApiException('This investigation is not accepting tips.', 'tip_target_unavailable', 422);
            }

            return [$investigation->investigator, $investigation];
        }
        $investigator = Investigator::query()->where('slug', (string) ($data['investigator'] ?? ''))->where('status', 'active')->first();
        if ($investigator === null) {
            throw new ApiException('This investigator is not accepting tips.', 'tip_target_unavailable', 422);
        }

        return [$investigator, null];
    }

    private function newAccessCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $raw = '';
        for ($i = 0; $i < 20; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'TIP-'.implode('-', str_split($raw, 5));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Exceptions\ApiException;
use App\Models\Comment;
use App\Models\Complaint;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{complaint_type: string, description: string, investigation?: string|null, comment?: string|null, evidence?: string|null, reporter_email?: string|null} $data */
    public function submit(array $data, ?User $reporter): Complaint
    {
        $investigation = ! empty($data['investigation']) ? Investigation::query()->where('public_id', strtoupper($data['investigation']))->whereNotNull('published_at')->first() : null;
        $comment = ! empty($data['comment']) ? Comment::query()->where('public_id', $data['comment'])->first() : null;
        $evidence = ! empty($data['evidence']) ? Evidence::query()->where('public_id', $data['evidence'])->first() : null;

        if ($investigation === null && $comment === null && $evidence === null) {
            throw new ApiException('Tell us which content this complaint is about.', 'complaint_target_required', 422, ['investigation' => ['A valid investigation, comment or evidence reference is required.']]);
        }
        if ($reporter === null && empty($data['reporter_email'])) {
            throw new ApiException('Provide an email address so we can follow up.', 'validation_failed', 422, ['reporter_email' => ['An email address is required.']]);
        }
        $investigation ??= $comment->investigation ?? $evidence?->investigation;

        return DB::transaction(function () use ($data, $reporter, $investigation, $comment, $evidence): Complaint {
            $complaint = new Complaint([
                'complaint_type' => $data['complaint_type'],
                'description' => InvestigationService::cleanText($data['description']),
                'reporter_email' => $reporter === null ? mb_strtolower((string) $data['reporter_email']) : null,
            ]);
            $complaint->investigation_id = $investigation?->id;
            $complaint->comment_id = $comment?->id;
            $complaint->evidence_id = $evidence?->id;
            $complaint->reporter_user_id = $reporter?->id;
            $complaint->save();
            $this->audit->log('complaint.submitted', $complaint, null, [
                'type' => $complaint->complaint_type->value, 'investigation' => $investigation?->public_id,
                'comment' => $comment?->public_id, 'evidence' => $evidence?->public_id,
            ], $reporter);

            return $complaint;
        });
    }

    public function updateStatus(Complaint $complaint, ComplaintStatus $status, User $actor, ?string $resolution, ?string $internalNotes): Complaint
    {
        return DB::transaction(function () use ($complaint, $status, $actor, $resolution, $internalNotes): Complaint {
            $before = ['status' => $complaint->status->value];
            $complaint->status = $status;
            $complaint->reviewed_by = $actor->id;
            $complaint->reviewed_at = now();
            if ($resolution !== null) {
                $complaint->resolution = InvestigationService::cleanText($resolution);
            }
            if ($internalNotes !== null) {
                $complaint->internal_notes = trim(($complaint->internal_notes ? $complaint->internal_notes."\n\n" : '').'['.now()->toDateTimeString().' '.$actor->username.'] '.InvestigationService::cleanText($internalNotes));
            }
            $complaint->save();
            $this->audit->log('complaint.status_changed', $complaint, $before, ['status' => $status->value, 'resolution' => $complaint->resolution], $actor);

            User::query()->find($complaint->reporter_user_id)?->notify(new PlatformNotification('complaint_status', 'Your complaint status changed', [
                'complaint_id' => $complaint->public_id, 'status' => $status->value,
            ], '/account/complaints', emailCategory: 'moderation'));

            // Load independently of whatever (possibly partial) relation the caller loaded.
            $investigation = $complaint->investigation_id !== null
                ? Investigation::withTrashed()->with('investigator.user')->find($complaint->investigation_id)
                : null;
            if ($status === ComplaintStatus::ActionRequested && $investigation !== null) {
                // The investigator sees the complaint category and text, never who filed it.
                $investigation->investigator->user->notify(new PlatformNotification('complaint_action_requested', 'A complaint about your investigation needs your attention', [
                    'investigation_id' => $investigation->public_id,
                    'complaint_type' => $complaint->complaint_type->value,
                    'summary' => mb_substr($complaint->description, 0, 1000),
                    'note' => $complaint->complaint_type === ComplaintType::FactualDispute
                        ? __('This is a factual dispute. Onushondhan does not adjudicate factual accuracy; please review your report and sources and respond or correct as you see fit.')
                        : null,
                ], '/dashboard/investigations/'.$investigation->public_id, emailCategory: 'moderation'));
            }

            return $complaint;
        });
    }
}

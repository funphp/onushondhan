<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentStatus;
use App\Enums\ComplaintStatus;
use App\Enums\InvestigationStatus;
use App\Enums\InvestigatorStatus;
use App\Enums\ModerationActionType as A;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Complaint;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\ModerationAction;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies moderation actions. Moderation enforces platform policy (spam, abuse,
 * prohibited content, privacy/copyright, malicious files); it is never a statement
 * about the factual accuracy of a report.
 */
class ModerationService
{
    /** target type => [permission, allowed actions] */
    private const MATRIX = [
        'investigation' => ['investigations.moderate', [A::NoAction, A::RequestCorrection, A::Warn, A::Restrict, A::Remove, A::Restore, A::LockComments, A::UnlockComments, A::Escalate]],
        'comment' => ['comments.moderate', [A::NoAction, A::Hide, A::Remove, A::Restore, A::LockComments, A::UnlockComments, A::Escalate]],
        'evidence' => ['evidence.moderate', [A::NoAction, A::Hide, A::Remove, A::Restore, A::Escalate]],
        'user' => ['users.manage', [A::NoAction, A::Warn, A::SuspendUser, A::BanUser, A::ReinstateUser, A::Escalate]],
        'investigator' => ['investigators.manage_any', [A::NoAction, A::Warn, A::Restrict, A::Restore, A::Escalate]],
    ];

    private const ROLE_RANK = ['user' => 0, 'investigator' => 0, 'moderator' => 1, 'admin' => 2, 'super_admin' => 3];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InvestigationService $investigations,
    ) {}

    /** @param array<string, mixed> $details */
    public function apply(User $actor, string $targetType, Model $target, A $action, string $reason, array $details = [], ?Complaint $complaint = null): ModerationAction
    {
        if (! isset(self::MATRIX[$targetType])) {
            throw new ApiException('Unknown moderation target.', 'invalid_target', 422);
        }
        [$permission, $allowed] = self::MATRIX[$targetType];
        if (! $actor->hasPermission($permission)) {
            throw ApiException::forbidden();
        }
        if (! in_array($action, $allowed, true)) {
            throw new ApiException('This action is not available for this target.', 'invalid_action', 422);
        }

        return DB::transaction(function () use ($actor, $targetType, $target, $action, $reason, $details, $complaint): ModerationAction {
            $before = $this->snapshot($target);
            $notifyUser = match (true) {
                $targetType === 'investigation' && $target instanceof Investigation => $this->onInvestigation($target, $action, $details, $actor, $reason),
                $targetType === 'comment' && $target instanceof Comment => $this->onComment($target, $action),
                $targetType === 'evidence' && $target instanceof Evidence => $this->onEvidence($target, $action),
                $targetType === 'user' && $target instanceof User => $this->onUser($target, $action, $details, $actor, $reason),
                $targetType === 'investigator' && $target instanceof Investigator => $this->onInvestigator($target, $action),
                default => throw new ApiException('Target does not match its type.', 'invalid_target', 422),
            };

            $record = ModerationAction::query()->create([
                'moderator_user_id' => $actor->id,
                'target_type' => $targetType,
                'target_id' => $target->getKey(),
                'action' => $action,
                'reason' => mb_substr(InvestigationService::cleanText($reason), 0, 1000),
                'details' => $details === [] ? null : $details,
                'complaint_id' => $complaint?->id,
                'created_at' => now(),
            ]);

            if ($complaint !== null && $action === A::Escalate) {
                $complaint->forceFill(['status' => ComplaintStatus::Escalated, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            }

            $this->audit->log('moderation.'.$action->value, $target, $before, $this->snapshot($target->refresh()) + ['reason' => $reason, 'complaint' => $complaint?->public_id], $actor);

            if ($notifyUser !== null && ! in_array($action, [A::NoAction, A::Escalate], true)) {
                $notifyUser->notify(new PlatformNotification('moderation_action', $this->noticeTitle($targetType, $action), [
                    'target_type' => $targetType,
                    'action' => $action->value,
                    'reason' => mb_substr($reason, 0, 1000),
                ], null, emailCategory: 'moderation'));
            }

            return $record;
        });
    }

    /** @param array<string, mixed> $details */
    private function onInvestigation(Investigation $inv, A $action, array $details, User $actor, string $reason): User
    {
        $publicNote = isset($details['public_note']) ? mb_substr((string) $details['public_note'], 0, 500) : null;
        match ($action) {
            A::Restrict => $this->investigations->transition($inv, $actor, InvestigationStatus::Restricted, 'investigation.restricted', ['restricted_at' => now(), 'public_status_note' => $publicNote], $reason),
            A::Remove => $this->investigations->transition($inv, $actor, InvestigationStatus::Removed, 'investigation.removed', ['removed_at' => now(), 'public_status_note' => $publicNote], $reason),
            A::Restore => $this->investigations->transition($inv, $actor, $inv->current_version > 0 ? InvestigationStatus::Published : InvestigationStatus::Draft, 'investigation.restored', ['restricted_at' => null, 'removed_at' => null, 'public_status_note' => null], $reason),
            A::LockComments => $inv->forceFill(['comments_locked' => true])->save(),
            A::UnlockComments => $inv->forceFill(['comments_locked' => false])->save(),
            default => null,
        };

        return $inv->investigator->user;
    }

    private function onComment(Comment $comment, A $action): ?User
    {
        match ($action) {
            A::Hide => $comment->forceFill(['status' => CommentStatus::Hidden])->save(),
            A::Remove => $comment->forceFill(['status' => CommentStatus::Removed])->save(),
            A::Restore => $comment->forceFill(['status' => CommentStatus::Visible])->save(),
            A::LockComments => $comment->forceFill(['status' => CommentStatus::Locked])->save(),
            A::UnlockComments => $comment->forceFill(['status' => CommentStatus::Visible])->save(),
            default => null,
        };
        if ($action !== A::Escalate) {
            CommentReport::query()->where('comment_id', $comment->id)->where('status', 'open')
                ->update(['status' => $action === A::NoAction ? 'dismissed' : 'actioned', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        }
        $this->recountComments($comment->investigation_id);

        return in_array($action, [A::Hide, A::Remove], true) ? $comment->user : null;
    }

    private function onEvidence(Evidence $evidence, A $action): User
    {
        match ($action) {
            A::Hide => $evidence->forceFill(['moderation_status' => 'hidden'])->save(),
            A::Remove => $evidence->forceFill(['moderation_status' => 'removed'])->save(),
            A::Restore => $evidence->moderation_status === 'quarantined'
                ? throw ApiException::invalidState('Quarantined (infected) files cannot be restored.')
                : $evidence->forceFill(['moderation_status' => 'visible'])->save(),
            default => null,
        };

        return $evidence->investigator->user;
    }

    /** @param array<string, mixed> $details */
    private function onUser(User $user, A $action, array $details, User $actor, string $reason): User
    {
        if ($user->id === $actor->id) {
            throw ApiException::forbidden('You cannot moderate your own account.');
        }
        if ($this->rank($user) >= $this->rank($actor) && ! $actor->isSuperAdmin()) {
            throw ApiException::forbidden('You cannot moderate an account with equal or higher privileges.');
        }
        if ($user->isSuperAdmin()) {
            throw ApiException::forbidden('Super admin accounts cannot be moderated here.');
        }

        match ($action) {
            A::SuspendUser => $user->forceFill([
                'status' => UserStatus::Suspended,
                'status_reason' => mb_substr($reason, 0, 500),
                'suspended_until' => isset($details['days']) ? now()->addDays(max(1, min(3650, (int) $details['days']))) : null,
            ])->save(),
            A::BanUser => $user->forceFill(['status' => UserStatus::Blocked, 'status_reason' => mb_substr($reason, 0, 500), 'suspended_until' => null])->save(),
            A::ReinstateUser => $user->forceFill(['status' => UserStatus::Active, 'status_reason' => null, 'suspended_until' => null])->save(),
            default => null,
        };
        if (in_array($action, [A::SuspendUser, A::BanUser], true)) {
            app(SessionRevoker::class)->revokeUser($user);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        }

        return $user;
    }

    private function onInvestigator(Investigator $investigator, A $action): User
    {
        match ($action) {
            A::Restrict => $investigator->forceFill(['status' => InvestigatorStatus::Suspended])->save(),
            A::Restore => $investigator->forceFill(['status' => InvestigatorStatus::Active])->save(),
            default => null,
        };

        return $investigator->user;
    }

    private function recountComments(int $investigationId): void
    {
        Investigation::query()->whereKey($investigationId)->update([
            'comments_count' => Comment::query()->where('investigation_id', $investigationId)->where('status', CommentStatus::Visible)->count(),
        ]);
    }

    private function rank(User $user): int
    {
        return max(array_map(fn ($slug) => self::ROLE_RANK[$slug] ?? 0, $user->roleSlugs() ?: ['user']));
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $target): array
    {
        return array_intersect_key($target->getAttributes(), array_flip(['status', 'moderation_status', 'comments_locked', 'is_pinned', 'suspended_until', 'visibility']));
    }

    private function noticeTitle(string $type, A $action): string
    {
        return match ($action) {
            A::RequestCorrection => 'A moderator requested a correction to your investigation',
            A::Warn => 'You received a warning from the moderation team',
            A::Restrict => $type === 'investigator' ? 'Your investigator profile has been suspended' : 'Your investigation has been restricted pending review',
            A::Remove => 'Content you published was removed under the content policy',
            A::Hide => 'Content you posted was hidden under the content policy',
            A::Restore, A::ReinstateUser => 'Your content or account has been restored',
            A::SuspendUser => 'Your account has been suspended',
            A::BanUser => 'Your account has been blocked',
            A::LockComments, A::UnlockComments => 'Comment settings changed on your content',
            default => 'A moderation action affected your content',
        };
    }
}

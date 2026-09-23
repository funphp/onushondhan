<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentReportReason;
use App\Enums\CommentStatus;
use App\Enums\InvestigationStatus;
use App\Exceptions\ApiException;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\CommentVote;
use App\Models\Investigation;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\DB;

class CommentService
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AnalyticsService $analytics,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Investigation $investigation, User $user, string $body, ?string $parentPublicId): Comment
    {
        if ($investigation->status !== InvestigationStatus::Published || $investigation->comments_locked) {
            throw ApiException::invalidState('Comments are closed for this investigation.', 'comments_locked');
        }
        $body = self::clean($body);
        if ($body === '') {
            throw new ApiException('Write something before posting.', 'validation_failed', 422, ['body' => ['The comment cannot be empty.']]);
        }

        $parent = null;
        if ($parentPublicId !== null) {
            $parent = Comment::query()->where('public_id', $parentPublicId)->where('investigation_id', $investigation->id)->first();
            if ($parent === null || $parent->status !== CommentStatus::Visible) {
                throw new ApiException('You can no longer reply to this comment.', 'parent_unavailable', 422);
            }
            if ($this->depth($parent) + 1 >= (int) $this->settings->get('comments.max_depth')) {
                throw new ApiException('This thread is too deep to reply to.', 'thread_too_deep', 422);
            }
        }

        $comment = DB::transaction(function () use ($investigation, $user, $body, $parent): Comment {
            $comment = new Comment(['body' => $body]);
            $comment->investigation_id = $investigation->id;
            $comment->user_id = $user->id;
            $comment->parent_id = $parent?->id;
            $comment->save();
            Investigation::query()->whereKey($investigation->id)->increment('comments_count');
            if ($parent !== null) {
                Comment::query()->whereKey($parent->id)->increment('replies_count');
            }
            $this->analytics->record('comment_created', ['user_id' => $user->id, 'investigation_id' => $investigation->id, 'investigator_id' => $investigation->investigator_id]);

            return $comment;
        });

        $owner = $investigation->investigator->user;
        if ($owner->id !== $user->id) {
            $owner->notify(new PlatformNotification('comment_on_investigation', 'New comment on your investigation', [
                'investigation_id' => $investigation->public_id, 'investigation_title' => $investigation->published_title, 'comment_id' => $comment->public_id,
            ], $investigation->publicUrl().'#comment-'.$comment->public_id, emailCategory: 'comments'));
        }
        if ($parent !== null && $parent->user_id !== $user->id && $parent->user_id !== $owner->id) {
            $parent->user->notify(new PlatformNotification('comment_reply', 'Someone replied to your comment', [
                'investigation_id' => $investigation->public_id, 'investigation_title' => $investigation->published_title, 'comment_id' => $comment->public_id,
            ], $investigation->publicUrl().'#comment-'.$comment->public_id, emailCategory: 'comments'));
        }

        return $comment;
    }

    public function update(Comment $comment, string $body): Comment
    {
        if ($comment->status !== CommentStatus::Visible) {
            throw ApiException::invalidState('This comment can no longer be edited.');
        }
        $body = self::clean($body);
        if ($body === '') {
            throw new ApiException('The comment cannot be empty.', 'validation_failed', 422, ['body' => ['The comment cannot be empty.']]);
        }
        $comment->fill(['body' => $body]);
        $comment->edited_at = now();
        $comment->save();

        return $comment;
    }

    public function deleteOwn(Comment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            $comment->status = CommentStatus::Removed;
            $comment->save();
            $comment->delete();
            Investigation::query()->whereKey($comment->investigation_id)->where('comments_count', '>', 0)->decrement('comments_count');
            if ($comment->parent_id !== null) {
                Comment::query()->whereKey($comment->parent_id)->where('replies_count', '>', 0)->decrement('replies_count');
            }
        });
    }

    public function vote(Comment $comment, User $user, int $value): Comment
    {
        if ($comment->user_id === $user->id) {
            throw ApiException::forbidden('You cannot vote on your own comment.', 'own_comment');
        }
        if ($comment->status !== CommentStatus::Visible) {
            throw ApiException::invalidState('This comment is not available.');
        }

        $isNew = DB::transaction(function () use ($comment, $user, $value): bool {
            $existing = CommentVote::query()->where('comment_id', $comment->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing !== null) {
                $existing->update(['value' => $value]);
            } else {
                CommentVote::query()->create(['comment_id' => $comment->id, 'user_id' => $user->id, 'value' => $value]);
            }
            $this->rescore($comment);

            return $existing === null;
        });

        if ($isNew && $value > 0) {
            $this->analytics->record('comment_voted', ['user_id' => $user->id, 'investigation_id' => $comment->investigation_id]);
            $comment->user->notify(new PlatformNotification('comment_vote', 'Your comment received an upvote', [
                'comment_id' => $comment->public_id,
            ], null, emailCategory: null));
        }

        return $comment->refresh();
    }

    public function unvote(Comment $comment, User $user): Comment
    {
        DB::transaction(function () use ($comment, $user): void {
            CommentVote::query()->where('comment_id', $comment->id)->where('user_id', $user->id)->delete();
            $this->rescore($comment);
        });

        return $comment->refresh();
    }

    public function report(Comment $comment, User $user, CommentReportReason $reason, ?string $details): CommentReport
    {
        if ($comment->user_id === $user->id) {
            throw ApiException::invalidState('You cannot report your own comment.');
        }
        $existing = CommentReport::query()->where('comment_id', $comment->id)->where('reporter_user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($comment, $user, $reason, $details): CommentReport {
            $report = CommentReport::query()->create([
                'comment_id' => $comment->id,
                'reporter_user_id' => $user->id,
                'reason' => $reason->value,
                'details' => $details !== null ? self::clean($details) : null,
                'status' => 'open',
            ]);

            $threshold = (int) $this->settings->get('moderation.auto_hide_comment_reports');
            $open = CommentReport::query()->where('comment_id', $comment->id)->where('status', 'open')->count();
            if ($threshold > 0 && $open >= $threshold && $comment->status === CommentStatus::Visible) {
                // Hidden pending human review — not a finding against the author.
                $comment->status = CommentStatus::Pending;
                $comment->save();
                $this->audit->log('comment.auto_hidden', $comment, ['status' => 'visible'], ['status' => 'pending', 'open_reports' => $open]);
            }

            return $report;
        });
    }

    public function depth(Comment $comment): int
    {
        $depth = 0;
        $parentId = $comment->parent_id;
        while ($parentId !== null && $depth < 20) {
            $depth++;
            $parentId = Comment::withTrashed()->whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }

    private function rescore(Comment $comment): void
    {
        $score = (int) CommentVote::query()->where('comment_id', $comment->id)->sum('value');
        Comment::query()->whereKey($comment->id)->update(['score' => $score]);
    }

    public static function clean(string $body): string
    {
        $body = strip_tags($body);
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '';
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? '';

        return trim($body);
    }
}

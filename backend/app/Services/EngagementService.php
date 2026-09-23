<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvestigationStatus;
use App\Enums\ReactionType;
use App\Exceptions\ApiException;
use App\Models\Bookmark;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\InvestigatorFollower;
use App\Models\InvestigatorSupport;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Bookmarks, reactions, follows and investigator support. Every operation is
 * idempotent and backed by a unique constraint; counters are recomputed (not
 * blindly incremented) inside the same transaction so they cannot drift or inflate.
 */
class EngagementService
{
    /** Minutes a user must wait after revoking support before supporting again. */
    public const SUPPORT_COOLDOWN_MINUTES = 60;

    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly AuditLogger $audit,
        private readonly SecurityLogger $security,
        private readonly Settings $settings,
    ) {}

    public function bookmark(User $user, Investigation $inv): void
    {
        $this->assertEngageable($inv);
        $created = $this->idempotent(fn () => Bookmark::query()->create(['user_id' => $user->id, 'investigation_id' => $inv->id, 'created_at' => now()]));
        if ($created) {
            $this->recountInvestigation($inv);
            $this->analytics->record('investigation_bookmark', ['user_id' => $user->id, 'investigation_id' => $inv->id, 'investigator_id' => $inv->investigator_id]);
        }
    }

    public function unbookmark(User $user, Investigation $inv): void
    {
        Bookmark::query()->where('user_id', $user->id)->where('investigation_id', $inv->id)->delete();
        $this->recountInvestigation($inv);
    }

    public function react(User $user, Investigation $inv, ReactionType $type): void
    {
        $this->assertEngageable($inv);
        DB::transaction(function () use ($user, $inv, $type): void {
            Reaction::query()->updateOrCreate(['user_id' => $user->id, 'investigation_id' => $inv->id], ['reaction_type' => $type]);
            $this->recountInvestigation($inv);
        });
    }

    public function unreact(User $user, Investigation $inv): void
    {
        Reaction::query()->where('user_id', $user->id)->where('investigation_id', $inv->id)->delete();
        $this->recountInvestigation($inv);
    }

    public function follow(User $user, Investigator $investigator): void
    {
        if ($investigator->user_id === $user->id) {
            throw ApiException::invalidState('You cannot follow yourself.', 'self_follow');
        }
        $created = $this->idempotent(fn () => InvestigatorFollower::query()->create(['user_id' => $user->id, 'investigator_id' => $investigator->id, 'created_at' => now()]));
        $this->recountInvestigator($investigator);
        if ($created) {
            $this->analytics->record('investigator_followed', ['user_id' => $user->id, 'investigator_id' => $investigator->id]);
            $investigator->user->notify(new PlatformNotification('new_follower', 'You have a new follower', [], null, emailCategory: null));
        }
    }

    public function unfollow(User $user, Investigator $investigator): void
    {
        InvestigatorFollower::query()->where('user_id', $user->id)->where('investigator_id', $investigator->id)->delete();
        $this->recountInvestigator($investigator);
    }

    public function support(User $user, Investigator $investigator): void
    {
        if ($investigator->user_id === $user->id) {
            throw ApiException::invalidState('You cannot support yourself.', 'self_support');
        }
        if ($this->settings->get('support.require_verified_email') && ! $user->hasVerifiedEmail()) {
            throw new ApiException('Verify your email address to support investigators.', 'email_unverified', 403);
        }
        $minAgeHours = (int) $this->settings->get('support.min_account_age_hours');
        if ($minAgeHours > 0 && $user->created_at->gt(now()->subHours($minAgeHours))) {
            throw new ApiException('Your account is too new to support investigators yet.', 'account_too_new', 403);
        }

        $firstTime = DB::transaction(function () use ($user, $investigator): ?bool {
            $row = InvestigatorSupport::query()->where('user_id', $user->id)->where('investigator_id', $investigator->id)->lockForUpdate()->first();
            if ($row !== null && $row->status === 'active') {
                return null; // Already supporting: idempotent no-op.
            }
            if ($row !== null) {
                if ($row->revoked_at !== null && $row->revoked_at->gt(now()->subMinutes(self::SUPPORT_COOLDOWN_MINUTES))) {
                    throw new ApiException('You recently withdrew support. Please wait before supporting again.', 'support_cooldown', 429);
                }
                $row->forceFill(['status' => 'active', 'revoked_at' => null, 'toggle_count' => $row->toggle_count + 1])->save();
                if ($row->toggle_count >= 5) {
                    $this->security->event('support.frequent_toggling', $user, 'info', ['investigator' => $investigator->public_id, 'toggles' => $row->toggle_count]);
                }
            } else {
                try {
                    InvestigatorSupport::query()->create(['user_id' => $user->id, 'investigator_id' => $investigator->id, 'status' => 'active', 'toggle_count' => 1]);
                } catch (UniqueConstraintViolationException) {
                    return null; // Concurrent duplicate — the unique key held.
                }
            }
            $this->recountInvestigator($investigator);
            $this->audit->log('support.created', $investigator, null, ['user' => $user->public_id], $user);

            return $row === null;
        });

        if ($firstTime !== null) {
            $this->analytics->record('support_created', ['user_id' => $user->id, 'investigator_id' => $investigator->id]);
        }
        if ($firstTime === true) {
            $investigator->user->notify(new PlatformNotification('new_supporter', 'Someone supported your work', [], null, emailCategory: null));
        }
    }

    public function revokeSupport(User $user, Investigator $investigator): void
    {
        $revoked = DB::transaction(function () use ($user, $investigator): bool {
            $row = InvestigatorSupport::query()->where('user_id', $user->id)->where('investigator_id', $investigator->id)->where('status', 'active')->lockForUpdate()->first();
            if ($row === null) {
                return false;
            }
            $row->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();
            $this->recountInvestigator($investigator);
            $this->audit->log('support.revoked', $investigator, null, ['user' => $user->public_id], $user);

            return true;
        });
        if ($revoked) {
            $this->analytics->record('support_revoked', ['user_id' => $user->id, 'investigator_id' => $investigator->id]);
        }
    }

    public function recountInvestigator(Investigator $investigator): void
    {
        Investigator::query()->whereKey($investigator->id)->update([
            'supporters_count' => InvestigatorSupport::query()->where('investigator_id', $investigator->id)->where('status', 'active')->count(),
            'followers_count' => InvestigatorFollower::query()->where('investigator_id', $investigator->id)->count(),
        ]);
    }

    public function recountInvestigation(Investigation $inv): void
    {
        Investigation::query()->whereKey($inv->id)->update([
            'bookmarks_count' => Bookmark::query()->where('investigation_id', $inv->id)->count(),
            'reactions_count' => Reaction::query()->where('investigation_id', $inv->id)->count(),
        ]);
    }

    private function assertEngageable(Investigation $inv): void
    {
        if (! in_array($inv->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true)) {
            throw ApiException::invalidState('This investigation is not available.');
        }
    }

    /** Runs a create; returns false if the unique key says it already exists. */
    private function idempotent(callable $create): bool
    {
        try {
            $create();

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}

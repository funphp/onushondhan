<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvestigationStatus;
use App\Models\Investigation;
use App\Models\User;

class InvestigationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('investigations.manage_any') || $user->hasPermission('investigations.moderate');
    }

    /** Full (non-public) view: owner working copy, or staff oversight. */
    public function view(User $user, Investigation $investigation): bool
    {
        return $this->owns($user, $investigation)
            || $user->hasPermission('investigations.manage_any')
            || $user->hasPermission('investigations.moderate');
    }

    public function create(User $user): bool
    {
        return $user->investigator !== null
            && $user->investigator->isActive()
            && $user->hasPermission('investigations.create');
    }

    public function update(User $user, Investigation $investigation): bool
    {
        if ($this->owns($user, $investigation) && $user->hasPermission('investigations.manage_own')) {
            return $user->investigator?->isActive() === true
                && ! in_array($investigation->status, [InvestigationStatus::Restricted, InvestigationStatus::Removed], true);
        }

        return $user->hasPermission('investigations.manage_any');
    }

    public function publish(User $user, Investigation $investigation): bool
    {
        return $this->owns($user, $investigation)
            && $user->hasPermission('investigations.publish_own')
            && $user->hasVerifiedEmail()
            && $user->investigator?->isActive() === true;
    }

    public function withdraw(User $user, Investigation $investigation): bool
    {
        return $this->owns($user, $investigation) && $user->hasPermission('investigations.manage_own');
    }

    public function archive(User $user, Investigation $investigation): bool
    {
        return $this->withdraw($user, $investigation);
    }

    public function delete(User $user, Investigation $investigation): bool
    {
        return ($this->owns($user, $investigation) && $user->hasPermission('investigations.manage_own'))
            || $user->hasPermission('investigations.manage_any');
    }

    public function moderate(User $user, Investigation $investigation): bool
    {
        return $user->hasPermission('investigations.moderate') || $user->hasPermission('investigations.manage_any');
    }

    public function viewAnalytics(User $user, Investigation $investigation): bool
    {
        return ($this->owns($user, $investigation) && $user->hasPermission('analytics.view_own'))
            || $user->hasPermission('analytics.view_any');
    }

    private function owns(User $user, Investigation $investigation): bool
    {
        return $user->investigator !== null && $investigation->investigator_id === $user->investigator->id;
    }
}

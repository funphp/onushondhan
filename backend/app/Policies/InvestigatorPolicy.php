<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Investigator;
use App\Models\User;

class InvestigatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('investigators.view');
    }

    public function view(User $user, Investigator $investigator): bool
    {
        return $this->isOwner($user, $investigator) || $user->hasPermission('investigators.view');
    }

    public function update(User $user, Investigator $investigator): bool
    {
        return ($this->isOwner($user, $investigator) && $user->hasPermission('investigators.manage_own'))
            || $user->hasPermission('investigators.manage_any');
    }

    public function create(User $user): bool
    {
        return false; // Investigators are created only via onboarding.
    }

    public function delete(User $user, Investigator $investigator): bool
    {
        return $user->hasPermission('investigators.manage_any');
    }

    /** Owners may always see their own private identity; staff need the dedicated permission. */
    public function viewPrivateIdentity(User $user, Investigator $investigator): bool
    {
        return $this->isOwner($user, $investigator) || $user->hasPermission('identity.view_private');
    }

    public function managePrivateIdentity(User $user, Investigator $investigator): bool
    {
        return $this->isOwner($user, $investigator) || $user->hasPermission('identity.manage_private');
    }

    public function viewAnalytics(User $user, Investigator $investigator): bool
    {
        return ($this->isOwner($user, $investigator) && $user->hasPermission('analytics.view_own'))
            || $user->hasPermission('analytics.view_any');
    }

    private function isOwner(User $user, Investigator $investigator): bool
    {
        return $investigator->user_id === $user->id;
    }
}

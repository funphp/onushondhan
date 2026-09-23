<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EvidenceVisibility;
use App\Enums\InvestigationStatus;
use App\Enums\ScanStatus;
use App\Models\Evidence;
use App\Models\User;

class EvidencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('evidence.view_restricted') || $user->hasPermission('investigations.manage_any');
    }

    /** Metadata view in owner/admin contexts. */
    public function view(User $user, Evidence $evidence): bool
    {
        return $this->owns($user, $evidence) || $user->hasPermission('evidence.view_restricted');
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return $this->owns($user, $evidence) && $user->hasPermission('evidence.manage_own') && $user->investigator?->isActive() === true;
    }

    public function delete(User $user, Evidence $evidence): bool
    {
        return $this->update($user, $evidence) || $user->hasPermission('investigations.manage_any');
    }

    public function moderate(User $user, Evidence $evidence): bool
    {
        return $user->hasPermission('evidence.moderate');
    }

    /**
     * File access (preview/stream/download). Guests reach only public, clean evidence on
     * a publicly readable investigation. Quarantined files are never served.
     */
    public function access(?User $user, Evidence $evidence): bool
    {
        if ($evidence->scan_status === ScanStatus::Infected || $evidence->moderation_status === 'quarantined' || $evidence->trashed()) {
            return false;
        }

        if ($user !== null && $this->owns($user, $evidence) && $user->hasPermission('evidence.view_private_owned')) {
            return true;
        }

        if ($user !== null && $evidence->visibility !== EvidenceVisibility::Private && $user->hasPermission('evidence.view_restricted')) {
            return true;
        }

        if ($user !== null && $user->isSuperAdmin()) {
            return true;
        }

        $investigation = $evidence->investigation;

        return $evidence->visibility === EvidenceVisibility::Public
            && ! $evidence->isHiddenByModeration()
            && $evidence->isScanAcceptableForPublic()
            && $investigation !== null
            && ! $investigation->trashed()
            && in_array($investigation->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true)
            && $investigation->isPubliclyReadable();
    }

    public function isOwnerOrStaff(User $user, Evidence $evidence): bool
    {
        return $this->owns($user, $evidence) || $user->hasPermission('evidence.view_restricted') || $user->isSuperAdmin();
    }

    private function owns(User $user, Evidence $evidence): bool
    {
        return $user->investigator !== null && $evidence->investigator_id === $user->investigator->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tip;
use App\Models\User;

class TipPolicy
{
    /** Staff oversight of tip contents is need-to-know: only the dedicated permission. */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tips.view_any');
    }

    public function view(User $user, Tip $tip): bool
    {
        return $this->isRecipient($user, $tip) || $this->isSender($user, $tip) || $user->hasPermission('tips.view_any');
    }

    public function manage(User $user, Tip $tip): bool
    {
        return $this->isRecipient($user, $tip) && $user->hasPermission('tips.manage_own');
    }

    public function reply(User $user, Tip $tip): bool
    {
        return ($this->isRecipient($user, $tip) && $user->hasPermission('tips.manage_own')) || $this->isSender($user, $tip);
    }

    public function isRecipient(User $user, Tip $tip): bool
    {
        return $user->investigator !== null && $tip->investigator_id === $user->investigator->id;
    }

    public function isSender(User $user, Tip $tip): bool
    {
        return $tip->sender_user_id !== null && $tip->sender_user_id === $user->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return false; // Accounts are created by registration only.
    }

    public function update(User $user, User $model): bool
    {
        return false; // Status changes go through audited moderation actions.
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function manageRoles(User $user, User $model): bool
    {
        return $user->hasPermission('roles.manage') && $user->id !== $model->id
            && (! $model->isSuperAdmin() || $user->isSuperAdmin());
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for admin-panel resources: one permission to view, one to manage.
 * A null manage permission makes the resource read-only (e.g. append-only logs).
 */
abstract class AdminResourcePolicy
{
    protected string $viewPermission;

    protected ?string $managePermission = null;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->viewPermission);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->managePermission !== null && $user->hasPermission($this->managePermission);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function deleteAny(User $user): bool
    {
        return false; // No bulk deletes from the admin panel.
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}

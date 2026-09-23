<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RolePolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'roles.manage';

    protected ?string $managePermission = 'roles.manage';

    public function update(User $user, Model $model): bool
    {
        // The super_admin role is implicit-all and not editable.
        return parent::update($user, $model) && $model->getAttribute('slug') !== 'super_admin';
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! in_array($model->getAttribute('slug'), Role::PROTECTED_SLUGS, true);
    }
}

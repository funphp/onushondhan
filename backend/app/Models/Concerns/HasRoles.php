<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Minimal RBAC over the spec tables (roles, permissions, role_permissions, user_roles).
 * Permission lookups are memoised per model instance for the request lifetime.
 */
trait HasRoles
{
    /** @var list<string>|null */
    protected ?array $permissionCache = null;

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('created_at');
    }

    /** @return list<string> */
    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')->values()->all();
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roleSlugs(), true);
    }

    /** @param list<string> $slugs */
    public function hasAnyRole(array $slugs): bool
    {
        return array_intersect($slugs, $this->roleSlugs()) !== [];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        if ($this->permissionCache === null) {
            $this->loadMissing('roles.permissions');
            $this->permissionCache = $this->roles
                ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
                ->unique()->values()->all();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $slug): bool
    {
        return $this->isSuperAdmin() || in_array($slug, $this->permissionSlugs(), true);
    }

    public function assignRole(string $slug): void
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id => ['created_at' => now()]]);
        $this->flushRoleCache();
    }

    public function removeRole(string $slug): void
    {
        $role = Role::query()->where('slug', $slug)->first();
        if ($role !== null) {
            $this->roles()->detach($role->id);
        }
        $this->flushRoleCache();
    }

    public function flushRoleCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }
}

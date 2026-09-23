<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Idempotent RBAC baseline from docs/ROLES_PERMISSIONS.md. */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'users.view' => 'View user accounts',
        'users.manage' => 'Manage user accounts (suspend, block, reinstate)',
        'investigators.view' => 'View investigator profiles in admin',
        'investigators.manage_own' => 'Manage own investigator profile',
        'investigators.manage_any' => 'Manage any investigator profile',
        'identity.view_private' => 'View investigators\' private identity (audited)',
        'identity.manage_private' => 'Edit investigators\' private identity (audited)',
        'investigations.view_public' => 'View public investigations',
        'investigations.create' => 'Create investigations',
        'investigations.manage_own' => 'Manage own investigations',
        'investigations.manage_any' => 'Manage any investigation',
        'investigations.publish_own' => 'Publish own investigations',
        'investigations.moderate' => 'Restrict/remove/restore investigations',
        'evidence.manage_own' => 'Manage own evidence',
        'evidence.view_private_owned' => 'Access own private evidence',
        'evidence.view_restricted' => 'Access restricted evidence for review',
        'evidence.moderate' => 'Hide/remove/restore evidence',
        'comments.create' => 'Post comments',
        'comments.manage_own' => 'Edit/delete own comments',
        'comments.moderate' => 'Moderate comments',
        'tips.create' => 'Send tips',
        'tips.view_own' => 'View own tip inbox',
        'tips.manage_own' => 'Manage own tip inbox',
        'tips.view_any' => 'Oversight access to any tip (audited, need-to-know)',
        'complaints.create' => 'File complaints',
        'complaints.manage' => 'Review and resolve complaints',
        'moderation.view' => 'Access moderation queues',
        'support.create' => 'Support investigators',
        'support.revoke' => 'Revoke support',
        'analytics.view_own' => 'View own analytics',
        'analytics.view_any' => 'View platform analytics',
        'audit.view' => 'View audit logs',
        'roles.manage' => 'Manage roles and permissions',
        'settings.manage' => 'Manage system settings',
        'cms.manage' => 'Manage CMS pages',
        'terms.manage' => 'Manage agreement versions',
    ];

    public const ROLES = [
        'user' => ['Registered User', 'Community member', [
            'investigations.view_public', 'comments.create', 'comments.manage_own', 'tips.create', 'complaints.create', 'support.create', 'support.revoke',
        ]],
        'investigator' => ['Investigator', 'Publishes investigations under a public identity', [
            'investigators.manage_own', 'investigations.create', 'investigations.manage_own', 'investigations.publish_own',
            'evidence.manage_own', 'evidence.view_private_owned', 'tips.view_own', 'tips.manage_own', 'analytics.view_own',
        ]],
        'moderator' => ['Moderator', 'Moderation queues, comments, complaints, restricted content', [
            'comments.moderate', 'complaints.manage', 'moderation.view', 'investigations.moderate', 'evidence.view_restricted',
            'evidence.moderate', 'users.view', 'investigators.view', 'audit.view',
        ]],
        'admin' => ['Admin', 'System administration', [
            'users.view', 'users.manage', 'investigators.view', 'investigators.manage_any', 'investigations.manage_any', 'investigations.moderate',
            'evidence.view_restricted', 'evidence.moderate', 'comments.moderate', 'complaints.manage', 'moderation.view',
            'analytics.view_any', 'audit.view', 'cms.manage', 'terms.manage', 'settings.manage',
        ]],
        // Super admin is granted everything implicitly (HasRoles::hasPermission); listed for visibility.
        'super_admin' => ['Super Admin', 'Full administration including roles and private identity access', ['roles.manage', 'identity.view_private', 'identity.manage_private', 'tips.view_any']],
    ];

    public function run(): void
    {
        $ids = [];
        foreach (self::PERMISSIONS as $slug => $name) {
            $ids[$slug] = Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name])->id;
        }
        foreach (self::ROLES as $slug => [$name, $description, $perms]) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);
            // Additive: never strip permissions an operator granted through the admin panel.
            $role->permissions()->syncWithoutDetaching(array_map(fn ($p) => $ids[$p], $perms));
        }
    }
}

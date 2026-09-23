<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SecurityLogger;
use Illuminate\Console\Command;

/**
 * Bootstrap/break-glass role assignment for an existing (self-registered, verified)
 * account, e.g. the first super admin. Never creates accounts or handles passwords.
 */
class GrantRole extends Command
{
    protected $signature = 'users:grant-role {email} {role} {--revoke : Remove the role instead}';

    protected $description = 'Grant (or revoke) a role for an existing verified user (audited)';

    public function handle(AuditLogger $audit, SecurityLogger $security): int
    {
        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();
        $role = (string) $this->argument('role');
        if ($user === null) {
            $this->error('No user with that email. They must register first.');

            return self::FAILURE;
        }
        if (! Role::query()->where('slug', $role)->exists()) {
            $this->error('Unknown role. Available: '.Role::query()->pluck('slug')->implode(', '));

            return self::FAILURE;
        }
        if (! $user->hasVerifiedEmail() && ! $this->option('revoke')) {
            $this->error('The user must verify their email address first.');

            return self::FAILURE;
        }

        $before = $user->roleSlugs();
        $this->option('revoke') ? $user->removeRole($role) : $user->assignRole($role);
        $user->flushRoleCache();
        $audit->log('user.roles_changed', $user, ['roles' => $before], ['roles' => $user->roleSlugs(), 'via' => 'console']);
        $security->event('roles.changed', $user, 'warning', ['via' => 'console', 'role' => $role, 'revoke' => (bool) $this->option('revoke')]);

        $this->info(sprintf('%s now has roles: %s', $user->email, implode(', ', $user->roleSlugs())));

        return self::SUCCESS;
    }
}

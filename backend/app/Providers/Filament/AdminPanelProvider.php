<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Widgets\EngagementChart;
use App\Filament\Widgets\ModerationQueue;
use App\Filament\Widgets\PlatformStats;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Staff-only admin panel at /admin. Access requires an active, verified account with
 * a moderator/admin/super_admin role (User::canAccessPanel); each resource is further
 * gated by policies. TOTP two-factor authentication is enforced when
 * ADMIN_MFA_REQUIRED=true (the production default).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('onusondhan.com Admin')
            ->colors(['primary' => Color::Teal])
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('onusondhan.com')],
                isRequired: (bool) config('onushondhan.admin.mfa_required'),
            )
            ->navigationGroups(['Content', 'Community', 'Moderation', 'People', 'Configuration', 'Audit'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->widgets([PlatformStats::class, ModerationQueue::class, EngagementChart::class])
            ->databaseNotifications(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}

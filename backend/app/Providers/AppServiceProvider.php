<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\FileVault;
use App\Contracts\MalwareScanner;
use App\Contracts\SmsSender;
use App\Models\CmsPage;
use App\Models\InvestigationCategory;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\TermsVersion;
use App\Models\User;
use App\Observers\AdminChangeObserver;
use App\Services\AuthService;
use App\Services\Files\FilesystemFileVault;
use App\Services\Scanning\ClamAvScanner;
use App\Services\Scanning\NullScanner;
use App\Services\Sms\LogSmsSender;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FileVault::class, fn () => new FilesystemFileVault((string) config('onushondhan.vault_disk', 'vault')));

        $this->app->singleton(MalwareScanner::class, function () {
            return match (config('onushondhan.scanner.driver')) {
                'clamav' => new ClamAvScanner((string) config('onushondhan.scanner.clamav_binary'), (int) config('onushondhan.scanner.timeout_seconds')),
                'none', null, '' => new NullScanner,
                default => throw new InvalidArgumentException('Unknown MALWARE_SCANNER driver.'),
            };
        });

        $this->app->singleton(SmsSender::class, fn () => match (config('onushondhan.sms.driver')) {
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS_DRIVER. Bind App\\Contracts\\SmsSender to a real provider.'),
        });
    }

    public function boot(): void
    {
        // Read from config (not env() in bootstrap/app.php) so it still applies when config is cached.
        TrustProxies::at(config('onushondhan.trusted_proxies'));

        // Catch mass-assignment of non-fillable attributes during development and tests.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers()->uncompromised(
            $this->app->isProduction() ? 0 : PHP_INT_MAX
        ));

        // Email links land on the frontend, which calls the API.
        VerifyEmail::createUrlUsing(fn (User $user) => AuthService::verificationUrl($user));
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('onushondhan.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        $this->configureRateLimiting();

        foreach ([CmsPage::class, TermsVersion::class, InvestigationCategory::class, SystemSetting::class, Role::class] as $model) {
            $model::observe(AdminChangeObserver::class);
        }
    }

    /** Server-side render requests from the Next.js server, identified by the shared internal token. */
    private static function isInternalRequest(Request $request): bool
    {
        $token = (string) config('onushondhan.internal_api_token');
        $sent = $request->header('X-Internal-Token');

        return $token !== '' && is_string($sent) && hash_equals($token, $sent);
    }

    private function configureRateLimiting(): void
    {
        $by = fn (Request $r) => $r->user()?->id !== null ? 'u:'.$r->user()->id : 'ip:'.$r->ip();

        RateLimiter::for('api', fn (Request $r) => self::isInternalRequest($r) ? Limit::none() : Limit::perMinute(240)->by($by($r)));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($by($r)));
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perHour(50)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by('register:'.$r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinute(3)->by('pwreset:'.mb_strtolower((string) $r->input('email'))),
            Limit::perHour(20)->by('pwreset-ip:'.$r->ip()),
        ]);
        RateLimiter::for('verification', fn (Request $r) => Limit::perHour(6)->by($by($r)));
        RateLimiter::for('comments', fn (Request $r) => [Limit::perMinute(6)->by($by($r)), Limit::perDay(200)->by($by($r))]);
        RateLimiter::for('engagement', fn (Request $r) => Limit::perMinute(60)->by($by($r)));
        RateLimiter::for('support', fn (Request $r) => [Limit::perMinute(10)->by($by($r)), Limit::perDay(100)->by($by($r))]);
        RateLimiter::for('tips', fn (Request $r) => [Limit::perMinute(3)->by($by($r)), Limit::perDay(30)->by($by($r))]);
        RateLimiter::for('tip-access', fn (Request $r) => Limit::perMinute(10)->by('tip-access:'.$r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(120)->by($by($r)));
        RateLimiter::for('autosave', fn (Request $r) => Limit::perMinute(60)->by($by($r)));
        RateLimiter::for('complaints', fn (Request $r) => [Limit::perMinute(3)->by($by($r)), Limit::perDay(30)->by($by($r))]);
        RateLimiter::for('downloads', fn (Request $r) => Limit::perMinute(60)->by($by($r)));
        RateLimiter::for('analytics', fn (Request $r) => Limit::perMinute(60)->by('an:'.$r->ip()));
    }
}

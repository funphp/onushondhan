<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InvestigationStatus;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\Tip;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Platform';

    public static function canView(): bool
    {
        return Auth::user()?->hasPermission('analytics.view_any') ?? false;
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30);

        return [
            Stat::make('Users', number_format(User::query()->count()))
                ->description(User::query()->where('created_at', '>=', $since)->count().' new in 30 days'),
            Stat::make('Investigators', number_format(Investigator::query()->where('status', 'active')->count())),
            Stat::make('Published investigations', number_format(Investigation::query()->where('status', InvestigationStatus::Published)->count()))
                ->description(Investigation::query()->where('published_at', '>=', $since)->count().' published in 30 days'),
            Stat::make('Views (all time)', number_format((int) Investigation::query()->sum('views_count'))),
            Stat::make('Tips (30 days)', number_format(Tip::query()->where('created_at', '>=', $since)->count()))
                ->description('Counts only — tip contents are need-to-know'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EngagementChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Engagement — last 30 days';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasPermission('analytics.view_any') ?? false;
    }

    protected function getData(): array
    {
        $events = ['investigation_view' => 'Views', 'investigation_share' => 'Shares', 'comment_created' => 'Comments', 'investigation_published' => 'Publications'];
        $start = now()->subDays(29)->startOfDay();
        $rows = DB::table('analytics_events')
            ->select('event_name', DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as c'))
            ->whereIn('event_name', array_keys($events))->where('created_at', '>=', $start)
            ->groupBy('event_name', DB::raw('DATE(created_at)'))->get();

        $labels = [];
        for ($i = 0; $i < 30; $i++) {
            $labels[] = $start->copy()->addDays($i)->toDateString();
        }
        $datasets = [];
        foreach ($events as $key => $label) {
            $byDay = $rows->where('event_name', $key)->pluck('c', 'day');
            $datasets[] = ['label' => $label, 'data' => array_map(fn ($d) => (int) ($byDay[$d] ?? 0), $labels)];
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Investigation;
use App\Models\Investigator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Aggregates for investigator dashboards. Only counts — never viewer identities. */
class InvestigatorAnalyticsService
{
    private const SERIES = ['investigation_view', 'investigation_share', 'comment_created', 'investigation_bookmark', 'tip_submitted', 'support_created', 'investigator_followed'];

    /** @return array<string, mixed> */
    public function summary(Investigator $investigator, int $days = 30, ?Investigation $investigation = null): array
    {
        $days = max(7, min(365, $days));
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('analytics_events')
            ->select('event_name', DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as c'))
            ->where('investigator_id', $investigator->id)
            ->when($investigation, fn ($q) => $q->where('investigation_id', $investigation->id))
            ->whereIn('event_name', self::SERIES)
            ->where('created_at', '>=', $since)
            ->groupBy('event_name', DB::raw('DATE(created_at)'))
            ->get();

        $series = [];
        foreach (self::SERIES as $event) {
            $series[$event] = [];
            for ($d = 0; $d < $days; $d++) {
                $series[$event][$since->copy()->addDays($d)->toDateString()] = 0;
            }
        }
        foreach ($rows as $row) {
            $series[$row->event_name][$row->day] = (int) $row->c;
        }

        $totalsQuery = Investigation::query()->where('investigator_id', $investigator->id)->when($investigation, fn ($q) => $q->whereKey($investigation->id));

        return [
            'range_days' => $days,
            'totals' => [
                'views' => (int) (clone $totalsQuery)->sum('views_count'),
                'shares' => (int) (clone $totalsQuery)->sum('shares_count'),
                'comments' => (int) (clone $totalsQuery)->sum('comments_count'),
                'bookmarks' => (int) (clone $totalsQuery)->sum('bookmarks_count'),
                'reactions' => (int) (clone $totalsQuery)->sum('reactions_count'),
                'tips' => (int) (clone $totalsQuery)->sum('tips_count'),
                'supporters' => $investigator->supporters_count,
                'followers' => $investigator->followers_count,
                'published_investigations' => $investigator->published_investigations_count,
            ],
            'series' => collect($series)->map(fn ($days) => collect($days)->map(fn ($v, $d) => ['date' => $d, 'count' => $v])->values())->all(),
            'top_investigations' => $investigation ? [] : Investigation::query()->where('investigator_id', $investigator->id)
                ->whereNotNull('published_at')->orderByDesc('views_count')->limit(5)
                ->get(['public_id', 'published_title', 'views_count', 'shares_count', 'comments_count'])
                ->map(fn ($i) => ['id' => $i->public_id, 'title' => $i->published_title, 'views' => $i->views_count, 'shares' => $i->shares_count, 'comments' => $i->comments_count]),
            'note' => 'Support and follower counts are engagement signals, not measures of accuracy.',
        ];
    }
}

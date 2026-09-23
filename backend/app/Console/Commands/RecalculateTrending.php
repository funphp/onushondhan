<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvestigationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trending = weighted engagement over the last 7 days with age decay. Engagement
 * counts only — never an accuracy signal.
 */
class RecalculateTrending extends Command
{
    protected $signature = 'investigations:trending';

    protected $description = 'Recalculate trending scores for published investigations';

    private const WEIGHTS = [
        'investigation_view' => 1.0,
        'investigation_share' => 4.0,
        'comment_created' => 3.0,
        'investigation_bookmark' => 3.0,
    ];

    public function handle(): int
    {
        $since = now()->subDays(7);
        $rows = DB::table('analytics_events')
            ->select('investigation_id', 'event_name', DB::raw('COUNT(DISTINCT COALESCE(user_id, anonymous_fingerprint)) as c'))
            ->whereNotNull('investigation_id')
            ->whereIn('event_name', array_keys(self::WEIGHTS))
            ->where('created_at', '>=', $since)
            ->groupBy('investigation_id', 'event_name')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $scores[$row->investigation_id] = ($scores[$row->investigation_id] ?? 0) + self::WEIGHTS[$row->event_name] * (int) $row->c;
        }

        DB::table('investigations')->where('trending_score', '>', 0)->update(['trending_score' => 0]);
        $published = DB::table('investigations')->whereIn('id', array_keys($scores))
            ->where('status', InvestigationStatus::Published->value)->pluck('published_at', 'id');

        foreach ($published as $id => $publishedAt) {
            $ageDays = max(0, now()->diffInHours($publishedAt, true) / 24);
            $score = $scores[$id] / pow($ageDays + 2, 0.8);
            DB::table('investigations')->where('id', $id)->update(['trending_score' => round($score, 4)]);
        }

        $this->info('Trending recalculated for '.count($published).' investigations.');

        return self::SUCCESS;
    }
}

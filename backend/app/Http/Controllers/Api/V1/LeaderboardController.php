<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvestigationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvestigatorSummaryResource;
use App\Models\Investigator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Community leaderboards with deliberately neutral labels. Rankings measure community
 * engagement only — never accuracy, truth or investigative quality.
 */
class LeaderboardController extends Controller
{
    public const BOARDS = [
        'top_supported' => 'Top Supported Investigators',
        'rising' => 'Rising Investigators',
        'most_published' => 'Most Published',
        'most_read' => 'Most Read',
        'most_shared' => 'Most Shared',
        'most_discussed' => 'Most Discussed',
    ];

    public const NOTE = 'Rankings reflect community engagement (support, follows, reads and discussion). They are not a measure of accuracy, truth or investigative quality.';

    public function support(Request $request): JsonResponse
    {
        return $this->board('top_supported', $request);
    }

    public function show(Request $request, string $board): JsonResponse
    {
        abort_unless(array_key_exists($board, self::BOARDS), 404);

        return $this->board($board, $request);
    }

    private function board(string $board, Request $request): JsonResponse
    {
        $limit = min(50, max(1, $request->integer('limit', 20)));
        $rows = Cache::remember("leaderboard.{$board}.{$limit}", 300, fn () => $this->compute($board, $limit));
        $investigators = Investigator::query()->whereIn('id', array_keys($rows))->get()->keyBy('id');

        $data = [];
        $rank = 1;
        foreach ($rows as $id => $score) {
            if (! isset($investigators[$id])) {
                continue;
            }
            $data[] = ['rank' => $rank++, 'score' => (int) $score, 'investigator' => new InvestigatorSummaryResource($investigators[$id])];
        }

        return response()->json([
            'board' => $board,
            'label' => self::BOARDS[$board],
            'note' => self::NOTE,
            'boards' => collect(self::BOARDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'data' => $data,
        ]);
    }

    /** @return array<int, int> investigator id => score, ordered */
    private function compute(string $board, int $limit): array
    {
        $active = fn ($q) => $q->where('investigators.status', 'active')->whereNull('investigators.deleted_at');

        $query = match ($board) {
            'top_supported' => DB::table('investigators')->tap($active)->where('supporters_count', '>', 0)->select('investigators.id as inv_id', 'supporters_count as score'),
            'most_published' => DB::table('investigators')->tap($active)->where('published_investigations_count', '>', 0)->select('investigators.id as inv_id', 'published_investigations_count as score'),
            'most_read' => DB::table('investigators')->tap($active)->where('total_views', '>', 0)->select('investigators.id as inv_id', 'total_views as score'),
            'rising' => DB::table('investigator_supports as s')->join('investigators', 'investigators.id', '=', 's.investigator_id')->tap($active)
                ->where('s.status', 'active')->where('s.created_at', '>=', now()->subDays(30))
                ->groupBy('investigators.id')->select('investigators.id as inv_id', DB::raw('COUNT(*) as score')),
            'most_shared', 'most_discussed' => DB::table('investigations as i')->join('investigators', 'investigators.id', '=', 'i.investigator_id')->tap($active)
                ->whereIn('i.status', [InvestigationStatus::Published->value, InvestigationStatus::Archived->value])->where('i.visibility', 'public')->whereNull('i.deleted_at')
                ->groupBy('investigators.id')
                ->select('investigators.id as inv_id', DB::raw('SUM('.($board === 'most_shared' ? 'i.shares_count' : 'i.comments_count').') as score'))
                ->having('score', '>', 0),
        };

        return $query->orderByDesc('score')->orderBy('inv_id')->limit($limit)->get()->mapWithKeys(fn ($r) => [(int) $r->inv_id => (int) $r->score])->all();
    }
}

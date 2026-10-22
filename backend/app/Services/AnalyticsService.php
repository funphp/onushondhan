<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Notifications\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * Privacy-conscious analytics: no raw IPs are stored. Visitors are represented by a
 * keyed hash of (IP, user agent, day) that rotates daily and cannot be reversed
 * without the server-side key. Events are pruned after the retention window.
 */
class AnalyticsService
{
    /** Read counts that trigger a milestone notification to the investigator. */
    public const VIEW_MILESTONES = [100, 1000, 10000, 100000, 1000000];

    public const EVENTS = [
        'page_view', 'investigation_view', 'investigation_share', 'investigation_bookmark', 'comment_created',
        'comment_voted', 'support_created', 'support_revoked', 'investigator_followed', 'tip_submitted',
        'tip_message_sent', 'evidence_previewed', 'evidence_downloaded', 'search_performed',
        'investigation_published', 'investigation_withdrawn',
    ];

    /**
     * @param  array{user_id?: int|null, investigation_id?: int|null, investigator_id?: int|null}  $refs
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $event, array $refs = [], array $metadata = [], ?string $pageUrl = null): void
    {
        AnalyticsEvent::query()->create([
            'user_id' => $refs['user_id'] ?? null,
            'session_id' => null,
            'investigation_id' => $refs['investigation_id'] ?? null,
            'investigator_id' => $refs['investigator_id'] ?? null,
            'event_name' => $event,
            'page_url' => $pageUrl !== null ? mb_substr($pageUrl, 0, 500) : null,
            'referrer' => $this->referrerHost(),
            'anonymous_fingerprint' => $this->fingerprint(),
            'metadata_json' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    /** Count a view at most once per visitor per dedupe window. Returns true when counted. */
    public function recordView(Investigation $investigation, Request $request): bool
    {
        $visitor = $request->user()?->id !== null ? 'u'.$request->user()->id : 'f'.$this->fingerprint();
        $key = 'view:'.$investigation->id.':'.$visitor;
        if (! Cache::add($key, 1, now()->addMinutes((int) config('onushondhan.analytics.view_dedupe_minutes', 30)))) {
            return false;
        }

        DB::transaction(function () use ($investigation, $request): void {
            Investigation::query()->whereKey($investigation->id)->increment('views_count');
            $views = (int) Investigation::query()->whereKey($investigation->id)->value('views_count');
            if (in_array($views, self::VIEW_MILESTONES, true)) {
                $investigation->loadMissing('investigator.user');
                $investigation->investigator->user->notify(new PlatformNotification('investigation_milestone', 'Your investigation reached :views reads', [
                    'investigation_id' => $investigation->public_id,
                    'investigation_title' => $investigation->published_title,
                    'views' => $views,
                ], '/dashboard/investigations/'.$investigation->public_id, emailCategory: null, titleParams: ['views' => $views]));
            }
            Investigator::query()->whereKey($investigation->investigator_id)->increment('total_views');
            $this->record('investigation_view', [
                'user_id' => $request->user()?->id,
                'investigation_id' => $investigation->id,
                'investigator_id' => $investigation->investigator_id,
            ]);
        });

        return true;
    }

    public function fingerprint(): ?string
    {
        $key = (string) (config('onushondhan.analytics.fingerprint_key') ?: config('app.key'));
        $ip = (string) RequestFacade::ip();
        if ($ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip.'|'.RequestFacade::userAgent().'|'.now()->toDateString(), $key);
    }

    private function referrerHost(): ?string
    {
        $ref = (string) RequestFacade::header('referer', '');
        $host = $ref !== '' ? parse_url($ref, PHP_URL_HOST) : null;

        return is_string($host) ? mb_substr($host, 0, 255) : null;
    }
}

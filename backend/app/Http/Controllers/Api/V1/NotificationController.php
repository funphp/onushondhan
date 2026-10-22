<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }
        $page = $query->paginate(min(50, max(1, $request->integer('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->data['kind'] ?? class_basename($n->type),
                'data' => $this->localized($n->data),
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /**
     * Re-render the stored title in the request's interface language.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function localized(array $data): array
    {
        if (isset($data['title_key']) && is_string($data['title_key'])) {
            $params = is_array($data['params'] ?? null) ? $data['params'] : [];
            $data['title'] = __($data['title_key'], array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $params));
        }
        unset($data['title_key']);

        return $data;
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        // Scoped to the caller's own notifications: other users' ids simply 404.
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => __('Marked as read.')]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => __('All notifications marked as read.')]);
    }
}

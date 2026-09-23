<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CommentStatus;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public comment view. Authors are identified by public username — or, for the
 * investigation's own investigator, by their public investigator identity.
 */
class CommentResource extends JsonResource
{
    /** @var array<int, int> comment id => viewer's vote value */
    public static array $viewerVotes = [];

    public static ?int $viewerId = null;

    public static ?int $ownerUserId = null;

    public static ?array $ownerIdentity = null;

    public function toArray(Request $request): array
    {
        /** @var Comment $c */
        $c = $this->resource;
        $gone = $c->trashed() || $c->status !== CommentStatus::Visible;
        $isOwner = self::$ownerUserId !== null && $c->user_id === self::$ownerUserId;

        return [
            'id' => $c->public_id,
            'body' => $gone ? null : $c->body,
            'placeholder' => $gone ? ($c->trashed() || $c->status === CommentStatus::Removed ? 'This comment was deleted.' : 'This comment is hidden.') : null,
            'status' => $gone ? ($c->trashed() ? 'removed' : $c->status->value) : 'visible',
            'author' => $gone ? null : ($isOwner
                ? ['name' => self::$ownerIdentity['public_name'] ?? 'Investigator', 'role' => 'investigator', 'slug' => self::$ownerIdentity['slug'] ?? null]
                : ['name' => $c->user?->username ?? 'former-user', 'role' => 'member', 'slug' => null]),
            'is_pinned' => $c->is_pinned,
            'score' => $c->score,
            'replies_count' => $c->replies_count,
            'parent_id' => $c->relationLoaded('parent') ? $c->parent?->public_id : null,
            'created_at' => $c->created_at->toIso8601String(),
            'edited_at' => $c->edited_at?->toIso8601String(),
            'viewer' => self::$viewerId === null ? null : [
                'vote' => self::$viewerVotes[$c->id] ?? 0,
                'is_author' => $c->user_id === self::$viewerId,
            ],
            'replies' => $c->relationLoaded('replies') ? CommentResource::collection($c->replies) : [],
        ];
    }
}

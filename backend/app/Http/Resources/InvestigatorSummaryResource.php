<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Investigator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe investigator attribution. Only public identity fields — never the
 * account's name/email or anything from investigator_private_identity.
 */
class InvestigatorSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Investigator $inv */
        $inv = $this->resource;

        return [
            'id' => $inv->public_id,
            'public_name' => $inv->public_name,
            'slug' => $inv->slug,
            'identity_mode' => $inv->identity_mode->value,
            'avatar_url' => self::avatarUrl($inv),
            'supporters_count' => $inv->supporters_count,
            'followers_count' => $inv->followers_count,
            'published_investigations_count' => $inv->published_investigations_count,
        ];
    }

    public static function avatarUrl(Investigator $inv): ?string
    {
        if ($inv->avatar_path === null) {
            return null;
        }

        return '/api/v1/investigators/'.$inv->slug.'/avatar?v='.substr(hash('sha256', $inv->avatar_path), 0, 12);
    }
}

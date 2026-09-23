<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $investigation_id
 * @property int $sort_order
 * @property Carbon|null $accessed_at
 * @property string $title
 * @property string $source_type
 * @property string|null $publisher
 * @property string|null $url
 * @property string|null $citation_text
 * @property bool $is_public
 */
class Source extends Model
{
    protected $table = 'sources';

    protected $fillable = ['title', 'source_type', 'publisher', 'url', 'citation_text', 'accessed_at', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'accessed_at' => 'date'];
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }
}

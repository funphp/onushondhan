<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record of the explicit responsibility confirmation made before each publication.
 *
 * @property int $id
 * @property int $investigation_id
 * @property int $user_id
 * @property int $version_no
 * @property array<string, bool> $statements_json
 * @property \Illuminate\Support\Carbon $confirmed_at
 */
class PublishConfirmation extends Model
{
    public $timestamps = false;

    protected $table = 'investigation_publish_confirmations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['statements_json' => 'array', 'confirmed_at' => 'datetime'];
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    /** @return BelongsTo<TermsVersion, $this> */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }
}

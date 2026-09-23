<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Never serialised by any public API resource. Every read by staff is audited
 * (see IdentityAccessService). Columns are encrypted at rest with APP_KEY.
 *
 * @property int $id
 * @property int $investigator_id
 * @property string|null $legal_or_private_name
 * @property string|null $private_email
 * @property string|null $private_phone
 * @property string|null $notes
 */
class InvestigatorPrivateIdentity extends Model
{
    protected $table = 'investigator_private_identity';

    protected $fillable = ['legal_or_private_name', 'private_email', 'private_phone', 'notes'];

    protected $hidden = ['legal_or_private_name', 'private_email', 'private_phone', 'notes'];

    protected function casts(): array
    {
        return [
            'legal_or_private_name' => 'encrypted',
            'private_email' => 'encrypted',
            'private_phone' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Investigator, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }
}

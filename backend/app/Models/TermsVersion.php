<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementType;
use App\Enums\TermsStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AgreementType $agreement_type
 * @property string $version
 * @property string $title
 * @property string $content
 * @property TermsStatus $status
 * @property Carbon|null $effective_at
 * @property Carbon|null $published_at
 */
class TermsVersion extends Model
{
    protected $fillable = ['agreement_type', 'version', 'title', 'content', 'effective_at'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'agreement_type' => AgreementType::class,
            'status' => TermsStatus::class,
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<TermsVersion> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', TermsStatus::Published)
            ->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()));
    }

    public static function currentFor(AgreementType|string $type): ?self
    {
        $type = $type instanceof AgreementType ? $type->value : $type;

        return self::query()->current()->where('agreement_type', $type)
            ->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TermsAcceptance, $this> */
    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }
}

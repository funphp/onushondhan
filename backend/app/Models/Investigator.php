<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentityMode;
use App\Enums\InvestigatorStatus;
use App\Support\Slug;
use Database\Factories\InvestigatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $public_name
 * @property string $slug
 * @property string|null $bio
 * @property string|null $avatar_path
 * @property string|null $website_url
 * @property IdentityMode $identity_mode
 * @property InvestigatorStatus $status
 * @property int $supporters_count
 * @property int $followers_count
 * @property int $published_investigations_count
 * @property int $total_views
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read InvestigatorPrivateIdentity|null $privateIdentity
 */
class Investigator extends Model
{
    /** @use HasFactory<InvestigatorFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    protected $fillable = ['public_name', 'bio', 'website_url', 'identity_mode'];

    protected $attributes = [
        'status' => 'active',
        'identity_mode' => 'protected_public_identity',
    ];

    protected function casts(): array
    {
        return [
            'identity_mode' => IdentityMode::class,
            'status' => InvestigatorStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Slug::make($name, 100, 'investigator');
        $slug = $base;
        $i = 2;
        while (self::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<InvestigatorPrivateIdentity, $this> */
    public function privateIdentity(): HasOne
    {
        return $this->hasOne(InvestigatorPrivateIdentity::class);
    }

    /** @return HasMany<Investigation, $this> */
    public function investigations(): HasMany
    {
        return $this->hasMany(Investigation::class);
    }

    /** @return HasMany<InvestigatorSupport, $this> */
    public function supports(): HasMany
    {
        return $this->hasMany(InvestigatorSupport::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'investigator_followers')->withPivot('created_at');
    }

    /** @return HasMany<Tip, $this> */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    public function isActive(): bool
    {
        return $this->status === InvestigatorStatus::Active;
    }

    /** Identity snapshot embedded in versions — public-safe fields only. */
    public function identitySnapshot(): array
    {
        return [
            'public_id' => $this->public_id,
            'public_name' => $this->public_name,
            'slug' => $this->slug,
            'identity_mode' => $this->identity_mode->value,
        ];
    }
}

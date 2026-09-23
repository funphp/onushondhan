<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use Database\Factories\InvestigationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Columns title..period_end are the owner's working copy. Public pages render the
 * current InvestigationVersion snapshot; published_* columns project it for search.
 *
 * @property int $id
 * @property string $public_id
 * @property int $investigator_id
 * @property int|null $category_id
 * @property int|null $subcategory_id
 * @property string $title
 * @property string|null $slug
 * @property string|null $summary
 * @property string|null $body
 * @property array<int, string>|null $recommendations
 * @property array<int, array<string, string>>|null $entities
 * @property string|null $location_name
 * @property string|null $location_country
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property int $draft_revision
 * @property bool $has_unpublished_changes
 * @property string|null $published_title
 * @property string|null $published_summary
 * @property string|null $published_body
 * @property array<string, mixed>|null $identity_snapshot
 * @property InvestigationStatus $status
 * @property InvestigationVisibility $visibility
 * @property Carbon|null $published_at
 * @property Carbon|null $last_published_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $restricted_at
 * @property Carbon|null $removed_at
 * @property string|null $withdrawal_reason
 * @property string|null $public_status_note
 * @property int $current_version
 * @property bool $comments_locked
 * @property int $views_count
 * @property int $shares_count
 * @property int $comments_count
 * @property int $bookmarks_count
 * @property int $reactions_count
 * @property int $tips_count
 * @property float $trending_score
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Investigator $investigator
 * @property-read InvestigationCategory|null $category
 * @property-read InvestigationCategory|null $subcategory
 * @property-read InvestigationVersion|null $currentVersion
 */
class Investigation extends Model
{
    /** @use HasFactory<InvestigationFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Owner-editable working-copy fields only. */
    protected $fillable = [
        'title', 'summary', 'body', 'recommendations', 'entities', 'category_id', 'subcategory_id',
        'location_name', 'location_country', 'location_lat', 'location_lng', 'period_start', 'period_end',
        'visibility',
    ];

    protected $attributes = [
        'status' => 'draft',
        'visibility' => 'public',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvestigationStatus::class,
            'visibility' => InvestigationVisibility::class,
            'recommendations' => 'array',
            'entities' => 'array',
            'identity_snapshot' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'published_at' => 'datetime',
            'last_published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'archived_at' => 'datetime',
            'restricted_at' => 'datetime',
            'removed_at' => 'datetime',
            'has_unpublished_changes' => 'boolean',
            'comments_locked' => 'boolean',
            'trending_score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Investigation $investigation): void {
            if (blank($investigation->public_id)) {
                $investigation->public_id = self::generatePublicId();
            }
        });
    }

    /** Non-sequential public identifier, e.g. INV-2026-K7Q3M9XZ. */
    public static function generatePublicId(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, 31)];
            }
            $id = 'INV-'.now()->format('Y').'-'.$code;
        } while (self::withTrashed()->where('public_id', $id)->exists());

        return $id;
    }

    public static function makeSlug(string $title): string
    {
        return Str::limit(Str::slug($title) ?: 'investigation', 200, '');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    // --- Scopes -------------------------------------------------------------

    /** Published + public: eligible for listings, search, sitemap. @param Builder<Investigation> $query */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('investigations.status', InvestigationStatus::Published)
            ->where('investigations.visibility', InvestigationVisibility::Public)
            ->whereHas('investigator', fn ($q) => $q->where('status', 'active'));
    }

    /** Readable by URL by anyone (public or unlisted). @param Builder<Investigation> $query */
    public function scopePubliclyReadable(Builder $query): void
    {
        $query->whereIn('status', [InvestigationStatus::Published, InvestigationStatus::Archived])
            ->whereIn('visibility', [InvestigationVisibility::Public, InvestigationVisibility::Unlisted])
            ->whereHas('investigator', fn ($q) => $q->where('status', 'active'));
    }

    public function isPubliclyReadable(): bool
    {
        return in_array($this->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true)
            && $this->visibility !== InvestigationVisibility::Private
            && $this->investigator->isActive();
    }

    public function isEditableByOwner(): bool
    {
        return in_array($this->status, [InvestigationStatus::Draft, InvestigationStatus::Published, InvestigationStatus::Withdrawn], true);
    }

    public function publicUrl(): string
    {
        return config('onushondhan.frontend_url').'/investigation/'.$this->public_id.'/'.($this->slug ?? '');
    }

    // --- Relations ------------------------------------------------------------

    /** @return BelongsTo<Investigator, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    /** @return BelongsTo<InvestigationCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InvestigationCategory::class, 'category_id');
    }

    /** @return BelongsTo<InvestigationCategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(InvestigationCategory::class, 'subcategory_id');
    }

    /** @return BelongsToMany<InvestigationTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(InvestigationTag::class, 'investigation_tag_map', 'investigation_id', 'tag_id');
    }

    /** @return HasMany<InvestigationLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(InvestigationLocation::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<TimelineEvent, $this> */
    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEvent::class)->orderBy('sort_order')->orderBy('event_date')->orderBy('id');
    }

    /** @return HasMany<Source, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<InvestigationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(InvestigationVersion::class)->orderByDesc('version_no');
    }

    /** @return HasOne<InvestigationVersion, $this> */
    public function currentVersion(): HasOne
    {
        // The current published version is always the highest version number.
        return $this->hasOne(InvestigationVersion::class)->latestOfMany('version_no');
    }

    /** @return HasMany<Evidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<PublishConfirmation, $this> */
    public function publishConfirmations(): HasMany
    {
        return $this->hasMany(PublishConfirmation::class);
    }
}

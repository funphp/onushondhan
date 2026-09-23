<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $investigation_id
 * @property int|null $comment_id
 * @property int|null $evidence_id
 * @property int|null $reporter_user_id
 * @property string|null $reporter_email
 * @property ComplaintType $complaint_type
 * @property string $description
 * @property ComplaintStatus $status
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $resolution
 * @property string|null $internal_notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Investigation|null $investigation
 * @property-read Comment|null $comment
 * @property-read Evidence|null $evidence
 * @property-read User|null $reporter
 */
class Complaint extends Model
{
    use HasUlids;

    protected $fillable = ['complaint_type', 'description', 'reporter_email'];

    protected $hidden = ['internal_notes'];

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return [
            'complaint_type' => ComplaintType::class,
            'status' => ComplaintStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ComplaintStatus::Submitted, ComplaintStatus::UnderReview, ComplaintStatus::ActionRequested, ComplaintStatus::Escalated], true);
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class)->withTrashed();
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class)->withTrashed();
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ModerationAction, $this> */
    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class);
    }
}

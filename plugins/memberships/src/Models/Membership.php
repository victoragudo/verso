<?php

namespace Verso\Plugins\Memberships\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Verso\Plugins\Memberships\Enums\MembershipStatus;

// Links a user to a plan, with an optional end date.
/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property MembershipStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Plan $plan
 */
class Membership extends Model
{
    protected $attributes = ['status' => 'active'];

    protected $fillable = ['user_id', 'plan_id', 'status', 'starts_at', 'expires_at', 'note'];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Active memberships that have started and not passed their end date.
    /** @param Builder<Membership> $query */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query->where('status', MembershipStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at));
    }

    // Active memberships whose end date has passed.
    /** @param Builder<Membership> $query */
    public function scopeDue(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->where('status', MembershipStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $at ?? now());
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === MembershipStatus::Active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->expires_at === null || $this->expires_at->greaterThan($at));
    }

    public function expire(): void
    {
        $this->update(['status' => MembershipStatus::Expired]);
    }

    public function cancel(): void
    {
        $this->update(['status' => MembershipStatus::Cancelled]);
    }
}

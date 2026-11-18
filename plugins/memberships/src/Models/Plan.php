<?php

namespace Verso\Plugins\Memberships\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// A membership plan. A higher level opens everything that lower levels open.
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $level
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Plan extends Model
{
    protected $table = 'membership_plans';

    protected $fillable = ['name', 'slug', 'description', 'level'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Plan $plan): void {
            $plan->slug = Str::slug(filled($plan->slug) ? $plan->slug : $plan->name);
        });
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    // True when a member of this plan can see content restricted to the other plan.
    public function covers(Plan $other): bool
    {
        return $this->level >= $other->level;
    }
}

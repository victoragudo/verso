<?php

namespace App\Verso\Layouts\Models;

use App\Models\User;
use App\Verso\Core\Database\JsonArray;
use App\Verso\Layouts\LayoutKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One theme builder layout. The working copy (blocks, conditions, settings) is saved as people edit;
 * publishing copies blocks and settings to the published columns, which visitors see.
 *
 * @property int $id
 * @property LayoutKind $kind
 * @property string $name
 * @property array<int, mixed> $blocks
 * @property array<int, mixed>|null $published_blocks
 * @property array<int, mixed> $conditions
 * @property array<string, mixed> $settings
 * @property array<string, mixed>|null $published_settings
 * @property int $priority
 * @property string $status
 * @property int|null $user_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'name', 'blocks', 'published_blocks', 'conditions', 'settings', 'published_settings', 'priority', 'status', 'user_id', 'published_at'])]
class SiteLayout extends Model
{
    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    // Turned off: kept, but visitors never see it.
    public const string OFF = 'off';

    protected function casts(): array
    {
        return [
            'kind' => LayoutKind::class,
            'blocks' => JsonArray::class,
            'published_blocks' => JsonArray::class,
            'conditions' => JsonArray::class,
            'settings' => JsonArray::class,
            'published_settings' => JsonArray::class,
            'priority' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<SiteLayoutRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SiteLayoutRevision::class)->latest('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->published_blocks !== null && $this->status !== self::OFF;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->status === self::DRAFT;
    }
}

<?php

namespace App\Verso\Site\Models;

use App\Models\User;
use App\Verso\Site\Parts\SitePartStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The edited copy of one theme part. "blocks" is the working copy, "published_blocks" is what visitors see.
 *
 * @property int $id
 * @property string $theme
 * @property string $part
 * @property array<int, mixed> $blocks
 * @property array<int, mixed>|null $published_blocks
 * @property SitePartStatus $status
 * @property int|null $user_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['theme', 'part', 'blocks', 'published_blocks', 'status', 'user_id', 'published_at'])]
class SitePart extends Model
{
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_blocks' => 'array',
            'status' => SitePartStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<SitePartRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SitePartRevision::class)->latest('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // True when visitors see the blocks of this row instead of the theme file.
    public function isLive(): bool
    {
        return $this->published_blocks !== null;
    }

    // True when the working copy has changes that are not published yet.
    public function hasUnpublishedChanges(): bool
    {
        return $this->status === SitePartStatus::Draft;
    }
}

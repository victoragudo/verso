<?php

namespace App\Verso\Layouts\Models;

use App\Models\User;
use App\Verso\Core\Database\JsonArray;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A published version of a layout, kept to go back to.
 *
 * @property int $id
 * @property int $site_layout_id
 * @property array<int, mixed> $blocks
 * @property array<int, mixed> $conditions
 * @property array<string, mixed> $settings
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['blocks', 'conditions', 'settings', 'user_id'])]
class SiteLayoutRevision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['blocks' => JsonArray::class, 'conditions' => JsonArray::class, 'settings' => JsonArray::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

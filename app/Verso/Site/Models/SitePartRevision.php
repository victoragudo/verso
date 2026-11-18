<?php

namespace App\Verso\Site\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A copy of the blocks of a part at the time it was published.
 *
 * @property int $id
 * @property int $site_part_id
 * @property array<int, mixed> $blocks
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['site_part_id', 'blocks', 'user_id'])]
class SitePartRevision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    /** @return BelongsTo<SitePart, $this> */
    public function sitePart(): BelongsTo
    {
        return $this->belongsTo(SitePart::class);
    }
}

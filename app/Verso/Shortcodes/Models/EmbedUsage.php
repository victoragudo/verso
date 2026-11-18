<?php

namespace App\Verso\Shortcodes\Models;

use App\Verso\Content\Models\Entry;
use App\Verso\Shortcodes\Enums\UsageKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One row of the usage index: entry X embeds item "key" of block "name".
/**
 * @property int $id
 * @property int $entry_id
 * @property UsageKind $kind
 * @property string $name
 * @property string|null $key
 */
#[Fillable(['entry_id', 'kind', 'name', 'key'])]
class EmbedUsage extends Model
{
    protected function casts(): array
    {
        return ['kind' => UsageKind::class];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}

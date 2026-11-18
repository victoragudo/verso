<?php

namespace App\Verso\Seo\Models;

use App\Verso\Content\Models\Entry;
use App\Verso\Seo\Links\LinkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One internal link found in the content of a published entry by the link check.
 *
 * @property int $id
 * @property int $source_entry_id
 * @property string $path
 * @property int|null $target_entry_id
 * @property LinkStatus $status
 * @property-read Entry|null $source
 */
#[Table('seo_links')]
#[Fillable(['source_entry_id', 'path', 'target_entry_id', 'status'])]
class SeoLink extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_entry_id' => 'integer',
            'target_entry_id' => 'integer',
            'status' => LinkStatus::class,
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'source_entry_id');
    }
}

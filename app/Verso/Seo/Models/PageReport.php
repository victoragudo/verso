<?php

namespace App\Verso\Seo\Models;

use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What the link check found on one published entry: link counts and how long its content took to build.
 *
 * @property int $entry_id
 * @property int $render_ms
 * @property int $internal_links
 * @property int $external_links
 * @property int $broken_links
 * @property Carbon|null $checked_at
 * @property-read Entry|null $entry
 */
#[Table('seo_page_reports')]
#[Fillable(['entry_id', 'render_ms', 'internal_links', 'external_links', 'broken_links', 'checked_at'])]
class PageReport extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'entry_id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_id' => 'integer',
            'render_ms' => 'integer',
            'internal_links' => 'integer',
            'external_links' => 'integer',
            'broken_links' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}

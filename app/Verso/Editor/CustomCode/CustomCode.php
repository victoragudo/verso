<?php

namespace App\Verso\Editor\CustomCode;

use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Custom CSS and JavaScript of the whole site (no entry) or of one entry.
 *
 * @property int $id
 * @property int|null $entry_id
 * @property string|null $css
 * @property string|null $js
 * @property int|null $user_id
 * @property Carbon|null $updated_at
 */
class CustomCode extends Model
{
    protected $table = 'custom_code';

    protected $fillable = ['entry_id', 'css', 'js', 'user_id'];

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    // Short hash of the JavaScript, added to its URL so browsers load a new file after a change.
    public function version(): string
    {
        return substr(hash('xxh128', (string) $this->js), 0, 12);
    }
}

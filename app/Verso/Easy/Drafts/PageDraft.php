<?php

namespace App\Verso\Easy\Drafts;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Unsaved blocks of one page in the visual page editor, kept for one person. Visitors never see them.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $user_id
 * @property list<array<string, mixed>> $blocks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['blocks'])]
class PageDraft extends Model
{
    protected $table = 'easy_page_drafts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

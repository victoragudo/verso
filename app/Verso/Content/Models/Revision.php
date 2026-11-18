<?php

namespace App\Verso\Content\Models;

use App\Models\User;
use Database\Factories\RevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved copy of an entry at one moment.
 *
 * @property int $id
 * @property int $entry_id
 * @property int|null $user_id
 * @property string $title
 * @property list<array<string, mixed>>|null $blocks
 * @property string|null $excerpt
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entry $entry
 * @property-read User|null $user
 */
#[Fillable(['title', 'blocks', 'excerpt', 'meta'])]
#[UseFactory(RevisionFactory::class)]
class Revision extends Model
{
    /** @use HasFactory<RevisionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'meta' => 'array',
        ];
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

    // Save the content of an entry as a new revision. Pass $snapshot to save an older state.
    /** @param array{title: string, blocks: list<array<string, mixed>>|null, excerpt: string|null, meta: array<string, mixed>|null}|null $snapshot */
    public static function capture(Entry $entry, ?User $user = null, ?array $snapshot = null): self
    {
        $revision = new self($snapshot ?? $entry->snapshot());
        $revision->entry()->associate($entry);
        $revision->user()->associate($user);
        $revision->save();

        return $revision;
    }

    // Copy this revision back onto its entry (not saved yet).
    public function applyTo(Entry $entry): Entry
    {
        $entry->fill([
            'title' => $this->title,
            'blocks' => $this->blocks,
            'excerpt' => $this->excerpt,
            'meta' => $this->meta,
        ]);

        return $entry;
    }
}

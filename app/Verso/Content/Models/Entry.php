<?php

namespace App\Verso\Content\Models;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Policies\EntryPolicy;
use App\Verso\Core\Database\JsonArray;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Permalinks\Permalinks;
use Carbon\CarbonInterface;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

/**
 * A piece of content: a post, a page or any other content type.
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property EntryStatus $status
 * @property list<array<string, mixed>>|null $blocks
 * @property string|null $excerpt
 * @property int|null $author_id
 * @property int|null $parent_id
 * @property int $menu_order
 * @property Carbon|null $published_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $author
 * @property-read Entry|null $parent
 * @property-read Collection<int, Entry> $children
 * @property-read Collection<int, Revision> $revisions
 * @property-read Collection<int, Term> $terms
 */
#[Fillable(['type', 'title', 'slug', 'blocks', 'excerpt', 'parent_id', 'menu_order', 'meta'])]
#[UseFactory(EntryFactory::class)]
#[UsePolicy(EntryPolicy::class)]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory, SoftDeletes;

    // Stops endless loops when walking up a broken parent chain.
    protected const MAX_DEPTH = 32;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'menu_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'blocks' => JsonArray::class,
            'meta' => JsonArray::class,
            'menu_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Entry, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'parent_id');
    }

    /** @return HasMany<Entry, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Entry::class, 'parent_id')->orderBy('menu_order')->orderBy('title');
    }

    /** @return HasMany<Revision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class)->latest('id');
    }

    /** @return BelongsToMany<Term, $this> */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'entry_term');
    }

    // Entries that are live on the site now.
    /** @param Builder<Entry> $query */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', EntryStatus::Published)
            ->where('published_at', '<=', now());
    }

    // Scheduled entries whose publish date has passed.
    /** @param Builder<Entry> $query */
    #[Scope]
    protected function dueForPublishing(Builder $query): void
    {
        $query->where('status', EntryStatus::Scheduled)
            ->where('published_at', '<=', now());
    }

    /** @param Builder<Entry> $query */
    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    // Make the entry live. The publish date stays when it is already in the past.
    public function publish(?CarbonInterface $at = null): static
    {
        $this->ensureNotTrashed();

        if ($at !== null && $at->isFuture()) {
            throw new InvalidArgumentException('Use schedule() to publish an entry in the future.');
        }

        $this->status = EntryStatus::Published;
        $this->published_at = Carbon::instance($at ?? $this->pastPublishDate() ?? now());
        $this->save();

        return $this;
    }

    // Plan the entry to go live at a future date.
    public function schedule(CarbonInterface $at): static
    {
        $this->ensureNotTrashed();

        if (! $at->isFuture()) {
            throw new InvalidArgumentException('A scheduled date must be in the future.');
        }

        $this->status = EntryStatus::Scheduled;
        $this->published_at = Carbon::instance($at);
        $this->save();

        return $this;
    }

    // Take the entry off the site and make it a draft again.
    public function unpublish(): static
    {
        $this->ensureNotTrashed();

        $this->status = EntryStatus::Draft;
        $this->save();

        return $this;
    }

    // Mark the entry as trash and soft delete it.
    public function moveToTrash(): static
    {
        $this->status = EntryStatus::Trash;
        $this->save();
        $this->delete();

        return $this;
    }

    // Bring the entry back from the trash as a draft.
    public function restoreFromTrash(): static
    {
        $this->restore();
        $this->status = EntryStatus::Draft;
        $this->save();

        return $this;
    }

    // Words in the text of all blocks and their children, like the count a writer sees.
    public function wordCount(): int
    {
        $text = implode(' ', $this->blockTexts($this->blocks ?? []));
        $plain = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5);

        return (int) preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’_-]*/u', $plain);
    }

    /**
     * The "content" text of the blocks, children included.
     *
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    protected function blockTexts(array $blocks): array
    {
        $texts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (is_string($block['content'] ?? null)) {
                $texts[] = $block['content'];
            }

            if (is_array($block['children'] ?? null)) {
                array_push($texts, ...$this->blockTexts($block['children']));
            }
        }

        return $texts;
    }

    public function isPublished(): bool
    {
        return $this->status === EntryStatus::Published
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    public function isScheduled(): bool
    {
        return $this->status === EntryStatus::Scheduled;
    }

    public function isDraft(): bool
    {
        return $this->status === EntryStatus::Draft;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->author_id !== null && $this->author_id === $user->id;
    }

    public function contentType(): ContentType
    {
        return app(ContentTypeRegistry::class)->get($this->type);
    }

    // True when this entry is the front page of the site.
    public function isFrontPage(): bool
    {
        return $this->exists && (int) app(Settings::class)->get('reading.front_page') === $this->id;
    }

    /**
     * Parents from the top down, without this entry.
     *
     * @return list<Entry>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $seen = [$this->id => true];
        $current = $this->loadedParent();

        while ($current !== null && ! isset($seen[$current->id]) && count($ancestors) < self::MAX_DEPTH) {
            $seen[$current->id] = true;
            array_unshift($ancestors, $current);
            $current = $current->loadedParent();
        }

        return $ancestors;
    }

    // The parent entry. It skips the query when there is no parent and keeps the result for later calls.
    public function loadedParent(): ?Entry
    {
        if ($this->parent_id === null) {
            return null;
        }

        if (! $this->relationLoaded('parent')) {
            $this->setRelation('parent', $this->parent()->first());
        }

        return $this->getRelation('parent');
    }

    // Slugs of the parents and this entry, like "about/team".
    public function slugPath(): string
    {
        $slugs = array_map(fn (Entry $entry): string => $entry->slug, $this->ancestors());
        $slugs[] = $this->slug;

        return implode('/', $slugs);
    }

    // The public path from the permalink settings, like "/blog/my-post". The front page is "/".
    public function path(): string
    {
        return app(Permalinks::class)->entryPath($this);
    }

    public function url(): string
    {
        return url($this->path());
    }

    // True when $parent can be the parent of this entry (same hierarchical type, no loops).
    public function canHaveParent(?Entry $parent): bool
    {
        if ($parent === null) {
            return true;
        }

        if (! $this->contentType()->hierarchical || $parent->type !== $this->type) {
            return false;
        }

        if ($this->exists && $parent->id === $this->id) {
            return false;
        }

        foreach ($parent->ancestors() as $ancestor) {
            if ($this->exists && $ancestor->id === $this->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * The terms of one taxonomy.
     *
     * @return Collection<int, Term>
     */
    public function termsIn(string $taxonomy): Collection
    {
        return $this->terms->where('taxonomy', $taxonomy)->values();
    }

    /**
     * The fields saved in a revision.
     *
     * @return array{title: string, blocks: list<array<string, mixed>>|null, excerpt: string|null, meta: array<string, mixed>|null}
     */
    public function snapshot(): array
    {
        return [
            'title' => $this->title,
            'blocks' => $this->blocks,
            'excerpt' => $this->excerpt,
            'meta' => $this->meta,
        ];
    }

    // Delete old revisions and keep only the newest $keep ones.
    public function pruneRevisions(int $keep): int
    {
        $keepIds = $this->revisions()->limit(max($keep, 0))->pluck('id');

        return $this->revisions()->getQuery()->whereNotIn('id', $keepIds)->delete();
    }

    protected function pastPublishDate(): ?CarbonInterface
    {
        if ($this->published_at === null || $this->published_at->isFuture()) {
            return null;
        }

        return $this->published_at;
    }

    protected function ensureNotTrashed(): void
    {
        if ($this->trashed()) {
            throw new LogicException('Restore the entry from the trash first.');
        }
    }
}

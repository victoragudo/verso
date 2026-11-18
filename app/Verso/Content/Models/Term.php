<?php

namespace App\Verso\Content\Models;

use App\Verso\Content\Policies\TermPolicy;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Site\Permalinks\Permalinks;
use Database\Factories\TermFactory;
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
use Illuminate\Support\Carbon;

/**
 * A category, tag or other taxonomy term.
 *
 * @property int $id
 * @property string $taxonomy
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Term|null $parent
 * @property-read Collection<int, Term> $children
 * @property-read Collection<int, Entry> $entries
 */
#[Fillable(['taxonomy', 'name', 'slug', 'parent_id', 'description'])]
#[UseFactory(TermFactory::class)]
#[UsePolicy(TermPolicy::class)]
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory;

    /** @return BelongsTo<Term, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    /** @return HasMany<Term, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id')->orderBy('name');
    }

    /** @return BelongsToMany<Entry, $this> */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_term');
    }

    /** @param Builder<Term> $query */
    #[Scope]
    protected function inTaxonomy(Builder $query, string $taxonomy): void
    {
        $query->where('taxonomy', $taxonomy);
    }

    public function taxonomyDefinition(): Taxonomy
    {
        return app(TaxonomyRegistry::class)->get($this->taxonomy);
    }

    // The public path from the permalink settings, like "/category/travel".
    public function path(): string
    {
        return app(Permalinks::class)->termPath($this);
    }

    public function url(): string
    {
        return url($this->path());
    }

    // True when $parent can be the parent of this term (same hierarchical taxonomy, no loops).
    public function canHaveParent(?Term $parent): bool
    {
        if ($parent === null) {
            return true;
        }

        if (! $this->taxonomyDefinition()->hierarchical || $parent->taxonomy !== $this->taxonomy) {
            return false;
        }

        $seen = [];
        $current = $parent;

        while ($current !== null && ! isset($seen[$current->id])) {
            if ($this->exists && $current->id === $this->id) {
                return false;
            }

            $seen[$current->id] = true;
            $current = $current->parent;
        }

        return true;
    }
}

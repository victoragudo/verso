<?php

namespace App\Verso\Content\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

// Builds URL slugs that are unique where they need to be.
class SlugGenerator
{
    /**
     * @param  list<string>  $reserved
     */
    public function __construct(
        protected int $maxLength = 190,
        protected array $reserved = [],
        protected string $fallback = 'untitled',
    ) {}

    // Turn any text into a clean slug, like "Café con leche!" into "cafe-con-leche".
    public function slugify(string $text): string
    {
        $slug = Str::slug($text);
        $slug = trim(Str::limit($slug, $this->maxLength, ''), '-');

        return $slug === '' ? $this->fallback : $slug;
    }

    // A slug unique among entries of the same type and parent (trashed ones count too).
    public function forEntry(string $type, string $text, ?int $parentId = null, ?int $ignoreId = null): string
    {
        $base = $this->slugify($text);

        if ($parentId === null && in_array($base, $this->reserved, true)) {
            $base = $this->slugify($base.'-'.$type);
        }

        return $this->unique($base, function (string $slug) use ($type, $parentId, $ignoreId): bool {
            return Entry::withTrashed()
                ->where('type', $type)
                ->where('slug', $slug)
                ->when($parentId === null, fn (Builder $query) => $query->whereNull('parent_id'))
                ->when($parentId !== null, fn (Builder $query) => $query->where('parent_id', $parentId))
                ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->exists();
        });
    }

    // A slug unique inside a taxonomy.
    public function forTerm(string $taxonomy, string $text, ?int $ignoreId = null): string
    {
        $base = $this->slugify($text);

        return $this->unique($base, fn (string $slug): bool => Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists());
    }

    // Add "-2", "-3" and so on until the slug is free.
    /** @param callable(string): bool $taken */
    protected function unique(string $base, callable $taken): string
    {
        $slug = $base;
        $suffix = 2;

        while ($taken($slug)) {
            $ending = '-'.$suffix++;
            $slug = rtrim(Str::limit($base, $this->maxLength - strlen($ending), ''), '-').$ending;
        }

        return $slug;
    }
}

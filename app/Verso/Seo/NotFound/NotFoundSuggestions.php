<?php

namespace App\Verso\Seo\NotFound;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Database\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

// Finds published pages whose address looks like a path that was not found, like "/blog/tram-28" for "/tram28".
class NotFoundSuggestions
{
    // Words shorter than this are not used to search.
    protected const MIN_WORD_LENGTH = 3;

    public function __construct(
        protected int $limit,
        protected int $candidates,
        protected int $minSimilarity,
    ) {}

    /**
     * The closest live pages, best first.
     *
     * @return list<array{path: string, title: string, similarity: int}>
     */
    public function for(string $path): array
    {
        return $this->rank($path, $this->candidatesFor($path));
    }

    /**
     * Suggestions for many paths with one shared list of candidates.
     *
     * @param  list<string>  $paths
     * @return array<string, list<array{path: string, title: string, similarity: int}>>
     */
    public function forMany(array $paths): array
    {
        $words = array_values(array_unique(array_merge(...array_map($this->words(...), $paths ?: ['']))));
        $candidates = $this->query($words)->get();

        $result = [];
        foreach ($paths as $path) {
            $result[$path] = $this->rank($path, $candidates);
        }

        return $result;
    }

    /**
     * How alike two paths are, from 0 to 100. The last part counts most.
     */
    public function similarity(string $missing, string $existing): int
    {
        $missing = $this->clean($missing);
        $existing = $this->clean($existing);

        if ($missing === '' || $existing === '') {
            return 0;
        }

        similar_text($this->lastPart($missing), $this->lastPart($existing), $lastPercent);
        similar_text($missing, $existing, $fullPercent);

        return (int) round(max($lastPercent, $fullPercent * 0.9));
    }

    /**
     * @return Collection<int, Entry>
     */
    protected function candidatesFor(string $path): Collection
    {
        return $this->query($this->words($path))->get();
    }

    /**
     * Published entries that share a word with the path, or the newest ones when no word matches.
     *
     * @param  list<string>  $words
     * @return Builder<Entry>
     */
    protected function query(array $words): Builder
    {
        $query = Entry::query()->published()
            ->with(['parent', 'terms'])
            ->latest('published_at')->orderByDesc('id')
            ->limit(max(1, $this->candidates));

        if ($words === []) {
            return $query;
        }

        return $query->where(function (Builder $group) use ($words): void {
            foreach ($words as $word) {
                $group->orWhere(fn (Builder $one) => TextSearch::apply($one, ['slug'], $word));
            }
        });
    }

    /**
     * @param  Collection<int, Entry>  $candidates
     * @return list<array{path: string, title: string, similarity: int}>
     */
    protected function rank(string $path, Collection $candidates): array
    {
        $ranked = [];

        foreach ($candidates as $entry) {
            $entryPath = $entry->path();
            $score = $this->similarity($path, $entryPath);

            if ($score >= $this->minSimilarity && $this->clean($entryPath) !== $this->clean($path)) {
                $ranked[] = ['path' => $entryPath, 'title' => $entry->title, 'similarity' => $score];
            }
        }

        usort($ranked, fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity'] ?: strcmp($a['path'], $b['path']));

        return array_slice($ranked, 0, max(0, $this->limit));
    }

    /**
     * The words of the last part of a path, longest first.
     *
     * @return list<string>
     */
    protected function words(string $path): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $this->lastPart($this->clean($path))) ?: [];
        $words = array_values(array_unique(array_filter($parts, fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH)));

        usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($words, 0, 3);
    }

    protected function clean(string $path): string
    {
        $path = (string) preg_replace('/[?#].*$/su', '', $path);
        $path = preg_replace('/\.(html?|php|aspx?)$/i', '', trim($path, '/')) ?? '';

        return Str::lower(Str::ascii($path));
    }

    protected function lastPart(string $path): string
    {
        $parts = explode('/', $path);

        return (string) end($parts);
    }
}

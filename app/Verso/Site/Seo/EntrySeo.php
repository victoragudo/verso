<?php

namespace App\Verso\Site\Seo;

use App\Verso\Content\Models\Entry;

// The SEO fields of one entry, read from entries.meta.seo and cleaned.
final readonly class EntrySeo
{
    public const META_KEY = 'seo';

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public bool $noindex = false,
        public ?string $ogImage = null,
        public bool $sitemapExclude = false,
        public ?string $focusKeyphrase = null,
        public bool $nofollow = false,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?int $score = null,
    ) {}

    public static function fromEntry(Entry $entry): self
    {
        $seo = $entry->meta[self::META_KEY] ?? [];

        return self::fromArray(is_array($seo) ? $seo : []);
    }

    /**
     * Build from raw form or JSON data. Bad values become empty.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::text($data['title'] ?? null, (int) config('verso.seo.title_max_length')),
            description: self::text($data['description'] ?? null, (int) config('verso.seo.description_max_length')),
            canonical: self::link($data['canonical'] ?? null),
            noindex: filter_var($data['noindex'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ogImage: self::link($data['og_image'] ?? null),
            sitemapExclude: filter_var($data['sitemap_exclude'] ?? false, FILTER_VALIDATE_BOOLEAN),
            focusKeyphrase: self::text($data['focus_keyphrase'] ?? null, (int) config('verso.seo.keyphrase_max_length')),
            nofollow: filter_var($data['nofollow'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ogTitle: self::text($data['og_title'] ?? null, (int) config('verso.seo.title_max_length')),
            ogDescription: self::text($data['og_description'] ?? null, (int) config('verso.seo.description_max_length')),
            score: self::score($data['score'] ?? null),
        );
    }

    /**
     * The shape stored in entries.meta.seo.
     *
     * @return array{title: string|null, description: string|null, canonical: string|null, noindex: bool, og_image: string|null, sitemap_exclude: bool, focus_keyphrase: string|null, nofollow: bool, og_title: string|null, og_description: string|null, score: int|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'noindex' => $this->noindex,
            'og_image' => $this->ogImage,
            'sitemap_exclude' => $this->sitemapExclude,
            'focus_keyphrase' => $this->focusKeyphrase,
            'nofollow' => $this->nofollow,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'score' => $this->score,
        ];
    }

    // "good", "ok" or "poor" from the score of the editor checks, or null when the entry was never checked.
    public function rating(): ?string
    {
        if ($this->score === null) {
            return null;
        }

        return match (true) {
            $this->score >= (int) config('verso.seo.analysis.good_score') => 'good',
            $this->score >= (int) config('verso.seo.analysis.ok_score') => 'ok',
            default => 'poor',
        };
    }

    // The robots meta value of this entry, or null when search engines may index and follow it.
    public function robots(): ?string
    {
        return match (true) {
            $this->noindex && $this->nofollow => 'noindex, nofollow',
            $this->noindex => 'noindex, follow',
            $this->nofollow => 'index, nofollow',
            default => null,
        };
    }

    // The score the editor sent, from 0 to 100.
    protected static function score(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, min(100, (int) round((float) $value)));
    }

    // Plain text on one line, cut to the max length.
    protected static function text(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return $value === '' ? null : mb_substr($value, 0, max($maxLength, 1));
    }

    // Only http(s) URLs and local paths are kept, so "javascript:" links never reach a page.
    protected static function link(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        if (preg_match('#^https?://[^\s]+$#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $value;
        }

        return null;
    }
}

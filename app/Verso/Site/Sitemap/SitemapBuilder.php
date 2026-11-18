<?php

namespace App\Verso\Site\Sitemap;

use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Filters\SitemapEntriesFilter;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Site\Seo\EntrySeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use XMLWriter;

// Builds the sitemap index, one sitemap per content type and one per taxonomy (split in pages when big).
class SitemapBuilder
{
    public const XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    // File names of taxonomy sitemaps start with this, like "sitemap-taxonomy-category.xml".
    public const TAXONOMY_PREFIX = 'taxonomy-';

    public const IMAGE_XMLNS = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function __construct(
        protected ContentTypeRegistry $types,
        protected SiteTime $time,
        protected int $maxUrls,
        /** @var list<string> */
        protected array $excludedTypes = [],
        protected ?TaxonomyRegistry $taxonomies = null,
        /** @var list<string> */
        protected array $excludedTaxonomies = [],
        /** @var list<int> */
        protected array $excludedEntries = [],
        protected bool $includeImages = false,
        protected string $lastModified = 'updated',
    ) {}

    // The /sitemap.xml file: a list of the sitemap files of every type with public entries.
    public function index(): string
    {
        $xml = $this->startDocument('sitemapindex');

        foreach ($this->types() as $type) {
            $query = $this->entries($type);
            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $lastModified = (clone $query)->max($this->dateColumn());

            $this->writeIndexFiles($xml, $type->name, $count, $lastModified);
        }

        foreach ($this->taxonomies() as $taxonomy) {
            $query = $this->terms($taxonomy);
            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $lastModified = $this->publishedEntriesIn($taxonomy)->max('entries.updated_at');

            $this->writeIndexFiles($xml, self::TAXONOMY_PREFIX.$taxonomy->name, $count, $lastModified);
        }

        return $this->endDocument($xml);
    }

    /**
     * How many URLs each sitemap holds, for the admin status. Keys are the sitemap names.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->types() as $type) {
            $counts[$type->name] = $this->entries($type)->count();
        }

        foreach ($this->taxonomies() as $taxonomy) {
            $counts[self::TAXONOMY_PREFIX.$taxonomy->name] = $this->terms($taxonomy)->count();
        }

        return $counts;
    }

    // One sitemap file of term archives, or null when the taxonomy or page does not exist.
    public function forTaxonomy(string $taxonomyName, int $page = 1): ?string
    {
        $taxonomy = $this->taxonomies()[$taxonomyName] ?? null;

        if ($taxonomy === null || $page < 1) {
            return null;
        }

        $terms = $this->terms($taxonomy)
            ->withMax(['entries as last_entry_update' => $this->onlyPublished(...)], 'updated_at')
            ->orderBy('id')
            ->forPage($page, $this->maxUrls())
            ->get();

        if ($terms->isEmpty() && $page > 1) {
            return null;
        }

        $urls = [];

        foreach ($terms as $term) {
            $lastModified = $term->getAttribute('last_entry_update');
            $urls[] = new SitemapUrl(
                $term->url(),
                $lastModified === null ? null : $this->time->iso(Carbon::parse((string) $lastModified)),
            );
        }

        return $this->urlSet(self::TAXONOMY_PREFIX.$taxonomy->name, $page, $urls);
    }

    // The taxonomy name in a file name like "taxonomy-category" or "taxonomy-tag-2", or null for other files.
    public function taxonomyFromFileName(string $name): ?string
    {
        return str_starts_with($name, self::TAXONOMY_PREFIX) ? substr($name, strlen(self::TAXONOMY_PREFIX)) : null;
    }

    /**
     * Taxonomies with a public archive route that appear in the sitemap, by name.
     *
     * @return array<string, Taxonomy>
     */
    public function taxonomies(): array
    {
        $taxonomies = [];

        foreach ($this->taxonomies?->all() ?? [] as $taxonomy) {
            if (app(Permalinks::class)->taxonomyBase($taxonomy) !== '' && ! in_array($taxonomy->name, $this->excludedTaxonomies, true)) {
                $taxonomies[$taxonomy->name] = $taxonomy;
            }
        }

        return $taxonomies;
    }

    // Add the index rows for one sitemap, one per page.
    protected function writeIndexFiles(XMLWriter $xml, string $name, int $count, mixed $lastModified): void
    {
        $pages = (int) ceil($count / $this->maxUrls());

        for ($page = 1; $page <= $pages; $page++) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', url('/'.$this->fileName($name, $page)));
            if ($lastModified !== null) {
                $xml->writeElement('lastmod', (string) $this->time->iso(Carbon::parse((string) $lastModified)));
            }
            $xml->endElement();
        }
    }

    // Terms of a taxonomy that hold at least one published entry.
    /** @return Builder<Term> */
    protected function terms(Taxonomy $taxonomy): Builder
    {
        return Term::query()
            ->inTaxonomy($taxonomy->name)
            ->whereHas('entries', $this->onlyPublished(...));
    }

    /** @param Builder<Entry> $query */
    protected function onlyPublished(Builder $query): void
    {
        $query->published();
    }

    // Published entries linked to any term of a taxonomy.
    /** @return Builder<Entry> */
    protected function publishedEntriesIn(Taxonomy $taxonomy): Builder
    {
        return Entry::query()
            ->published()
            ->whereHas('terms', fn (Builder $query) => $query->where('taxonomy', $taxonomy->name));
    }

    // One sitemap file, or null when the type or page does not exist.
    public function forType(string $typeName, int $page = 1): ?string
    {
        $type = $this->types()[$typeName] ?? null;

        if ($type === null || $page < 1) {
            return null;
        }

        $entries = $this->entries($type)
            ->with('parent')
            ->orderBy('id')
            ->forPage($page, $this->maxUrls())
            ->get();

        if ($entries->isEmpty() && $page > 1) {
            return null;
        }

        $urls = [];

        $images = $this->includeImages ? $this->featuredImages($entries->all()) : [];

        foreach ($entries as $entry) {
            $date = $this->lastModified === 'published' ? $entry->published_at : $entry->updated_at;
            $urls[] = new SitemapUrl($entry->url(), $date === null ? null : $this->time->iso($date), $images[$entry->id] ?? []);
        }

        return $this->urlSet($type->name, $page, $urls);
    }

    /**
     * Writes a <urlset> file after the SitemapEntriesFilter.
     *
     * @param  list<SitemapUrl>  $urls
     */
    protected function urlSet(string $name, int $page, array $urls): string
    {
        $urls = SitemapEntriesFilter::apply($urls, $name, $page);
        $withImages = array_filter($urls, fn (SitemapUrl $url): bool => $url->images !== []) !== [];
        $xml = $this->startDocument('urlset', $withImages);

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url->location);
            if ($url->lastModified !== null) {
                $xml->writeElement('lastmod', $url->lastModified);
            }
            foreach ($url->images as $image) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', $image);
                $xml->endElement();
            }
            $xml->endElement();
        }

        return $this->endDocument($xml);
    }

    /**
     * Split a file name like "post" or "post-2" into a name and a page number. $known lists names that may end in a number.
     *
     * @param  list<string>|null  $known  defaults to the content type names
     * @return array{0: string, 1: int}
     */
    public function parseFileName(string $name, ?array $known = null): array
    {
        if (in_array($name, $known ?? array_keys($this->types()), true)) {
            return [$name, 1];
        }

        if (preg_match('/^(.+)-(\d+)$/', $name, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$name, 1];
    }

    // The public file name, like "sitemap-post.xml" or "sitemap-post-2.xml".
    public function fileName(string $type, int $page = 1): string
    {
        return 'sitemap-'.$type.($page > 1 ? '-'.$page : '').'.xml';
    }

    /**
     * Content types that appear in the sitemap, by name.
     *
     * @return array<string, ContentType>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->types->all() as $type) {
            if (! in_array($type->name, $this->excludedTypes, true)) {
                $types[$type->name] = $type;
            }
        }

        return $types;
    }

    // Published entries of a type, without those hidden from search engines or left out of the sitemap.
    /** @return Builder<Entry> */
    protected function entries(ContentType $type): Builder
    {
        $noindex = 'meta->'.EntrySeo::META_KEY.'->noindex';
        $excluded = 'meta->'.EntrySeo::META_KEY.'->sitemap_exclude';

        return Entry::query()
            ->ofType($type->name)
            ->published()
            ->when($this->excludedEntries !== [], fn (Builder $query) => $query->whereNotIn('id', $this->excludedEntries))
            ->where(fn (Builder $query) => $query->whereNull($noindex)->orWhere($noindex, false))
            ->where(fn (Builder $query) => $query->whereNull($excluded)->orWhere($excluded, false));
    }

    protected function dateColumn(): string
    {
        return $this->lastModified === 'published' ? 'published_at' : 'updated_at';
    }

    /**
     * Featured image URLs by entry id, loaded with one query.
     *
     * @param  array<int, Entry>  $entries
     * @return array<int, list<string>>
     */
    protected function featuredImages(array $entries): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            $featured = $entry->meta['featured_image'] ?? null;

            if (is_array($featured) && is_numeric($featured['mediaId'] ?? null)) {
                $ids[$entry->id] = (int) $featured['mediaId'];
            }
        }

        $media = $ids === [] ? collect() : Media::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');
        $images = [];

        foreach ($ids as $entryId => $mediaId) {
            $item = $media->get($mediaId);

            if ($item instanceof Media && $item->isImage()) {
                $images[$entryId] = [url($item->url())];
            }
        }

        return $images;
    }

    protected function maxUrls(): int
    {
        return max($this->maxUrls, 1);
    }

    protected function startDocument(string $root, bool $withImages = false): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);
        $xml->writeAttribute('xmlns', self::XMLNS);
        if ($withImages) {
            $xml->writeAttribute('xmlns:image', self::IMAGE_XMLNS);
        }

        return $xml;
    }

    protected function endDocument(XMLWriter $xml): string
    {
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}

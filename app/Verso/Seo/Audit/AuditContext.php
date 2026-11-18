<?php

namespace App\Verso\Seo\Audit;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Seo\Links\CrawlState;
use App\Verso\Site\Seo\EntrySeo;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Support\Str;

// Data shared by the checks of one audit run. Published entries are read once, in chunks.
class AuditContext
{
    /** @var list<EntrySnapshot>|null */
    protected ?array $entries = null;

    public function __construct(
        public readonly Settings $settings,
        public readonly SeoSettings $seo,
        public readonly CrawlState $crawl,
        protected ContentTypeRegistry $types,
        public readonly int $maxItems,
        protected int $chunk,
    ) {}

    /** @return list<EntrySnapshot> */
    public function entries(): array
    {
        return $this->entries ??= $this->readEntries();
    }

    /** @return list<EntrySnapshot> */
    public function indexableEntries(): array
    {
        return array_values(array_filter($this->entries(), fn (EntrySnapshot $entry): bool => $entry->isIndexable()));
    }

    public function siteName(): string
    {
        return (string) $this->settings->get('site.name');
    }

    public function tagline(): string
    {
        return trim((string) $this->settings->get('site.tagline'));
    }

    /** @return list<EntrySnapshot> */
    protected function readEntries(): array
    {
        $snapshots = [];
        $articleTypes = (array) config('verso.seo.article_types');
        $frontPage = (int) $this->settings->get('reading.front_page');
        $site = $this->siteName();
        $tagline = $this->tagline();

        Entry::query()->published()
            ->whereIn('type', $this->types->names())
            ->select(['id', 'type', 'title', 'slug', 'excerpt', 'meta', 'blocks', 'status', 'published_at'])
            ->orderBy('id')
            ->chunk(max(1, $this->chunk), function ($entries) use (&$snapshots, $articleTypes, $frontPage, $site, $tagline): void {
                foreach ($entries as $entry) {
                    /** @var Entry $entry */
                    $seo = EntrySeo::fromEntry($entry);
                    $type = $this->types->get($entry->type);
                    $excerpt = trim(strip_tags((string) $entry->excerpt));

                    $snapshots[] = new EntrySnapshot(
                        id: $entry->id,
                        type: $entry->type,
                        typeLabel: $type->label,
                        title: $entry->title,
                        seoTitle: $seo->title ?? $this->seo->title($entry->title, $site, $tagline === '' ? null : $tagline),
                        description: $seo->description ?? ($excerpt === '' ? null : Str::squish($excerpt)),
                        hasImage: $seo->ogImage !== null || is_array($entry->meta['featured_image'] ?? null) && ($entry->meta['featured_image']['mediaId'] ?? $entry->meta['featured_image']['src'] ?? null) !== null,
                        noindex: $seo->noindex,
                        isArticle: in_array($entry->type, $articleTypes, true),
                        isFrontPage: $entry->id === $frontPage,
                        imagesWithoutAlt: $this->imagesWithoutAlt(is_array($entry->blocks) ? $entry->blocks : []),
                        editUrl: route('admin.entries.edit', ['contentType' => $entry->type, 'entry' => $entry->id]),
                        rating: $seo->rating(),
                    );
                }
            });

        return $snapshots;
    }

    // Image blocks with no alt text of their own and no media library image to take it from.
    /** @param array<mixed> $blocks */
    protected function imagesWithoutAlt(array $blocks): int
    {
        $count = 0;

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if (($block['type'] ?? null) === 'image' && trim((string) ($attrs['alt'] ?? '')) === '' && empty($attrs['mediaId'])) {
                $count++;
            }

            $count += $this->imagesWithoutAlt(is_array($block['children'] ?? null) ? $block['children'] : []);
        }

        return $count;
    }
}

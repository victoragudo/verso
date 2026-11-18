<?php

namespace App\Verso\Importer\WordPress;

use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Importer\Content\ContentConverter;
use App\Verso\Importer\Content\ShortcodeMapper;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Support\SeoMetaMapper;
use App\Verso\Importer\Support\WordPressDates;
use App\Verso\Importer\Wxr\WxrItem;
use App\Verso\Site\Seo\EntrySeo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

// Creates or updates one Verso entry from a WordPress post, page or custom post.
class EntryImporter
{
    // How many entries to read at once when looking for earlier imports.
    protected const LOOKUP_CHUNK = 500;

    /**
     * @param  list<string>  $skipTypes
     * @param  list<string>  $skipStatuses
     */
    public function __construct(
        protected CreateEntry $createEntry,
        protected UpdateEntry $updateEntry,
        protected ContentTypeRegistry $types,
        protected ContentConverter $converter,
        protected SeoMetaMapper $seo,
        protected TermImporter $terms,
        protected WordPressDates $dates,
        protected array $skipTypes = [],
        protected array $skipStatuses = [],
        protected int $excerptMaxLength = 1000,
    ) {}

    // Load the WordPress ids of entries made by earlier imports, so they are updated, not copied.
    public function loadPreviousImports(ImportState $state): void
    {
        Entry::withTrashed()
            ->select(['id', 'meta'])
            ->whereNotNull('meta->wordpress->id')
            ->chunkById(self::LOOKUP_CHUNK, function ($entries) use ($state): void {
                foreach ($entries as $entry) {
                    $id = $entry->meta['wordpress']['id'] ?? null;

                    if (is_numeric($id)) {
                        $state->entries[(int) $id] = $entry->id;
                    }
                }
            });
    }

    public function shouldSkip(WxrItem $item): bool
    {
        return $item->isAttachment()
            || in_array($item->postType, $this->skipTypes, true)
            || in_array($item->status, $this->skipStatuses, true);
    }

    // The Verso content type: same name when Verso knows it, otherwise a post.
    public function typeFor(WxrItem $item): string
    {
        return $this->types->has($item->postType) ? $item->postType : 'post';
    }

    public function import(WxrItem $item, ImportState $state): ?Entry
    {
        $existingId = $state->entries[$item->id] ?? null;
        $existing = $existingId === null ? null : Entry::withTrashed()->find($existingId);

        if ($existing?->trashed()) {
            $state->report->add('entries', ImportReport::SKIPPED);
            $state->report->warn(__('":title" is in the trash, so it was not imported again.', ['title' => $item->title]));

            return null;
        }

        $type = $existing->type ?? $this->typeFor($item);
        $data = $this->data($item, $type, $state, $existing);

        if ($existing !== null) {
            $entry = $this->updateEntry->handle($existing, $data);
            $state->report->add('entries', ImportReport::UPDATED);
        } else {
            $entry = $this->createEntry->handle($state->authorFor($item->creator), $type, $data);
            $this->keepCreationDate($entry, $item);
            $state->report->add('entries', ImportReport::CREATED);
        }

        $state->entries[$item->id] = $entry->id;
        $this->rememberParent($entry, $item, $state);
        $state->imported[] = new ImportedEntry(
            entryId: $entry->id,
            wordpressId: $item->id,
            postType: $item->postType,
            link: $item->link,
            slug: $item->slug,
            localDate: $this->dates->parse('', $item->date) ?? $this->dates->parse($item->dateGmt),
        );

        return $entry;
    }

    /**
     * Converted blocks. Unknown shortcodes stay as text and are listed in the import summary.
     *
     * @return list<array<string, mixed>>
     */
    protected function blocks(WxrItem $item, ImportState $state): array
    {
        $blocks = $this->converter->convert($item->content, 'wordpress:'.$item->id, $state->media);
        $unknown = app(ShortcodeMapper::class)->unknown($blocks);

        if ($unknown !== []) {
            $list = implode(', ', array_map(fn (string $name): string => '['.$name.']', $unknown));
            $state->report->warn(__('":title" uses unknown shortcodes :shortcodes. They were kept as text.', ['title' => $item->title, 'shortcodes' => $list]));
        }

        return $blocks;
    }

    /**
     * @return array{title: string, slug: string|null, status: EntryStatus, published_at: CarbonImmutable|null, blocks: list<array<string, mixed>>, excerpt: string|null, menu_order: int, meta: array<string, mixed>, terms: array<string, list<int>>, parent_id?: int}
     */
    protected function data(WxrItem $item, string $type, ImportState $state, ?Entry $existing): array
    {
        $contentType = $this->types->get($type);
        $data = [
            'title' => $item->title,
            'slug' => $item->slug !== '' ? $item->slug : null,
            'status' => $this->status($item),
            'published_at' => $this->dates->parse($item->dateGmt, $item->status === 'draft' ? '' : $item->date),
            'blocks' => $this->blocks($item, $state),
            'excerpt' => $this->excerpt($item),
            'menu_order' => $item->menuOrder,
            'meta' => $this->meta($item, $state, $existing),
            'terms' => $this->terms->idsFor($item, $contentType->taxonomies, $state),
        ];

        $parentId = $state->entries[$item->parentId] ?? null;

        if ($contentType->hierarchical && $parentId !== null) {
            $data['parent_id'] = $parentId;
        }

        return $data;
    }

    // Published and future posts keep their status. Everything else becomes a draft.
    protected function status(WxrItem $item): EntryStatus
    {
        return in_array($item->status, ['publish', 'future'], true) ? EntryStatus::Published : EntryStatus::Draft;
    }

    protected function excerpt(WxrItem $item): ?string
    {
        $text = trim(html_entity_decode(strip_tags($item->excerpt), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : Str::limit($text, $this->excerptMaxLength, '');
    }

    /**
     * Keep the meta of an earlier import and replace the WordPress parts.
     *
     * @return array<string, mixed>
     */
    protected function meta(WxrItem $item, ImportState $state, ?Entry $existing): array
    {
        $meta = $existing->meta ?? [];
        $meta['wordpress'] = ['id' => (string) $item->id, 'type' => $item->postType, 'link' => $item->link];

        $seo = $this->seo->map($item, $item->title);

        if (is_string($seo['og_image'] ?? null)) {
            $seo['og_image'] = $state->media->byUrl($seo['og_image'])->url ?? $seo['og_image'];
        }

        if ($seo !== []) {
            $merged = [...(is_array($meta['seo'] ?? null) ? $meta['seo'] : []), ...$seo];
            $meta['seo'] = EntrySeo::fromArray($merged)->toArray();
        }

        $thumbnailId = (int) $item->metaValue('_thumbnail_id');

        if ($thumbnailId > 0) {
            $media = $state->media->byId($thumbnailId);

            if ($media !== null) {
                $meta['featured_image'] = ['mediaId' => $media->mediaId, 'src' => $media->url, 'alt' => $media->alt];
            } else {
                $state->report->warn(__('The featured image of ":title" (attachment :id) was not imported.', ['title' => $item->title, 'id' => $thumbnailId]));
            }
        }

        return $meta;
    }

    // Children can come before their parent in the file. Those are fixed at the end.
    protected function rememberParent(Entry $entry, WxrItem $item, ImportState $state): void
    {
        if ($item->parentId > 0 && $entry->contentType()->hierarchical && $entry->parent_id === null) {
            $state->pendingParents[$entry->id] = $item->parentId;
        }
    }

    protected function keepCreationDate(Entry $entry, WxrItem $item): void
    {
        $date = $this->dates->parse($item->dateGmt, $item->date);

        if ($date !== null) {
            Entry::query()->whereKey($entry->id)->update(['created_at' => $date]);
        }
    }
}

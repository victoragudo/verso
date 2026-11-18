<?php

namespace App\Verso\Media;

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaUsage;

// Finds the media of an image block and gives its responsive img attributes. One instance lives for one request.
class ResponsiveImages
{
    /** @var array<int, Media|null> */
    protected array $found = [];

    public function __construct(protected ImageUrl $urls, protected MediaUsage $usage) {}

    /**
     * Loads the media of many entries with one query, so lists do not run one query per image.
     *
     * @param  iterable<Entry>  $entries
     * @param  list<int>  $extraIds  more media to load in the same query, like an author photo
     */
    public function preloadEntries(iterable $entries, array $extraIds = []): void
    {
        $ids = $extraIds;

        foreach ($entries as $entry) {
            array_push($ids, ...$this->usage->mediaIds($entry));
        }

        $this->preload($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    public function preload(array $ids): void
    {
        $missing = array_values(array_diff(array_unique($ids), array_keys($this->found)));

        if ($missing === []) {
            return;
        }

        $media = Media::query()->whereKey($missing)->get()->keyBy('id');

        foreach ($missing as $id) {
            $this->found[$id] = $media->get($id);
        }
    }

    /**
     * Keeps media that a caller already loaded. Ids without a model are remembered as missing.
     *
     * @param  array<int, Media>  $media  media by id
     * @param  list<int>  $ids
     */
    public function remember(array $media, array $ids): void
    {
        foreach ($ids as $id) {
            $this->found[$id] = $media[$id] ?? null;
        }
    }

    // Forgets loaded media, for long running processes.
    public function forget(): void
    {
        $this->found = [];
    }

    /**
     * Attributes with a srcset for a resizable image, or null when the media is missing.
     *
     * @return array{src: string, srcset: string, sizes: string, width: int|null, height: int|null, alt: string}|null
     */
    public function attributes(mixed $mediaId, string $sizes): ?array
    {
        $media = $this->find($mediaId);

        if ($media === null || ! $media->isResizable()) {
            return null;
        }

        return $this->urls->attributes($media, $sizes);
    }

    // The media item of an id, loaded once per request. Themes use it for captions.
    public function media(mixed $mediaId): ?Media
    {
        return $this->find($mediaId);
    }

    // Loads each media once per request, so a gallery does not repeat queries for the same file.
    protected function find(mixed $mediaId): ?Media
    {
        $id = is_numeric($mediaId) ? (int) $mediaId : 0;

        if ($id < 1) {
            return null;
        }

        if (! array_key_exists($id, $this->found)) {
            $this->found[$id] = Media::query()->find($id);
        }

        return $this->found[$id];
    }
}

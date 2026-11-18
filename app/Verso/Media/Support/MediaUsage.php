<?php

namespace App\Verso\Media\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// Finds the entries that use a media file in their blocks or meta.
class MediaUsage
{
    /**
     * @param  list<string>  $metaKeys  entry meta keys (dot notation) that hold a media id
     */
    public function __construct(protected array $metaKeys = []) {}

    /**
     * Entries that use the file, trashed ones included.
     *
     * @return Collection<int, Entry>
     */
    public function entriesUsing(Media $media): Collection
    {
        $id = (string) $media->id;

        // A cheap text match first, then an exact check in PHP.
        return Entry::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($id): void {
                $query->where('blocks', 'like', '%'.$id.'%')->orWhere('meta', 'like', '%'.$id.'%');
            })
            ->orderBy('title')
            ->get()
            ->filter(fn (Entry $entry): bool => $this->entryUses($entry, $media->id))
            ->values()
            ->toBase();
    }

    public function isUsed(Media $media): bool
    {
        return $this->entriesUsing($media)->isNotEmpty();
    }

    public function entryUses(Entry $entry, int $mediaId): bool
    {
        if ($this->blocksUse($entry->blocks ?? [], $mediaId)) {
            return true;
        }

        foreach ($this->metaKeys as $key) {
            if ($this->sameId(data_get($entry->meta ?? [], $key), $mediaId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walks blocks and their children looking for the media id.
     *
     * @param  array<mixed>  $blocks
     */
    public function blocksUse(array $blocks, int $mediaId): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if ($this->sameId($attrs['mediaId'] ?? null, $mediaId)) {
                return true;
            }

            foreach ((array) ($attrs['mediaIds'] ?? []) as $id) {
                if ($this->sameId($id, $mediaId)) {
                    return true;
                }
            }

            if (is_array($block['children'] ?? null) && $this->blocksUse($block['children'], $mediaId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every media id an entry uses in its blocks and meta.
     *
     * @return list<int>
     */
    public function mediaIds(Entry $entry): array
    {
        $ids = $this->blockMediaIds($entry->blocks ?? []);

        foreach ($this->metaKeys as $key) {
            $ids[] = data_get($entry->meta ?? [], $key);
        }

        return $this->validIds($ids);
    }

    /**
     * Media ids in blocks and their children.
     *
     * @param  array<mixed>  $blocks
     * @return list<mixed>
     */
    protected function blockMediaIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            $ids[] = $attrs['mediaId'] ?? null;
            array_push($ids, ...array_values((array) ($attrs['mediaIds'] ?? [])));

            if (is_array($block['children'] ?? null)) {
                array_push($ids, ...$this->blockMediaIds($block['children']));
            }
        }

        return $ids;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<int>
     */
    protected function validIds(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }

    protected function sameId(mixed $value, int $mediaId): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value === $mediaId;
    }
}

<?php

namespace App\Verso\Media\Http\Presenters;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Collection;

// Turns media files into plain arrays for the admin pages and the picker.
class MediaPresenter
{
    public function __construct(protected int $thumbnailSize) {}

    /**
     * One media item, as the MediaItem type in verso.ts.
     *
     * @return array<string, mixed>
     */
    public function item(Media $media, ?User $user = null): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url(),
            'filename' => $media->filename,
            'path' => $media->path,
            'mime_type' => $media->mime_type,
            'type' => $media->type()->value,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'thumbnail_url' => $media->isResizable()
                ? $media->imageUrl(['w' => $this->thumbnailSize, 'h' => $this->thumbnailSize, 'fit' => 'crop'])
                : null,
            'uploaded_by' => $media->uploader?->name,
            'created_at' => $media->created_at?->toIso8601String(),
            'can' => [
                'update' => $user?->can('update', $media) ?? false,
                'delete' => $user?->can('delete', $media) ?? false,
            ],
        ];
    }

    /**
     * The item plus the entries that use it.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<string, mixed>
     */
    public function detail(Media $media, Collection $entries, ?User $user = null): array
    {
        return [
            ...$this->item($media, $user),
            'usage' => $entries->map(fn (Entry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'type' => $entry->type,
                'trashed' => $entry->trashed(),
                'edit_url' => route('admin.entries.edit', ['contentType' => $entry->type, 'entry' => $entry->id], false),
            ])->all(),
        ];
    }
}

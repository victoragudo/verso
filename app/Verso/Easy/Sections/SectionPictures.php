<?php

namespace App\Verso\Easy\Sections;

use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;

// Gives a new section from the "Add section" gallery the latest pictures of the media library, so it does not start empty.
class SectionPictures
{
    public function __construct(protected int $limit) {}

    // The content of a section with its defaults, and pictures where it has none.
    public function fill(SectionTemplate $section): SectionContent
    {
        $content = (new SectionContent)->withDefaults($section->defaults);
        $pictures = $this->latest();

        if ($pictures === []) {
            return $content;
        }

        $items = [];

        foreach ($content->items as $index => $item) {
            $items[] = is_array($item['image'] ?? null) ? $item : [...$item, 'image' => $pictures[$index % count($pictures)]];
        }

        return new SectionContent(
            heading: $content->heading,
            text: $content->text,
            items: $items,
            images: $content->images !== [] ? $content->images : $pictures,
            button: $content->button,
            extra: $content->extra,
        );
    }

    /**
     * Image attrs of the newest pictures.
     *
     * @return list<array{mediaId: int, src: string, alt: string, width: int, height: int}>
     */
    public function latest(): array
    {
        if ($this->limit < 1) {
            return [];
        }

        $media = Media::query()->ofType(MediaType::Image)->latest('id')->limit($this->limit)->get()->all();

        return array_values(array_map(fn (Media $media): array => [
            'mediaId' => (int) $media->id,
            'src' => $media->url(),
            'alt' => (string) $media->alt,
            'width' => (int) $media->width,
            'height' => (int) $media->height,
        ], $media));
    }
}

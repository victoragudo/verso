<?php

namespace Database\Seeders\Demo;

use App\Verso\Media\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Turns the short block lists of DemoPosts into real editor blocks.
class DemoBlocks
{
    /**
     * @param  Collection<string, Media>  $media  demo media by file name
     */
    public function __construct(protected Collection $media) {}

    /**
     * @param  list<list<mixed>>  $specs
     * @return list<array<string, mixed>>
     */
    public function build(array $specs): array
    {
        return array_values(array_filter(array_map($this->block(...), $specs)));
    }

    /**
     * @param  list<mixed>  $spec
     * @return array<string, mixed>|null
     */
    protected function block(array $spec): ?array
    {
        $kind = (string) $spec[0];

        return match ($kind) {
            'p' => $this->make('paragraph', [], '<p>'.$spec[1].'</p>'),
            'h2', 'h3' => $this->make('heading', ['level' => (int) substr($kind, 1)], e((string) $spec[1])),
            'ul', 'ol' => $this->make('list', ['ordered' => $kind === 'ol'], implode('', array_map(fn (string $item): string => '<li>'.$item.'</li>', (array) $spec[1]))),
            'quote' => $this->make('quote', ['citation' => (string) ($spec[2] ?? '')], '<p>'.e((string) $spec[1]).'</p>'),
            'code' => $this->make('code', ['language' => (string) $spec[1]], (string) $spec[2]),
            'table' => $this->make('table', ['header' => true, 'rows' => array_map(fn (array $row): array => array_map(fn (string $cell): string => e($cell), $row), (array) $spec[1])]),
            'gallery' => $this->gallery(array_values(array_map(strval(...), (array) $spec[1])), (string) ($spec[2] ?? '')),
            'image' => $this->image((string) $spec[1]),
            'embed' => $this->make('embed', ['url' => (string) $spec[1], 'caption' => (string) ($spec[2] ?? '')]),
            'button' => $this->make('button', ['label' => (string) $spec[1], 'url' => (string) $spec[2], 'style' => 'secondary']),
            'separator' => $this->make('separator'),
            'columns' => $this->make('columns', [], '', array_values(array_map(fn (array $column): array => $this->make('column', [], '', [
                $this->make('heading', ['level' => 3], e((string) $column[0])),
                $this->make('paragraph', [], '<p>'.e((string) $column[1]).'</p>'),
            ]), (array) $spec[1]))),
            default => null,
        };
    }

    /**
     * An image block that points to a demo media file.
     *
     * @return array<string, mixed>|null
     */
    public function image(string $name): ?array
    {
        $media = $this->media->get($name);

        return $media === null ? null : $this->make('image', $this->imageAttrs($media) + ['caption' => (string) $media->caption]);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, mixed>|null
     */
    protected function gallery(array $names, string $caption): ?array
    {
        $items = collect($names)->map(fn (string $name): ?Media => $this->media->get($name))->filter()->values();

        if ($items->isEmpty()) {
            return null;
        }

        return $this->make('gallery', [
            'mediaIds' => $items->pluck('id')->all(),
            'columns' => $items->count(),
            'images' => $items->map(fn (Media $media): array => $this->imageAttrs($media))->all(),
            'caption' => $caption,
        ]);
    }

    /** @return array{mediaId: int, src: string, alt: string, width: int|null, height: int|null} */
    protected function imageAttrs(Media $media): array
    {
        return ['mediaId' => $media->id, 'src' => $media->url(), 'alt' => (string) $media->alt, 'width' => $media->width, 'height' => $media->height];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function make(string $type, array $attrs = [], string $content = '', array $children = []): array
    {
        return ['id' => 'b_'.Str::lower(Str::random(6)), 'type' => $type, 'attrs' => $attrs, 'content' => $content, 'children' => $children];
    }
}

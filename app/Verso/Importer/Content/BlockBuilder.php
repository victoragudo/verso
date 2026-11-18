<?php

namespace App\Verso\Importer\Content;

use DOMElement;

/**
 * Builds Verso blocks with stable ids and imported image URLs.
 *
 * @phpstan-type VersoBlock array{id: string, type: string, attrs: array<string, mixed>, content: string, children: list<array<string, mixed>>}
 */
class BlockBuilder
{
    public function __construct(protected BlockIds $ids, protected MediaMap $media) {}

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $children
     * @return VersoBlock
     */
    public function make(string $type, array $attrs = [], string $content = '', array $children = []): array
    {
        return [
            'id' => $this->ids->next(),
            'type' => $type,
            'attrs' => $attrs,
            'content' => $content,
            'children' => $children,
        ];
    }

    /** @return VersoBlock */
    public function paragraph(string $html): array
    {
        return $this->make('paragraph', [], trim($html));
    }

    /** @return VersoBlock */
    public function heading(int $level, string $html): array
    {
        return $this->make('heading', ['level' => max(1, min(6, $level))], trim($html));
    }

    /** @return VersoBlock */
    public function list(bool $ordered, string $itemsHtml): array
    {
        return $this->make('list', ['ordered' => $ordered], trim((string) preg_replace('/>\s+</', '><', $itemsHtml)));
    }

    /** @return VersoBlock */
    public function quote(string $html, string $citation = ''): array
    {
        return $this->make('quote', $citation === '' ? [] : ['citation' => $citation], trim($html));
    }

    /** @return VersoBlock */
    public function code(string $code, ?string $language = null): array
    {
        return $this->make('code', ['language' => $language], $code);
    }

    /** @return VersoBlock */
    public function separator(): array
    {
        return $this->make('separator');
    }

    /** @return VersoBlock */
    public function html(string $html): array
    {
        return $this->make('html', [], trim($html));
    }

    /** @return VersoBlock */
    public function embed(string $url, string $provider = '', string $caption = ''): array
    {
        return $this->make('embed', array_filter([
            'url' => trim($url),
            'provider' => $provider,
            'caption' => $caption,
        ], fn (string $value): bool => $value !== ''));
    }

    /** @return VersoBlock */
    public function button(string $url, string $label, string $style = 'fill'): array
    {
        return $this->make('button', ['url' => $url, 'label' => trim($label), 'style' => $style]);
    }

    // An image block. The old URL is swapped for the imported file when we have it.
    /** @return VersoBlock */
    public function image(DOMElement $img, ?int $wordpressId = null, string $caption = '', ?int $width = null, ?int $height = null): array
    {
        $src = $img->getAttribute('src');
        $wordpressId ??= $this->idFromClass($img->getAttribute('class'));
        $imported = $this->media->find($wordpressId, $src);
        $alt = $img->getAttribute('alt');

        return $this->make('image', [
            'mediaId' => $imported?->mediaId,
            'src' => $imported->url ?? $src,
            'alt' => $alt !== '' ? $alt : ($imported->alt ?? ''),
            'caption' => trim($caption),
            'width' => $width ?? $this->dimension($img->getAttribute('width')),
            'height' => $height ?? $this->dimension($img->getAttribute('height')),
        ]);
    }

    /**
     * A gallery from WordPress attachment ids and the image tags inside it.
     *
     * @param  list<int>  $wordpressIds
     * @param  list<DOMElement>  $images
     * @return VersoBlock
     */
    public function gallery(array $wordpressIds, array $images = []): array
    {
        $items = [];

        foreach ($images as $index => $img) {
            $id = $this->idFromClass($img->getAttribute('class')) ?? ($wordpressIds[$index] ?? null);
            $imported = $this->media->find($id, $img->getAttribute('src'));
            $items[] = ['mediaId' => $imported?->mediaId, 'src' => $imported->url ?? $img->getAttribute('src'), 'alt' => $img->getAttribute('alt')];
        }

        if ($images === []) {
            foreach ($wordpressIds as $id) {
                $imported = $this->media->byId($id);

                if ($imported !== null) {
                    $items[] = ['mediaId' => $imported->mediaId, 'src' => $imported->url, 'alt' => $imported->alt];
                }
            }
        }

        return $this->make('gallery', [
            'mediaIds' => array_values(array_filter(array_column($items, 'mediaId'), is_int(...))),
            'images' => $items,
        ]);
    }

    // WordPress marks images with a class like "wp-image-123".
    protected function idFromClass(string $class): ?int
    {
        return preg_match('/\bwp-image-(\d+)\b/', $class, $match) === 1 ? (int) $match[1] : null;
    }

    protected function dimension(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }
}

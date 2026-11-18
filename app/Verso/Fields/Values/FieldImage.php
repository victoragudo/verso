<?php

namespace App\Verso\Fields\Values;

use App\Verso\Media\Models\Media;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;

// An image field value for themes: the media row plus a responsive src, srcset and sizes.
final readonly class FieldImage implements Htmlable, JsonSerializable
{
    public function __construct(
        public Media $media,
        public string $src,
        public string $srcset,
        public string $sizes,
        public string $alt,
        public ?int $width,
        public ?int $height,
    ) {}

    public function id(): int
    {
        return $this->media->id;
    }

    public function url(): string
    {
        return $this->media->url();
    }

    public function caption(): ?string
    {
        return $this->media->caption;
    }

    /**
     * An img tag. Extra attributes (like class) are added and escaped.
     *
     * @param  array<string, string|int|bool>  $attributes
     */
    public function img(array $attributes = []): string
    {
        $all = array_filter([
            'src' => $this->src,
            'srcset' => $this->srcset,
            'sizes' => $this->srcset === '' ? '' : $this->sizes,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'loading' => 'lazy',
            'decoding' => 'async',
            ...$attributes,
        ], fn (mixed $value, string $key): bool => $key === 'alt' || ($value !== null && $value !== '' && $value !== false), ARRAY_FILTER_USE_BOTH);

        $html = '<img';
        foreach ($all as $name => $value) {
            $html .= $value === true ? ' '.e($name) : ' '.e($name).'="'.e((string) $value).'"';
        }

        return $html.'>';
    }

    public function toHtml(): string
    {
        return $this->img();
    }

    /** @return array{id: int, url: string, src: string, srcset: string, sizes: string, alt: string, caption: string|null, width: int|null, height: int|null, mime_type: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->media->id,
            'url' => $this->media->url(),
            'src' => $this->src,
            'srcset' => $this->srcset,
            'sizes' => $this->sizes,
            'alt' => $this->alt,
            'caption' => $this->media->caption,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->media->mime_type,
        ];
    }
}

<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Media\ResponsiveImages;

// One image with alt text and an optional caption.
class ImageBlock extends BaseBlock
{
    public function __construct(protected ResponsiveImages $images) {}

    public function name(): string
    {
        return 'image';
    }

    public function rules(): array
    {
        return [
            'mediaId' => ['nullable', 'integer', 'min:1'],
            'src' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'alt' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $image = $this->image($block->attrs, $renderer, (string) config('verso.media.images.block_sizes'));

        return $image === '' ? '' : '<figure class="verso-image">'.$image.$this->caption($block->string('caption'), $renderer).'</figure>';
    }

    // An <img> tag from image attrs, or nothing when the source is missing or unsafe.
    // When the image is a known media file, it gets resized versions in a srcset.
    /** @param array<mixed> $attrs */
    public function image(array $attrs, BlockRenderer $renderer, string $sizes = '100vw'): string
    {
        $alt = is_string($attrs['alt'] ?? null) ? $attrs['alt'] : '';
        $width = is_numeric($attrs['width'] ?? null) && (int) $attrs['width'] > 0 ? (int) $attrs['width'] : null;
        $height = is_numeric($attrs['height'] ?? null) && (int) $attrs['height'] > 0 ? (int) $attrs['height'] : null;
        $responsive = $this->images->attributes($attrs['mediaId'] ?? null, $sizes);

        if ($responsive !== null) {
            return '<img'.$this->attribute('src', $responsive['src'])
                .$this->attribute('srcset', $responsive['srcset'] !== '' ? $responsive['srcset'] : null)
                .$this->attribute('sizes', $responsive['srcset'] !== '' ? $responsive['sizes'] : null)
                .$this->attribute('alt', $alt !== '' ? $alt : $responsive['alt'])
                .$this->attribute('width', $responsive['width'] ?? $width)
                .$this->attribute('height', $responsive['height'] ?? $height)
                .' loading="lazy" decoding="async">';
        }

        $src = $renderer->urls()->clean($attrs['src'] ?? null);

        if ($src === null) {
            return '';
        }

        return '<img'.$this->attribute('src', $src).$this->attribute('alt', $alt)
            .$this->attribute('width', $width).$this->attribute('height', $height)
            .' loading="lazy" decoding="async">';
    }
}

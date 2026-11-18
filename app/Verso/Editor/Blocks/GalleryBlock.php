<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Editor\Support\BlockData;

// A grid of images. attrs.mediaIds lists the media, attrs.images keeps the data needed to show them.
class GalleryBlock extends BaseBlock
{
    // Asset key of the lightbox: a dialog that shows the full pictures one by one.
    public const LIGHTBOX = 'lightbox';

    public function __construct(protected ImageBlock $image) {}

    public function name(): string
    {
        return 'gallery';
    }

    public function rules(): array
    {
        return [
            'mediaIds' => ['nullable', 'array'],
            'mediaIds.*' => ['integer', 'min:1'],
            'columns' => ['nullable', 'integer', 'between:1,6'],
            'images' => ['nullable', 'array'],
            'images.*' => ['array'],
            'images.*.mediaId' => ['nullable', 'integer', 'min:1'],
            'images.*.src' => ['required', 'string', 'max:2048', new SafeUrl],
            'images.*.alt' => ['nullable', 'string', 'max:500'],
            'images.*.caption' => ['nullable', 'string', 'max:2000'],
            'images.*.width' => ['nullable', 'integer', 'min:1'],
            'images.*.height' => ['nullable', 'integer', 'min:1'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'lightbox' => ['nullable', 'boolean'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $items = '';
        $lightbox = $block->bool('lightbox');

        $number = 0;

        foreach ($block->list('images') as $image) {
            $tag = is_array($image) ? $this->image->image($image, $renderer, (string) config('verso.media.images.gallery_sizes')) : '';

            if (is_array($image) && $tag !== '') {
                $caption = is_string($image['caption'] ?? null) ? $image['caption'] : '';
                $full = $lightbox ? $renderer->urls()->clean($image['src'] ?? null) : null;
                $number++;
                // A picture without alt text still gives its link a name.
                $name = ! preg_match('/\salt="[^"]+"/', $tag) ? ' aria-label="'.e(__('Open picture :number', ['number' => $number])).'"' : '';
                $tag = $full === null ? $tag : '<a class="verso-lightbox__open" href="'.e($full).'"'.$name.' data-verso-lightbox>'.$tag.'</a>';
                $items .= '<figure class="verso-gallery__item">'.$tag.$this->caption($caption, $renderer).'</figure>';
            }
        }

        if ($items === '') {
            return '';
        }

        $columns = max(1, min(6, $block->int('columns', 3) ?? 3));

        // The lightbox script and styles come only with galleries that use them.
        if ($lightbox) {
            $renderer->needAssets(self::LIGHTBOX);
        }

        return '<figure class="verso-gallery"'.$this->attribute('data-columns', $columns).($lightbox ? ' data-verso-gallery-lightbox data-close="'.e(__('Close')).'" data-previous="'.e(__('Previous picture')).'" data-next="'.e(__('Next picture')).'" data-title="'.e(__('Pictures')).'"' : '').'>'
            .$items.$this->caption($block->string('caption'), $renderer).'</figure>';
    }
}

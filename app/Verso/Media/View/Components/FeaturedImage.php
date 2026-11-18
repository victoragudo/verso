<?php

namespace App\Verso\Media\View\Components;

use App\Verso\Content\Models\Entry;
use App\Verso\Media\ResponsiveImages;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

// Blade component <x-verso-featured-image :entry="$entry" /> that prints the featured image of an entry.
class FeaturedImage extends Component
{
    /** @var array{src: string, srcset: string, sizes: string, width: int|null, height: int|null, alt: string}|null */
    public ?array $image;

    public string $alt;

    protected mixed $mediaId;

    /**
     * @param  bool  $skipLeadImage  print nothing when the first block already shows the same image
     */
    public function __construct(
        ResponsiveImages $images,
        public Entry $entry,
        ?string $sizes = null,
        public bool $eager = false,
        public bool $skipLeadImage = false,
    ) {
        $featured = is_array($entry->meta['featured_image'] ?? null) ? $entry->meta['featured_image'] : [];
        $this->mediaId = $featured['mediaId'] ?? null;
        $this->image = $images->attributes($this->mediaId, $sizes ?? (string) config('verso.media.images.featured_sizes'));
        $this->alt = is_string($featured['alt'] ?? null) && $featured['alt'] !== '' ? $featured['alt'] : (string) ($this->image['alt'] ?? '');
    }

    // Nothing is printed when the entry has no featured image or the file is not an image.
    public function shouldRender(): bool
    {
        return $this->image !== null && ! ($this->skipLeadImage && $this->leadBlockShowsImage());
    }

    // True when the first block of the entry is an image block with the same media id.
    public function leadBlockShowsImage(): bool
    {
        $first = $this->entry->blocks[0] ?? null;

        if (! is_array($first) || ($first['type'] ?? null) !== 'image' || ! is_numeric($this->mediaId)) {
            return false;
        }

        $blockMediaId = $first['attrs']['mediaId'] ?? null;

        return is_numeric($blockMediaId) && (int) $blockMediaId === (int) $this->mediaId;
    }

    public function render(): View
    {
        return view('verso-media::featured-image');
    }
}

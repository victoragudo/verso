<?php

namespace App\Verso\Media;

use App\Verso\Media\Models\Media;
use Illuminate\Routing\UrlGenerator;

// Builds signed URLs of resized images and responsive srcset lists.
class ImageUrl
{
    // Parameters the image route understands.
    public const PARAMS = ['w', 'h', 'fit', 'fm', 'q'];

    // Name of the public image route.
    public const ROUTE = 'media.image';

    /**
     * @param  list<int>  $widths  default srcset widths
     */
    public function __construct(
        protected UrlGenerator $urls,
        protected array $widths,
    ) {}

    /**
     * A signed URL for one size of an image.
     *
     * @param  array<string, int|string>  $params
     */
    public function make(Media|string $image, array $params = []): string
    {
        $path = $image instanceof Media ? $image->path : ltrim($image, '/');

        $relative = $this->urls->signedRoute(self::ROUTE, ['path' => $path, ...$this->only($params)], absolute: false);

        return $this->urls->to($relative);
    }

    /**
     * A srcset value like "a.webp 320w, b.webp 640w".
     *
     * @param  list<int>|null  $widths
     * @param  array<string, int|string>  $params
     */
    public function srcset(Media|string $image, ?array $widths = null, array $params = []): string
    {
        $original = $image instanceof Media ? $image->width : null;
        $height = $image instanceof Media ? $image->height : null;
        unset($params['w']);

        $parts = [];

        foreach ($this->widthsFor($original, $widths) as $width) {
            $sized = ['w' => $width, ...$params];

            // Keep the same shape when a fixed height is asked for.
            if (isset($params['h']) && $original !== null && $height !== null && $original > 0) {
                $sized['h'] = (int) round((int) $params['h'] * $width / $original);
            }

            $parts[] = $this->make($image, $sized).' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Ready to use img attributes for a media image.
     *
     * @param  array<string, int|string>  $params
     * @return array{src: string, srcset: string, sizes: string, width: int|null, height: int|null, alt: string}
     */
    public function attributes(Media $media, string $sizes = '100vw', array $params = []): array
    {
        $widths = $this->widthsFor($media->width, null);
        $largest = $widths[count($widths) - 1];

        return [
            'src' => $media->isResizable() ? $this->make($media, ['w' => $largest, ...$params]) : $media->url(),
            'srcset' => $media->isResizable() ? $this->srcset($media, null, $params) : '',
            'sizes' => $sizes,
            'width' => $media->width,
            'height' => $media->height,
            'alt' => (string) $media->alt,
        ];
    }

    /**
     * The widths to use, never wider than the original image.
     *
     * @param  list<int>|null  $widths
     * @return non-empty-list<int>
     */
    public function widthsFor(?int $originalWidth, ?array $widths): array
    {
        $widths = array_values(array_unique(array_filter($widths ?? $this->widths, fn (int $width): bool => $width > 0)));
        sort($widths);

        if ($originalWidth !== null && $originalWidth > 0) {
            $largest = $widths === [] ? 0 : max($widths);
            $widths = array_values(array_filter($widths, fn (int $width): bool => $width < $originalWidth));

            // Add the original size when it is not bigger than the widest wanted size.
            if ($originalWidth <= $largest || $widths === []) {
                $widths[] = $originalWidth;
            }
        }

        return $widths === [] ? [$originalWidth ?? 1] : $widths;
    }

    /**
     * Keeps only the parameters the image route understands.
     *
     * @param  array<string, int|string>  $params
     * @return array<string, int|string>
     */
    protected function only(array $params): array
    {
        return array_intersect_key($params, array_flip(self::PARAMS));
    }
}

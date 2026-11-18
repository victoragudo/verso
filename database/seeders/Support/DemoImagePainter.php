<?php

namespace Database\Seeders\Support;

use RuntimeException;

// Paints calm, photo-like demo pictures with GD (landscapes, sea, arches, still lifes, fields, portraits) and returns JPEG bytes.
class DemoImagePainter
{
    // Layers of hills drawn in front of the sky.
    protected const HILL_LAYERS = 3;

    // Blur passes over the finished picture.
    protected const SOFTEN_PASSES = 2;

    // Compositions the painter knows. Unknown names fall back to a landscape.
    public const STYLES = ['landscape', 'sea', 'arches', 'still-life', 'fields', 'portrait'];

    public function __construct(
        protected int $width,
        protected int $height,
        protected int $quality,
    ) {}

    /**
     * One picture. The same palette, style and seed always give the same picture.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette  hex colors like "#f4a261"
     */
    public function paint(array $palette, int $seed, string $style = 'landscape'): string
    {
        return $this->paintSized($palette, $seed, $style, $this->width, $this->height);
    }

    /**
     * Like paint(), with its own size (used for square avatars).
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     */
    public function paintSized(array $palette, int $seed, string $style, int $width, int $height): string
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($image === false) {
            throw new RuntimeException('GD could not create the demo image.');
        }

        imagealphablending($image, true);
        $canvas = new DemoCanvas($image, max(1, $width), max(1, $height));
        $hills = array_pad(array_slice($palette['hills'], 0, self::HILL_LAYERS), self::HILL_LAYERS, $palette['horizon']);

        $canvas->gradient($this->rgb($palette['sky']), $this->rgb($palette['horizon']), 0, $canvas->height);

        match ($style) {
            'sea' => $this->sea($canvas, $palette, $hills, $seed),
            'arches' => $this->arches($canvas, $palette, $hills, $seed),
            'still-life' => $this->stillLife($canvas, $palette, $hills, $seed),
            'fields' => $this->fields($canvas, $palette, $hills, $seed),
            'portrait' => $this->portrait($canvas, $palette, $hills, $seed),
            default => $this->landscape($canvas, $palette, $hills, $seed),
        };

        $canvas->soften(self::SOFTEN_PASSES);

        ob_start();
        imagejpeg($image, null, $this->quality);

        return (string) ob_get_clean();
    }

    /**
     * A sun and soft hills.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function landscape(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $canvas->glow($this->rgb($palette['sun']), $canvas->width * (0.2 + ($seed % 7) / 10), $canvas->height * 0.32, $canvas->height * 0.22);

        foreach ($hills as $layer => $color) {
            $canvas->wave($this->rgb($color), 0.55 + $layer * 0.13, 0.08 - $layer * 0.015, 2 + $layer, $seed * 1.7 + $layer * 2.3);
        }
    }

    /**
     * A low sun over a calm sea with light on the water.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function sea(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $horizon = (int) ($canvas->height * 0.58);
        $sunX = $canvas->width * (0.3 + ($seed % 5) / 10);

        $canvas->glow($this->rgb($palette['sun']), $sunX, $horizon - $canvas->height * 0.06, $canvas->height * 0.18);
        $canvas->wave($this->rgb($hills[2]), 0.5, 0.025, 1.5, $seed);
        $canvas->gradient($this->rgb($hills[0]), $this->rgb($hills[1]), $horizon, $canvas->height);

        for ($row = 0; $row < 22; $row++) {
            $y = $horizon + (int) (($canvas->height - $horizon) * ($row / 22) ** 1.4);
            $half = $canvas->width * (0.02 + $row * 0.008);

            for ($dash = 0; $dash < 4; $dash++) {
                $start = $sunX - $half + $half * 2 * (0.5 + 0.5 * sin($row * 2.1 + $dash * 1.7 + $seed));
                $canvas->bar($this->rgb($palette['sun']), $start - $half * 0.25, $y, $start + $half * 0.2, $y + 1 + intdiv($row, 8), 60 + $dash * 12);
            }
        }
    }

    /**
     * A sunny wall with a row of arches and their shadows.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function arches(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $count = 3 + $seed % 3;
        $gap = $canvas->width / ($count + 1);
        $top = $canvas->height * 0.3;

        $canvas->bar($this->rgb($palette['horizon']), 0, $top - $canvas->height * 0.12, $canvas->width, $canvas->height);

        for ($index = 1; $index <= $count; $index++) {
            $x = $gap * $index;
            $width = $gap * 0.55;
            $canvas->arch($this->rgb($hills[2]), $x - $width / 2, $top, $width, $canvas->height * 0.62);
            $canvas->arch($this->rgb($hills[1]), $x - $width / 2 + $width * 0.12, $top + $width * 0.12, $width * 0.76, $canvas->height * 0.55, 60);
        }

        $canvas->bar($this->rgb($hills[0]), 0, $canvas->height * 0.86, $canvas->width, $canvas->height);
        $canvas->glow($this->rgb($palette['sun']), $canvas->width * 0.85, $canvas->height * 0.1, $canvas->height * 0.5, 100);
    }

    /**
     * A table top with a bowl, a cup and a round loaf or fruit.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function stillLife(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $table = $canvas->height * 0.62;
        $canvas->gradient($this->rgb($hills[0]), $this->rgb($hills[2]), (int) $table, $canvas->height);
        $canvas->glow($this->rgb($palette['sun']), $canvas->width * 0.75, $canvas->height * 0.2, $canvas->height * 0.6, 90);

        $centerX = $canvas->width * (0.42 + ($seed % 3) / 20);
        $canvas->ellipse($this->rgb($hills[2]), $centerX + 18, $table + 30, $canvas->width * 0.36, $canvas->height * 0.12, 70);
        $canvas->ellipse($this->rgb($hills[1]), $centerX, $table - $canvas->height * 0.06, $canvas->width * 0.3, $canvas->height * 0.3);
        $canvas->ellipse($this->rgb($palette['sun']), $centerX - $canvas->width * 0.05, $table - $canvas->height * 0.13, $canvas->width * 0.08, $canvas->height * 0.06, 60);
        $canvas->bar($this->rgb($palette['sky']), $centerX + $canvas->width * 0.2, $table - $canvas->height * 0.16, $centerX + $canvas->width * 0.28, $table + 4);
        $canvas->ellipse($this->rgb($hills[0]), $centerX + $canvas->width * 0.24, $table + 4, $canvas->width * 0.08, $canvas->height * 0.025);
    }

    /**
     * Rows of fields in perspective under a wide sky.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function fields(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $horizon = $canvas->height * 0.45;
        $canvas->glow($this->rgb($palette['sun']), $canvas->width * (0.15 + ($seed % 4) / 6), $horizon - 30, $canvas->height * 0.2);
        $canvas->wave($this->rgb($hills[1]), 0.45, 0.03, 2.5, $seed);

        for ($band = 0; $band < 7; $band++) {
            $top = $horizon + ($canvas->height - $horizon) * (($band / 7) ** 1.5);
            $bottom = $horizon + ($canvas->height - $horizon) * ((($band + 1) / 7) ** 1.5);
            $canvas->bar($this->rgb($hills[$band % 3]), 0, $top, $canvas->width, $bottom, $band % 2 === 0 ? 0 : 25);
        }
    }

    /**
     * A soft head and shoulders shape on a plain background, for avatars.
     *
     * @param  array{sky: string, horizon: string, sun: string, hills: list<string>}  $palette
     * @param  list<string>  $hills
     */
    protected function portrait(DemoCanvas $canvas, array $palette, array $hills, int $seed): void
    {
        $centerX = $canvas->width * (0.48 + ($seed % 3) / 50);
        $canvas->glow($this->rgb($palette['sun']), $canvas->width * 0.7, $canvas->height * 0.25, $canvas->height * 0.5, 80);
        $canvas->ellipse($this->rgb($hills[1]), $centerX, $canvas->height * 1.02, $canvas->width * 0.9, $canvas->height * 0.75);
        $canvas->ellipse($this->rgb($hills[0]), $centerX, $canvas->height * 0.42, $canvas->width * 0.36, $canvas->height * 0.44);
        $canvas->ellipse($this->rgb($hills[2]), $centerX, $canvas->height * 0.25, $canvas->width * 0.4, $canvas->height * 0.2, 20);
    }

    /** @return array{int, int, int} */
    protected function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}

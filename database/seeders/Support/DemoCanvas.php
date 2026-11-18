<?php

namespace Database\Seeders\Support;

use GdImage;

// Simple drawing helpers on a GD image: gradients, soft glows, waves, bars, arches and ellipses.
class DemoCanvas
{
    // Rings used to fake a soft glow with transparent ellipses.
    protected const GLOW_RINGS = 28;

    public function __construct(
        public GdImage $image,
        public int $width,
        public int $height,
    ) {}

    /**
     * A vertical gradient between two rows.
     *
     * @param  array{int, int, int}  $top
     * @param  array{int, int, int}  $bottom
     */
    public function gradient(array $top, array $bottom, int $from, int $to): void
    {
        $rows = max(1, $to - $from - 1);

        for ($y = $from; $y < $to; $y++) {
            $ratio = ($y - $from) / $rows;
            imageline($this->image, 0, $y, $this->width - 1, $y, $this->color([
                (int) round($top[0] + ($bottom[0] - $top[0]) * $ratio),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $ratio),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $ratio),
            ]));
        }
    }

    /**
     * A light source: a solid core with fading rings around it.
     *
     * @param  array{int, int, int}  $rgb
     */
    public function glow(array $rgb, float $x, float $y, float $size, int $coreAlpha = 0): void
    {
        for ($ring = self::GLOW_RINGS; $ring >= 1; $ring--) {
            $scale = 1 + $ring * 0.08;
            imagefilledellipse($this->image, (int) $x, (int) $y, (int) ($size * $scale), (int) ($size * $scale), $this->color($rgb, 123));
        }

        imagefilledellipse($this->image, (int) $x, (int) $y, (int) $size, (int) $size, $this->color($rgb, $coreAlpha));
    }

    /**
     * A wavy shape filled down to the bottom edge, like a hill or a far coast.
     *
     * @param  array{int, int, int}  $rgb
     */
    public function wave(array $rgb, float $base, float $amplitude, float $frequency, float $phase): void
    {
        $points = [0, $this->height];

        for ($x = 0; $x <= $this->width; $x += 16) {
            $points[] = $x;
            $points[] = (int) ($this->height * $base + sin($x / $this->width * M_PI * $frequency + $phase) * $this->height * $amplitude);
        }

        array_push($points, $this->width, $this->height);
        imagefilledpolygon($this->image, $points, $this->color($rgb));
    }

    /** @param array{int, int, int} $rgb */
    public function bar(array $rgb, float $x1, float $y1, float $x2, float $y2, int $alpha = 0): void
    {
        imagefilledrectangle($this->image, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $this->color($rgb, $alpha));
    }

    /** @param array{int, int, int} $rgb */
    public function ellipse(array $rgb, float $x, float $y, float $width, float $height, int $alpha = 0): void
    {
        imagefilledellipse($this->image, (int) $x, (int) $y, (int) $width, (int) $height, $this->color($rgb, $alpha));
    }

    // An arch: a rectangle with a round top.
    /** @param array{int, int, int} $rgb */
    public function arch(array $rgb, float $x, float $y, float $width, float $height, int $alpha = 0): void
    {
        $radius = $width / 2;
        imagefilledrectangle($this->image, (int) $x, (int) ($y + $radius), (int) ($x + $width), (int) ($y + $height), $this->color($rgb, $alpha));
        imagefilledarc($this->image, (int) ($x + $radius), (int) ($y + $radius), (int) $width, (int) $width, 180, 360, $this->color($rgb, $alpha), IMG_ARC_PIE);
    }

    // Softens hard edges a little, so the picture looks more like a photo.
    public function soften(int $passes): void
    {
        for ($pass = 0; $pass < $passes; $pass++) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
        }
    }

    // Allocates a color, keeping each channel between 0 and 255. Alpha goes from 0 (solid) to 127 (clear).
    /** @param array{int, int, int} $rgb */
    public function color(array $rgb, int $alpha = 0): int
    {
        return (int) imagecolorallocatealpha(
            $this->image,
            max(0, min(255, $rgb[0])),
            max(0, min(255, $rgb[1])),
            max(0, min(255, $rgb[2])),
            max(0, min(127, $alpha)),
        );
    }
}

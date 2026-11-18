<?php

use App\Verso\Media\ImageUrl;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->images = app(ImageUrl::class);
});

it('makes a signed url for a path', function () {
    $url = $this->images->make('2026/11/olive-grove.jpg', ['w' => 800, 'fm' => 'webp']);

    expect($url)->toStartWith(url('/img/2026/11/olive-grove.jpg'))
        ->toContain('w=800')
        ->toContain('fm=webp')
        ->toContain('signature=');
});

it('drops parameters the image route does not know', function () {
    $url = $this->images->make('2026/11/olive-grove.jpg', ['w' => 800, 'blur' => 20]);

    expect($url)->not->toContain('blur');
});

it('accepts a media model and a leading slash', function () {
    $media = Media::factory()->make(['path' => '2026/11/olive-grove.jpg']);

    expect($this->images->make($media))->toStartWith(url('/img/2026/11/olive-grove.jpg?'))
        ->and($this->images->make('/2026/11/olive-grove.jpg'))->toStartWith(url('/img/2026/11/olive-grove.jpg?'));
});

it('keeps widths smaller than the original and adds the original', function () {
    expect($this->images->widthsFor(1000, [320, 640, 960, 1280]))->toBe([320, 640, 960, 1000])
        ->and($this->images->widthsFor(500, [640, 320]))->toBe([320, 500])
        ->and($this->images->widthsFor(200, [320, 640]))->toBe([200]);
});

it('does not add an original wider than the widest size', function () {
    expect($this->images->widthsFor(6000, [320, 1920]))->toBe([320, 1920]);
});

it('uses the configured widths when the original is unknown', function () {
    expect($this->images->widthsFor(null, null))->toBe([320, 640, 960, 1280, 1920]);
});

it('builds a srcset with one url per width', function () {
    $media = Media::factory()->make(['path' => '2026/11/olive-grove.jpg', 'width' => 1000, 'height' => 500]);

    $srcset = $this->images->srcset($media, [320, 640], ['fm' => 'avif']);
    $parts = explode(', ', $srcset);

    expect($parts)->toHaveCount(2)
        ->and($parts[0])->toEndWith(' 320w')->toContain('w=320')->toContain('fm=avif')
        ->and($parts[1])->toEndWith(' 640w');
});

it('ends the srcset with the original width when it is small', function () {
    $media = Media::factory()->make(['width' => 500, 'height' => 400]);

    expect($this->images->srcset($media, [320, 640]))->toEndWith(' 500w');
});

it('scales a fixed height with each width', function () {
    $media = Media::factory()->make(['width' => 1000, 'height' => 800]);

    $srcset = $this->images->srcset($media, [500], ['h' => 400, 'fit' => 'crop']);

    expect($srcset)->toContain('h=200')->toContain('w=500');
});

it('returns ready img attributes', function () {
    $media = Media::factory()->make(['path' => '2026/11/olive-grove.jpg', 'width' => 800, 'height' => 600, 'alt' => 'Olive trees at noon']);

    $attributes = $this->images->attributes($media, '(min-width: 768px) 50vw, 100vw');

    expect($attributes['src'])->toContain('w=800')
        ->and($attributes['srcset'])->toContain('320w')->toContain('800w')
        ->and($attributes['sizes'])->toBe('(min-width: 768px) 50vw, 100vw')
        ->and($attributes['width'])->toBe(800)
        ->and($attributes['height'])->toBe(600)
        ->and($attributes['alt'])->toBe('Olive trees at noon');
});

it('falls back to the file url for files it cannot resize', function () {
    $media = Media::factory()->make(['disk' => 'local', 'path' => '2026/11/olive-grove.jpg']);

    $attributes = $this->images->attributes($media);

    expect($attributes['src'])->toBe($media->url())
        ->and($attributes['srcset'])->toBe('');
});

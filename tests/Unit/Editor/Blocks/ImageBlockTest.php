<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\ImageBlock;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named image', function () {
    expect(app(ImageBlock::class)->name())->toBe('image');
});

it('renders a figure with size and caption', function () {
    expect($this->renderer->renderBlock(['type' => 'image', 'attrs' => [
        'mediaId' => 12, 'src' => '/img/2026/ribeira.jpg?w=1200', 'alt' => 'Boats on the Douro', 'caption' => 'Ribeira at <em>dusk</em>', 'width' => 1200, 'height' => 800,
    ]]))->toBe('<figure class="verso-image"><img src="/img/2026/ribeira.jpg?w=1200" alt="Boats on the Douro" width="1200" height="800" loading="lazy" decoding="async"><figcaption>Ribeira at <em>dusk</em></figcaption></figure>');
});

it('keeps an empty alt for decorative images', function () {
    expect($this->renderer->renderBlock(['type' => 'image', 'attrs' => ['src' => 'https://cdn.example.org/tiles.png']]))
        ->toBe('<figure class="verso-image"><img src="https://cdn.example.org/tiles.png" alt="" loading="lazy" decoding="async"></figure>');
});

it('renders nothing without a safe source', function (mixed $src) {
    expect($this->renderer->renderBlock(['type' => 'image', 'attrs' => ['src' => $src, 'alt' => 'Tram']]))->toBe('');
})->with([null, '', 'javascript:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>', [[['/a.jpg']]]]);

it('escapes attributes so they cannot break out', function () {
    $html = $this->renderer->renderBlock(['type' => 'image', 'attrs' => ['src' => '/a.jpg" onerror="alert(1)', 'alt' => '"><script>alert(1)</script>']]);

    expect($html)->toContain('src="/a.jpg&quot; onerror=&quot;alert(1)"')
        ->toContain('alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<script');
});

it('ignores bad sizes and sanitizes the caption', function () {
    expect($this->renderer->renderBlock(['type' => 'image', 'attrs' => ['src' => '/a.jpg', 'width' => -5, 'height' => 'tall', 'caption' => '<script>alert(1)</script>']]))
        ->toBe('<figure class="verso-image"><img src="/a.jpg" alt="" loading="lazy" decoding="async"></figure>');
});

it('validates its attrs', function () {
    $rules = app(ImageBlock::class)->rules();

    expect(Validator::make(['mediaId' => 3, 'src' => '/storage/a.jpg', 'alt' => 'Azulejos', 'width' => 800], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['src' => 'javascript:alert(1)'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['mediaId' => 0], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['width' => 'wide'], $rules)->fails())->toBeTrue();
});

it('uses resized versions in a srcset when the image is a media file', function () {
    $photo = Media::factory()->create(['path' => '2026/11/ribeira-at-dusk.jpg', 'width' => 1600, 'height' => 1067, 'alt' => 'Ribeira at dusk']);
    config(['verso.media.images.block_sizes' => '(min-width: 760px) 720px, 100vw']);

    $html = $this->renderer->renderBlock(['type' => 'image', 'attrs' => ['mediaId' => $photo->id, 'src' => '/storage/2026/11/ribeira-at-dusk.jpg', 'alt' => '']]);

    expect($html)->toContain('srcset="')
        ->toContain('/img/2026/11/ribeira-at-dusk.jpg?')
        ->toContain(' 640w')
        ->toContain('sizes="(min-width: 760px) 720px, 100vw"')
        ->toContain('alt="Ribeira at dusk"')
        ->toContain('width="1600" height="1067"')
        ->not->toContain('/storage/2026/11/ribeira-at-dusk.jpg');
});

it('prefers the alt text written in the block', function () {
    $photo = Media::factory()->create(['alt' => 'Old alt text']);

    $html = $this->renderer->renderBlock(['type' => 'image', 'attrs' => ['mediaId' => $photo->id, 'alt' => 'Fishing boats in Matosinhos']]);

    expect($html)->toContain('alt="Fishing boats in Matosinhos"');
});

it('falls back to the stored source when the media file is gone', function () {
    $html = $this->renderer->renderBlock(['type' => 'image', 'attrs' => ['mediaId' => 98765, 'src' => '/storage/2026/11/azulejos.jpg', 'alt' => 'Blue tiles']]);

    expect($html)->toBe('<figure class="verso-image"><img src="/storage/2026/11/azulejos.jpg" alt="Blue tiles" loading="lazy" decoding="async"></figure>');
});

<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\GalleryBlock;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named gallery', function () {
    expect(app(GalleryBlock::class)->name())->toBe('gallery');
});

it('renders every image with captions', function () {
    $html = $this->renderer->renderBlock(['type' => 'gallery', 'attrs' => [
        'mediaIds' => [4, 5],
        'columns' => 2,
        'caption' => 'Trams of Porto',
        'images' => [
            ['mediaId' => 4, 'src' => '/storage/tram-1.jpg', 'alt' => 'Tram 1 on the riverside', 'caption' => 'Line 1'],
            ['mediaId' => 5, 'src' => '/storage/tram-22.jpg', 'alt' => 'Tram 22 downtown'],
        ],
    ]]);

    expect($html)->toBe('<figure class="verso-gallery" data-columns="2">'
        .'<figure class="verso-gallery__item"><img src="/storage/tram-1.jpg" alt="Tram 1 on the riverside" loading="lazy" decoding="async"><figcaption>Line 1</figcaption></figure>'
        .'<figure class="verso-gallery__item"><img src="/storage/tram-22.jpg" alt="Tram 22 downtown" loading="lazy" decoding="async"></figure>'
        .'<figcaption>Trams of Porto</figcaption></figure>');
});

it('defaults to three columns and clamps the value', function () {
    $images = [['src' => '/storage/a.jpg']];

    expect($this->renderer->renderBlock(['type' => 'gallery', 'attrs' => ['images' => $images]]))->toContain('data-columns="3"')
        ->and($this->renderer->renderBlock(['type' => 'gallery', 'attrs' => ['images' => $images, 'columns' => 12]]))->toContain('data-columns="6"');
});

it('skips unsafe and broken images', function () {
    expect($this->renderer->renderBlock(['type' => 'gallery', 'attrs' => ['images' => [
        ['src' => 'javascript:alert(1)'], 'not an image', ['alt' => 'no source'], ['src' => '/storage/ok.jpg'],
    ]]]))->toBe('<figure class="verso-gallery" data-columns="3"><figure class="verso-gallery__item"><img src="/storage/ok.jpg" alt="" loading="lazy" decoding="async"></figure></figure>');
});

it('renders nothing without images', function () {
    expect($this->renderer->renderBlock(['type' => 'gallery', 'attrs' => ['mediaIds' => [1, 2]]]))->toBe('');
});

it('validates its attrs', function () {
    $rules = app(GalleryBlock::class)->rules();

    expect(Validator::make(['mediaIds' => [1, 2], 'images' => [['mediaId' => 1, 'src' => '/a.jpg']]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['mediaIds' => ['first']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['images' => [['src' => 'javascript:alert(1)']]], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['columns' => 9], $rules)->fails())->toBeTrue();
});

it('gives media images a srcset with the gallery sizes', function () {
    $photo = Media::factory()->create(['path' => '2026/11/sintra-palace.jpg']);
    config(['verso.media.images.gallery_sizes' => '(min-width: 760px) 240px, 50vw']);

    $html = $this->renderer->renderBlock(['type' => 'gallery', 'attrs' => [
        'mediaIds' => [$photo->id],
        'images' => [['mediaId' => $photo->id, 'src' => '/storage/2026/11/sintra-palace.jpg', 'alt' => 'Pena Palace in the fog']],
    ]]);

    expect($html)->toContain('/img/2026/11/sintra-palace.jpg?')
        ->toContain('sizes="(min-width: 760px) 240px, 50vw"')
        ->toContain('alt="Pena Palace in the fog"');
});

<?php

use App\Verso\Media\ImageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('public')->putFileAs('2026/11', UploadedFile::fake()->image('harbour.jpg', 1200, 800), 'harbour.jpg');
    $this->images = app(ImageUrl::class);
});

it('serves a resized webp image', function () {
    $response = $this->get($this->images->make('2026/11/harbour.jpg', ['w' => 300, 'fm' => 'webp']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000')->toContain('public');

    [$width, $height] = getimagesizefromstring($response->streamedContent());
    expect([$width, $height])->toBe([300, 200]);
});

it('serves avif images', function () {
    $this->get($this->images->make('2026/11/harbour.jpg', ['w' => 120, 'fm' => 'avif']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/avif');
});

it('uses webp when no format is given', function () {
    $this->get($this->images->make('2026/11/harbour.jpg', ['w' => 100]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

it('crops to an exact size', function () {
    $response = $this->get($this->images->make('2026/11/harbour.jpg', ['w' => 200, 'h' => 200, 'fit' => 'crop', 'fm' => 'jpg']));

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect(array_slice(getimagesizefromstring($response->streamedContent()), 0, 2))->toBe([200, 200]);
});

it('caches each size on disk', function () {
    $url = $this->images->make('2026/11/harbour.jpg', ['w' => 300]);

    $this->get($url)->assertOk();
    $files = Storage::disk('local')->allFiles('glide-cache');
    $this->get($url)->assertOk();

    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith('glide-cache/2026/11/harbour.jpg/')->toEndWith('.webp')
        ->and(Storage::disk('local')->allFiles('glide-cache'))->toBe($files);
});

it('refuses urls without a valid signature', function () {
    $this->get('/img/2026/11/harbour.jpg?w=300')->assertForbidden();

    $url = $this->images->make('2026/11/harbour.jpg', ['w' => 300]);
    $this->get(str_replace('w=300', 'w=3000', $url))->assertForbidden();
});

it('refuses sizes above the limit', function () {
    config(['verso.media.images.max_dimension' => 500]);

    $this->get($this->images->make('2026/11/harbour.jpg', ['w' => 501]))->assertStatus(400);
});

it('refuses unknown formats', function () {
    $this->get($this->images->make('2026/11/harbour.jpg', ['fm' => 'tiff']))->assertStatus(400);
});

it('returns 404 for missing images', function () {
    $this->get($this->images->make('2026/11/lighthouse.jpg', ['w' => 300]))->assertNotFound();
});

it('returns 404 for files that are not images', function () {
    Storage::disk('public')->put('2026/11/notes.pdf', '%PDF-1.4 bakery notes');

    $this->get($this->images->make('2026/11/notes.pdf'))->assertNotFound();
});

it('returns 404 for broken image files', function () {
    Storage::disk('public')->put('2026/11/broken.jpg', 'not really a jpeg');

    $this->get($this->images->make('2026/11/broken.jpg', ['w' => 300]))->assertNotFound();
});

it('refuses paths that leave the media folder', function () {
    $this->get($this->images->make('2026/../../.env.jpg'))->assertNotFound();
});

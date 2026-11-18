<?php

use App\Models\User;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('knows its type', function () {
    expect(Media::factory()->make()->type())->toBe(MediaType::Image)
        ->and(Media::factory()->video()->make()->isVideo())->toBeTrue()
        ->and(Media::factory()->document()->make()->isImage())->toBeFalse()
        ->and(Media::factory()->audio()->make()->type())->toBe(MediaType::Audio);
});

it('gives the lower case extension', function () {
    $media = Media::factory()->make(['path' => '2026/11/Harbour-At-Dawn.JPG']);

    expect($media->extension())->toBe('jpg');
});

it('builds the public url from its disk', function () {
    $media = Media::factory()->make(['path' => '2026/11/harbour-at-dawn.jpg']);

    expect($media->url())->toBe(Storage::disk('public')->url('2026/11/harbour-at-dawn.jpg'));
});

it('belongs to the user who uploaded it', function () {
    $user = User::factory()->create(['name' => 'Lucia Ferreira']);
    $media = Media::factory()->create(['uploaded_by' => $user->id]);

    expect($media->uploader->name)->toBe('Lucia Ferreira');
});

it('filters by type', function () {
    Media::factory()->count(2)->create();
    Media::factory()->video()->create();
    Media::factory()->audio()->create();
    Media::factory()->document()->create();

    expect(Media::query()->ofType(MediaType::Image)->count())->toBe(2)
        ->and(Media::query()->ofType(MediaType::Video)->count())->toBe(1)
        ->and(Media::query()->ofType(MediaType::Audio)->count())->toBe(1)
        ->and(Media::query()->ofType(MediaType::Document)->pluck('mime_type')->all())->toBe(['application/pdf']);
});

it('searches file name, alt text and caption', function () {
    Media::factory()->create(['filename' => 'lisbon-tram.jpg', 'alt' => 'Yellow tram', 'caption' => null]);
    Media::factory()->create(['filename' => 'garden.jpg', 'alt' => 'Tomatoes on the vine', 'caption' => null]);
    Media::factory()->create(['filename' => 'bread.jpg', 'alt' => 'Fresh loaf', 'caption' => 'Baked in Lisbon last spring']);

    expect(Media::query()->search('lisbon')->count())->toBe(2)
        ->and(Media::query()->search('tomatoes')->count())->toBe(1)
        ->and(Media::query()->search('  ')->count())->toBe(3);
});

it('treats percent signs in a search as text', function () {
    Media::factory()->create(['filename' => 'sale-50%-off.jpg']);
    Media::factory()->create(['filename' => 'garden.jpg']);

    expect(Media::query()->search('%')->count())->toBe(1);
});

it('can only resize images on the media disk', function () {
    expect(Media::factory()->make()->isResizable())->toBeTrue()
        ->and(Media::factory()->make(['disk' => 's3'])->isResizable())->toBeFalse()
        ->and(Media::factory()->document()->make()->isResizable())->toBeFalse();
});

it('gives a signed image url for images and the plain url for others', function () {
    $photo = Media::factory()->make(['path' => '2026/11/harbour.jpg']);
    $guide = Media::factory()->document()->make();

    expect($photo->imageUrl(['w' => 640]))->toContain('/img/2026/11/harbour.jpg?')
        ->toContain('w=640')
        ->toContain('signature=')
        ->and($guide->imageUrl(['w' => 640]))->toBe($guide->url());
});

it('checks if its file is still on disk', function () {
    $media = Media::factory()->make(['path' => '2026/11/harbour.jpg']);

    expect($media->fileExists())->toBeFalse();

    Storage::disk('public')->put('2026/11/harbour.jpg', 'jpeg bytes');

    expect($media->fileExists())->toBeTrue();
});

it('casts meta to an array', function () {
    $media = Media::factory()->create(['meta' => ['camera' => 'Fujifilm X100V']]);

    expect($media->fresh()->meta)->toBe(['camera' => 'Fujifilm X100V']);
});

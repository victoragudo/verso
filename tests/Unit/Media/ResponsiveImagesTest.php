<?php

use App\Verso\Media\Models\Media;
use App\Verso\Media\ResponsiveImages;
use Illuminate\Support\Facades\DB;

it('gives srcset attributes for a resizable image', function () {
    $photo = Media::factory()->create(['path' => '2026/11/belem-tower.jpg', 'width' => 1280, 'height' => 853, 'alt' => 'Belém Tower at noon']);

    $attributes = app(ResponsiveImages::class)->attributes($photo->id, '100vw');

    expect($attributes['srcset'])->toContain('/img/2026/11/belem-tower.jpg?')
        ->and($attributes['sizes'])->toBe('100vw')
        ->and($attributes['alt'])->toBe('Belém Tower at noon')
        ->and($attributes['width'])->toBe(1280);
});

it('returns null for missing ids, bad ids and files that are not images', function (mixed $id) {
    expect(app(ResponsiveImages::class)->attributes($id, '100vw'))->toBeNull();
})->with([null, 0, -4, 'twelve', 424242]);

it('returns null for videos', function () {
    $clip = Media::factory()->video()->create();

    expect(app(ResponsiveImages::class)->attributes($clip->id, '100vw'))->toBeNull();
});

it('loads each media file only once', function () {
    $photo = Media::factory()->create();
    $images = app(ResponsiveImages::class);

    DB::enableQueryLog();
    $images->attributes($photo->id, '100vw');
    $images->attributes($photo->id, '50vw');

    expect(DB::getQueryLog())->toHaveCount(1);
});

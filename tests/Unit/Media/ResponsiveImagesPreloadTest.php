<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use App\Verso\Media\ResponsiveImages;
use Illuminate\Support\Facades\DB;

// Builds an entry with a featured image and a gallery block, without saving it.
function entryWithMedia(int $featuredId, array $galleryIds): Entry
{
    return new Entry([
        'type' => 'post',
        'title' => 'Autumn walks around Lake Bled',
        'slug' => 'autumn-walks-around-lake-bled',
        'meta' => ['featured_image' => ['mediaId' => $featuredId]],
        'blocks' => [
            ['id' => 'b_lead01', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>The trail starts by the castle.</p>', 'children' => []],
            ['id' => 'b_cols01', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
                ['id' => 'b_gal001', 'type' => 'gallery', 'attrs' => ['mediaIds' => $galleryIds], 'content' => '', 'children' => []],
            ]],
        ],
    ]);
}

it('loads the media of many entries with one query', function () {
    $media = Media::factory()->count(4)->create(['mime_type' => 'image/jpeg', 'width' => 1200, 'height' => 800]);
    $entries = [
        entryWithMedia($media[0]->id, [$media[1]->id, $media[2]->id]),
        entryWithMedia($media[3]->id, [$media[1]->id]),
    ];

    $images = app(ResponsiveImages::class);

    DB::enableQueryLog();
    $images->preloadEntries($entries);
    $images->attributes($media[0]->id, '100vw');
    $images->attributes($media[2]->id, '100vw');
    $images->attributes($media[3]->id, '100vw');

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('remembers missing media so they are not asked for again', function () {
    $images = app(ResponsiveImages::class);

    DB::enableQueryLog();
    $images->preload([9001, 9002]);
    $first = $images->attributes(9001, '100vw');
    $images->preload([9001]);

    expect($first)->toBeNull()
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('reads the media again after forget', function () {
    $photo = Media::factory()->create(['mime_type' => 'image/jpeg', 'width' => 1600, 'height' => 900]);
    $images = app(ResponsiveImages::class);
    $images->preload([$photo->id]);
    $images->forget();

    DB::enableQueryLog();
    $images->attributes($photo->id, '100vw');

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('is shared during one request', function () {
    expect(app(ResponsiveImages::class))->toBe(app(ResponsiveImages::class));
});

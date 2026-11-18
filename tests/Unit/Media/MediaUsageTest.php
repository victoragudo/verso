<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaUsage;

beforeEach(function () {
    $this->usage = app(MediaUsage::class);
    $this->photo = Media::factory()->create();
});

function mediaImageBlock(int|string $mediaId): array
{
    return ['id' => 'b_img1', 'type' => 'image', 'attrs' => ['mediaId' => $mediaId, 'alt' => 'Harbour at dawn'], 'children' => []];
}

it('finds entries with an image block', function () {
    $entry = Entry::factory()->create(['title' => 'A Slow Weekend in Porto', 'blocks' => [mediaImageBlock($this->photo->id)]]);
    Entry::factory()->create();

    expect($this->usage->entriesUsing($this->photo)->pluck('id')->all())->toBe([$entry->id])
        ->and($this->usage->isUsed($this->photo))->toBeTrue();
});

it('finds entries with a gallery block', function () {
    Entry::factory()->create(['blocks' => [
        ['id' => 'b_gal1', 'type' => 'gallery', 'attrs' => ['mediaIds' => [99, $this->photo->id]], 'children' => []],
    ]]);

    expect($this->usage->entriesUsing($this->photo))->toHaveCount(1);
});

it('looks inside nested columns', function () {
    Entry::factory()->create(['blocks' => [
        ['id' => 'b_cols', 'type' => 'columns', 'attrs' => [], 'children' => [
            ['id' => 'b_col1', 'type' => 'column', 'attrs' => [], 'children' => [mediaImageBlock((string) $this->photo->id)]],
        ]],
    ]]);

    expect($this->usage->isUsed($this->photo))->toBeTrue();
});

it('ignores ids that only look alike', function () {
    $other = Media::factory()->create();
    $text = 'We stayed in room '.$this->photo->id.'0 for '.$this->photo->id.' nights.';
    Entry::factory()->create(['blocks' => [
        mediaImageBlock((int) ($this->photo->id.'0')),
        ['id' => 'b_p1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.$text.'</p>', 'children' => []],
    ]]);

    expect($this->usage->isUsed($this->photo))->toBeFalse()
        ->and($this->usage->isUsed($other))->toBeFalse();
});

it('checks the featured image in entry meta', function () {
    Entry::factory()->create(['meta' => ['featured_media_id' => $this->photo->id]]);

    expect($this->usage->isUsed($this->photo))->toBeTrue();
});

it('checks the featured image saved by the WordPress importer', function () {
    Entry::factory()->create(['meta' => ['featured_image' => ['mediaId' => $this->photo->id, 'src' => '/storage/uploads/2024/03/tram.jpg', 'alt' => 'Tram 28']]]);

    expect($this->usage->isUsed($this->photo))->toBeTrue();
});

it('counts trashed entries too', function () {
    $entry = Entry::factory()->create(['blocks' => [mediaImageBlock($this->photo->id)]]);
    $entry->delete();

    expect($this->usage->isUsed($this->photo))->toBeTrue();
});

it('walks blocks without crashing on odd data', function () {
    expect($this->usage->blocksUse(['plain text', ['type' => 'image'], ['attrs' => 'broken']], 5))->toBeFalse()
        ->and($this->usage->blocksUse([mediaImageBlock('5')], 5))->toBeTrue()
        ->and($this->usage->blocksUse([mediaImageBlock('5a')], 5))->toBeFalse();
});

it('lists every media id an entry uses once', function () {
    $entry = new Entry([
        'type' => 'post',
        'title' => 'A rainy day at the Rijksmuseum',
        'slug' => 'a-rainy-day-at-the-rijksmuseum',
        'meta' => ['featured_image' => ['mediaId' => 12], 'featured_media_id' => '7'],
        'blocks' => [
            ['id' => 'b_img001', 'type' => 'image', 'attrs' => ['mediaId' => 12], 'content' => '', 'children' => []],
            ['id' => 'b_cols01', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
                ['id' => 'b_gal001', 'type' => 'gallery', 'attrs' => ['mediaIds' => [3, '4', 'not-a-number', 0]], 'content' => '', 'children' => []],
            ]],
        ],
    ]);

    $ids = app(MediaUsage::class)->mediaIds($entry);
    sort($ids);

    expect($ids)->toBe([3, 4, 7, 12]);
});

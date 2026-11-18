<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Blade;

// Renders the component for an entry with the given featured image meta.
function featuredImageHtml(array $meta): string
{
    return Blade::render('<x-verso-featured-image :entry="$entry" />', ['entry' => new Entry(['meta' => $meta])]);
}

it('prints the featured image with a srcset and the alt text from the entry', function () {
    $media = Media::factory()->create(['alt' => 'Old alt text', 'width' => 1600, 'height' => 1067]);

    $html = featuredImageHtml(['featured_image' => ['mediaId' => $media->id, 'alt' => 'Fishing boats on the beach at Nazare.']]);

    expect($html)->toContain('class="verso-featured-image"')
        ->toContain('srcset="')
        ->toContain('sizes="(min-width: 760px) 640px, 100vw"')
        ->toContain('alt="Fishing boats on the beach at Nazare."')
        ->toContain('width="1600"')
        ->toContain('loading="lazy"');
});

it('falls back to the media alt text', function () {
    $media = Media::factory()->create(['alt' => 'A tram climbing a hill in Lisbon.']);

    expect(featuredImageHtml(['featured_image' => ['mediaId' => $media->id]]))->toContain('alt="A tram climbing a hill in Lisbon."');
});

it('uses the configured sizes attribute', function () {
    config(['verso.media.images.featured_sizes' => '50vw']);
    $media = Media::factory()->create();

    expect(featuredImageHtml(['featured_image' => ['mediaId' => $media->id]]))->toContain('sizes="50vw"');
});

it('prints nothing without a usable featured image', function (array $meta) {
    expect(trim(featuredImageHtml($meta)))->toBe('');
})->with([
    'no meta' => [[]],
    'missing media' => [['featured_image' => ['mediaId' => 99999]]],
    'broken meta' => [['featured_image' => 'cover.jpg']],
]);

it('prints nothing for a video', function () {
    $video = Media::factory()->video()->create();

    expect(trim(featuredImageHtml(['featured_image' => ['mediaId' => $video->id]])))->toBe('');
});

// Renders the component with skip-lead-image for an entry with blocks.
function featuredImageWithBlocks(int $mediaId, array $blocks): string
{
    return Blade::render('<x-verso-featured-image :entry="$entry" :skip-lead-image="true" />', [
        'entry' => new Entry(['meta' => ['featured_image' => ['mediaId' => $mediaId]], 'blocks' => $blocks]),
    ]);
}

it('skips the featured image when the first block shows the same image', function () {
    $media = Media::factory()->create(['alt' => 'Sardines on a grill in Alfama.']);

    $html = featuredImageWithBlocks($media->id, [
        ['id' => 'b_lead', 'type' => 'image', 'attrs' => ['mediaId' => $media->id, 'alt' => 'Sardines on a grill in Alfama.']],
        ['id' => 'b_text', 'type' => 'paragraph', 'content' => 'June is the month of the sardine festivals.'],
    ]);

    expect(trim($html))->toBe('');
});

it('treats a media id stored as text like the same image', function () {
    $media = Media::factory()->create();

    expect(trim(featuredImageWithBlocks($media->id, [['id' => 'b_lead', 'type' => 'image', 'attrs' => ['mediaId' => (string) $media->id]]])))->toBe('');
});

it('keeps the featured image when the first block is another image or not an image', function (string $case) {
    $media = Media::factory()->create();
    $other = Media::factory()->create();

    $blocks = match ($case) {
        'other image' => [['id' => 'b_lead', 'type' => 'image', 'attrs' => ['mediaId' => $other->id]]],
        'later image' => [
            ['id' => 'b_text', 'type' => 'paragraph', 'content' => 'We started the walk at the river.'],
            ['id' => 'b_img', 'type' => 'image', 'attrs' => ['mediaId' => $media->id]],
        ],
        'image without media' => [['id' => 'b_lead', 'type' => 'image', 'attrs' => ['src' => 'https://images.example.org/cliffs.jpg']]],
        'no blocks' => [],
    };

    expect(featuredImageWithBlocks($media->id, $blocks))->toContain('class="verso-featured-image"');
})->with(['other image', 'later image', 'image without media', 'no blocks']);

it('shows the image even when it leads the content unless asked to skip it', function () {
    $media = Media::factory()->create();

    $html = Blade::render('<x-verso-featured-image :entry="$entry" />', [
        'entry' => new Entry(['meta' => ['featured_image' => ['mediaId' => $media->id]], 'blocks' => [['id' => 'b_lead', 'type' => 'image', 'attrs' => ['mediaId' => $media->id]]]]),
    ]);

    expect($html)->toContain('class="verso-featured-image"');
});

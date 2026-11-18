<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->photo = Media::factory()->create(['alt' => 'Morning fog over the Douro valley vineyards.', 'width' => 1600, 'height' => 1067]);
});

function postWithPhoto(Media $photo, array $blocks, string $type = 'post'): Entry
{
    return Entry::factory()->published()->create([
        'type' => $type,
        'title' => 'Harvest Week in the Douro Valley',
        'slug' => 'harvest-week-douro',
        'blocks' => $blocks,
        'meta' => ['featured_image' => ['mediaId' => $photo->id, 'alt' => $photo->alt]],
    ]);
}

it('shows the featured image on the entry page', function () {
    $post = postWithPhoto($this->photo, [
        ['id' => 'b_text', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Picking starts at sunrise, before the heat.', 'children' => []],
    ]);

    $this->get($post->path())
        ->assertOk()
        ->assertSee('class="verso-featured-image entry-featured-image"', false)
        ->assertSee('loading="eager"', false)
        ->assertSee('alt="Morning fog over the Douro valley vineyards."', false);
});

it('does not repeat the featured image when the first block shows it', function () {
    $post = postWithPhoto($this->photo, [
        ['id' => 'b_lead', 'type' => 'image', 'attrs' => ['mediaId' => $this->photo->id, 'alt' => $this->photo->alt], 'content' => '', 'children' => []],
        ['id' => 'b_text', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Picking starts at sunrise, before the heat.', 'children' => []],
    ]);

    $html = $this->get($post->path())->assertOk()->getContent();

    expect($html)->not->toContain('class="verso-featured-image')
        ->and(substr_count($html, 'alt="Morning fog over the Douro valley vineyards."'))->toBe(1);
});

it('shows the featured image on pages too', function () {
    $page = postWithPhoto($this->photo, [
        ['id' => 'b_text', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Our cellar is open for visits on Saturdays.', 'children' => []],
    ], 'page');

    $this->get($page->path())->assertOk()->assertSee('class="verso-featured-image entry-featured-image"', false);
});

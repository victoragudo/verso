<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\EntryContentFilter;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);

    $this->entry = Entry::factory()->published()->by(User::factory()->author()->create())->create([
        'title' => 'Five Tascas Worth the Queue',
        'slug' => 'five-tascas-worth-the-queue',
        'blocks' => [['id' => 'b_tasca1', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Arrive before noon.', 'children' => []]],
    ]);

    Filters::add(EntryContentFilter::class, fn (string $html, Entry $entry): string => '<p class="note">Reading list: '.e($entry->title).'</p>'.$html);
});

it('runs plugin content filters on the public entry page', function () {
    $this->get($this->entry->path())
        ->assertOk()
        ->assertSee('<p class="note">Reading list: Five Tascas Worth the Queue</p>', false)
        ->assertSee('Arrive before noon.');
});

it('runs plugin content filters in the feed', function () {
    $this->get('/feed')
        ->assertOk()
        ->assertSee('Reading list: Five Tascas Worth the Queue', false);
});

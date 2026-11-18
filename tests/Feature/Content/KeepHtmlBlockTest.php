<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;

beforeEach(function () {
    $this->booking = ['id' => 'b_book01', 'type' => 'html', 'attrs' => [], 'content' => '<div class="booking-widget" data-venue="harbour-cafe"></div>', 'children' => []];
    $this->entry = Entry::factory()->published()->create([
        'title' => 'Book a Table at the Harbour Cafe',
        'blocks' => [
            ['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => 'We open at eight every morning.', 'children' => []],
            $this->booking,
        ],
    ]);
    $this->editor = User::factory()->editor()->create();
});

function updateHarbourEntry(Entry $entry, array $blocks): array
{
    return [
        'title' => $entry->title,
        'slug' => $entry->slug,
        'status' => 'published',
        'blocks' => $blocks,
    ];
}

it('lets an editor save an entry that keeps an admin html block unchanged', function () {
    $blocks = [
        ['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => 'We open at seven every morning in summer.', 'children' => []],
        $this->booking,
    ];

    $this->actingAs($this->editor)
        ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $this->entry->id]), updateHarbourEntry($this->entry, $blocks))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(data_get($this->entry->fresh()->blocks, '0.content'))->toBe('We open at seven every morning in summer.')
        ->and(data_get($this->entry->fresh()->blocks, '1.content'))->toBe($this->booking['content']);
});

it('stops an editor from changing the html of an admin html block', function () {
    $changed = [...$this->booking, 'content' => '<script src="https://tracker.example.net/pixel.js"></script>'];

    $this->actingAs($this->editor)
        ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $this->entry->id]), updateHarbourEntry($this->entry, [$changed]))
        ->assertSessionHasErrors('blocks');

    expect(data_get($this->entry->fresh()->blocks, '1.content'))->toBe($this->booking['content']);
});

it('does not let a new entry reuse html blocks from another entry', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), [
            'title' => 'Copied booking widget',
            'status' => 'draft',
            'blocks' => [$this->booking],
        ])
        ->assertSessionHasErrors('blocks');
});

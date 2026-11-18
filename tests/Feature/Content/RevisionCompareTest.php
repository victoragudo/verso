<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Support\BlockDiff;

it('finds added, removed, changed and moved blocks by id', function () {
    $before = [
        ['id' => 'b_title', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Our bread'],
        ['id' => 'b_intro', 'type' => 'paragraph', 'content' => 'Baked at dawn.'],
        ['id' => 'b_old', 'type' => 'paragraph', 'content' => 'Closed on Sunday.'],
    ];
    $after = [
        ['id' => 'b_intro', 'type' => 'paragraph', 'content' => 'Baked at dawn.'],
        ['id' => 'b_title', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Our sourdough'],
        ['id' => 'b_new', 'type' => 'section', 'children' => [['id' => 'b_hours', 'type' => 'paragraph', 'content' => 'Open every day.']]],
    ];

    $changes = collect((new BlockDiff)->compare($before, $after))->mapWithKeys(fn (array $change) => [$change['id'] => $change['change']])->all();

    expect($changes)->toBe([
        'b_intro' => 'moved',
        'b_title' => 'changed',
        'b_new' => 'added',
        'b_hours' => 'added',
        'b_old' => 'removed',
    ]);
});

it('draws a revision and the saved entry with block markers for the comparison', function () {
    $admin = User::factory()->admin()->create();
    $entry = Entry::factory()->published()->create(['type' => 'page', 'title' => 'About the bakery', 'blocks' => [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Since 1998.']]]);
    $revision = $entry->revisions()->create(['title' => 'About us', 'blocks' => [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Since 1996.']]]);

    $this->actingAs($admin)
        ->getJson(route('admin.entries.revisions.compare', ['contentType' => 'page', 'entry' => $entry->id, 'revision' => $revision->id]))
        ->assertOk()
        ->assertJsonPath('title.before', 'About us')
        ->assertJsonPath('changes.0.change', 'changed')
        ->assertJson(fn ($json) => $json->where('before', fn (string $html) => str_contains($html, 'data-verso-block="b_1"') && str_contains($html, 'Since 1996.'))->etc());
});

it('keeps revision comparisons for people who may edit the entry', function () {
    $entry = Entry::factory()->published()->create(['type' => 'page']);
    $revision = $entry->revisions()->create(['title' => 'Old', 'blocks' => []]);

    $this->actingAs(User::factory()->contributor()->create())
        ->getJson(route('admin.entries.revisions.compare', ['contentType' => 'page', 'entry' => $entry->id, 'revision' => $revision->id]))
        ->assertForbidden();
});

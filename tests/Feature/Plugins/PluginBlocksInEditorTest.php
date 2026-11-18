<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;

// A paragraph with the given number of words about a walk in Sintra.
function sintraParagraph(string $id, int $words): array
{
    return ['id' => $id, 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.trim(str_repeat('forest ', $words)).'</p>', 'children' => []];
}

beforeEach(function () {
    $this->manager = PluginFixtures::use(base_path('plugins'));
    $this->manager->enable('reading-time');
    $this->manager->register($this->manager->get('reading-time'));
    $this->editor = User::factory()->editor()->create(['name' => 'Ines Duarte']);
});

it('shares the plugin blocks with the create page', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/entries/edit')
            ->where('pluginBlocks', function ($blocks) {
                $block = collect($blocks)->firstWhere('name', 'reading-time');

                return collect($blocks)->pluck('name')->intersect(['map', 'reading-time', 'tabs'])->count() === 3
                    && $block['label'] === 'Reading time'
                    && $block['description'] === 'Shows how many minutes the entry takes to read.'
                    && $block['fields'][0]['name'] === 'minutes'
                    && $block['fields'][0]['type'] === 'number'
                    && $block['fields'][0]['min'] === 1
                    && $block['fields'][0]['max'] === 600;
            })
        );
});

it('shares the plugin blocks with the edit page', function () {
    $entry = Entry::factory()->create(['title' => 'A slow walk through Sintra']);

    $this->actingAs($this->editor)
        ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]))
        ->assertInertia(fn (Assert $page) => $page->where('pluginBlocks', fn ($blocks) => collect($blocks)->contains('name', 'reading-time')));
});

it('sends an empty list when no plugin adds blocks', function () {
    PluginFixtures::use();
    app()->forgetInstance(PluginBlockRegistry::class);

    $this->actingAs($this->editor)
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page->where('pluginBlocks', []));
});

it('saves a plugin block and renders it on the public site', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'A slow walk through Sintra',
        'slug' => '',
        'status' => 'published',
        'blocks' => [
            ['id' => 'b_rt0001', 'type' => 'reading-time', 'attrs' => ['minutes' => 4], 'content' => '', 'children' => []],
            sintraParagraph('b_sintra1', 450),
        ],
    ])->assertSessionHasNoErrors();

    $entry = Entry::firstWhere('slug', 'a-slow-walk-through-sintra');

    expect($entry->blocks[0])->toMatchArray(['type' => 'reading-time', 'attrs' => ['minutes' => 4]]);

    auth()->logout();

    $this->get($entry->path())
        ->assertOk()
        ->assertSee('<p class="reading-time">3 min read</p>', false);
});

it('saves a plugin block inside columns', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Two days in Evora',
        'status' => 'draft',
        'blocks' => [[
            'id' => 'b_cols01', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
                ['id' => 'b_col01', 'type' => 'column', 'attrs' => [], 'content' => '', 'children' => [
                    ['id' => 'b_rt0002', 'type' => 'reading-time', 'attrs' => [], 'content' => '', 'children' => []],
                ]],
            ],
        ]],
    ])->assertSessionHasNoErrors();

    expect(Entry::firstWhere('slug', 'two-days-in-evora')->blocks[0]['children'][0]['children'][0]['type'])->toBe('reading-time');
});

it('rejects plugin block attrs that break the field rules', function (mixed $minutes) {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Cycling the Douro valley',
        'status' => 'draft',
        'blocks' => [['id' => 'b_rt0003', 'type' => 'reading-time', 'attrs' => ['minutes' => $minutes], 'content' => '', 'children' => []]],
    ])->assertSessionHasErrors('blocks');

    expect(Entry::count())->toBe(0);
})->with([0, 601, 'a few']);

it('rejects plugin blocks once the plugin is turned off', function () {
    PluginFixtures::use();
    app()->forgetInstance(BlockRegistry::class);

    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Cycling the Douro valley',
        'status' => 'draft',
        'blocks' => [['id' => 'b_rt0004', 'type' => 'reading-time', 'attrs' => [], 'content' => '', 'children' => []]],
    ])->assertSessionHasErrors('blocks');
});

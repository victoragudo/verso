<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Editor\Styles\StylePresetChanged;
use App\Verso\Editor\Styles\StylePresets;
use App\Verso\Plugins\BlockOutputCache;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

function cardPage(string $slug, string $preset): Entry
{
    $entry = Entry::factory()->published()->create(['type' => 'page', 'slug' => $slug, 'title' => ucfirst($slug), 'blocks' => [
        ['id' => 'b_'.$slug, 'type' => 'paragraph', 'content' => 'Warm bread.', 'attrs' => ['preset' => $preset]],
    ]]);
    app(UsageIndex::class)->refresh($entry);

    return $entry;
}

it('records which entries use which style preset', function () {
    $entry = cardPage('menu', 'card');

    expect(app(UsageIndex::class)->entriesUsing(UsageIndex::PRESET, ['card'])->pluck('id')->all())->toBe([$entry->id])
        ->and(app(UsageIndex::class)->entriesUsing(UsageIndex::PRESET, ['hero'])->all())->toBe([]);
});

it('forgets only the cached pages that use a changed preset', function () {
    $menu = cardPage('menu', 'card');
    $about = cardPage('about', 'hero');
    $pages = Mockery::mock(PageCache::class)->makePartial();
    $pages->shouldReceive('forget')->once()->with($menu->url());
    $pages->shouldNotReceive('flush');
    app()->instance(PageCache::class, $pages);

    StylePresetChanged::dispatch('card');

    expect($about->exists)->toBeTrue();
});

it('gives cached entry HTML a new key when a preset it uses changes', function () {
    $entry = cardPage('menu', 'card');
    // The last part of the key is the hash of the blocks and the presets they use.
    $hash = fn (): string => (string) last(explode('.', app(BlockOutputCache::class)->key($entry)));
    $before = $hash();

    app(StylePresets::class)->save('hero', 'Hero', ['color' => ['background' => '#000000']]);
    expect($hash())->toBe($before);

    app(StylePresets::class)->save('card', 'Card', ['color' => ['background' => '#123456']]);
    expect($hash())->not->toBe($before);
});

it('keeps pages built with static builder blocks in the block cache', function () {
    $renderer = app(ContentRenderer::class);

    expect($renderer->hasPluginBlocks([['type' => 'tabs', 'children' => [['type' => 'tab', 'children' => [['type' => 'paragraph']]]]]]))->toBeFalse()
        ->and($renderer->hasPluginBlocks([['type' => 'section', 'children' => [['type' => 'query-loop']]]]))->toBeTrue()
        ->and($renderer->hasPluginBlocks([['type' => 'synced', 'attrs' => ['ref' => 1]]]))->toBeTrue();
});

it('prints the block files inside cached entry HTML so it is complete', function () {
    $entry = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'faq', 'blocks' => [
        ['id' => 'b_t', 'type' => 'toggle', 'attrs' => ['title' => 'Do you deliver?'], 'children' => [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'Yes, on Fridays.']]],
    ]]);

    $first = app(ContentRenderer::class)->render($entry);
    $second = app(ContentRenderer::class)->render($entry);

    expect($first)->toContain('verso-blocks/disclosure.css')->and($second)->toBe($first);
});

it('lets only people with the custom CSS capability add CSS to a block', function () {
    $blocks = [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Hi', 'attrs' => ['customCss' => 'letter-spacing: 0.02em;']]];
    $designer = User::factory()->editor()->create();
    DB::table('user_capabilities')->insert(['user_id' => $designer->id, 'capability' => Capability::EditCustomCss->value, 'granted' => true]);

    $passes = fn (User $user): bool => Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($user)]])->passes();

    expect($passes(User::factory()->editor()->create()))->toBeFalse()
        ->and($passes($designer->fresh()))->toBeTrue()
        ->and($passes(User::factory()->admin()->create()))->toBeTrue();
});

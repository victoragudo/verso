<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Library\LibraryStore;
use App\Verso\Library\Models\LibraryItem;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Site\Models\SitePart;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->banner = LibraryItem::query()->create([
        'kind' => LibraryItem::SYNCED, 'title' => 'Opening hours banner', 'category' => 'Banners', 'keywords' => ['hours'],
        'blocks' => [['id' => 'b_h', 'type' => 'paragraph', 'content' => 'Open every day from 7am.']],
    ]);
});

it('draws a synced block from the library', function () {
    $html = app(BlockRenderer::class)->render([['id' => 'b_s', 'type' => 'synced', 'attrs' => ['ref' => $this->banner->id]]]);

    expect($html)->toContain('data-verso-synced="'.$this->banner->id.'"')->toContain('Open every day from 7am.');
});

it('draws nothing for a deleted item and stops an item that holds itself', function () {
    $loop = LibraryItem::query()->create(['kind' => LibraryItem::SYNCED, 'title' => 'Loop', 'category' => '', 'keywords' => [], 'blocks' => []]);
    $loop->update(['blocks' => [['id' => 'b_in', 'type' => 'synced', 'attrs' => ['ref' => $loop->id]], ['id' => 'b_t', 'type' => 'paragraph', 'content' => 'Once']]]);

    expect(app(BlockRenderer::class)->render([['id' => 'b_s', 'type' => 'synced', 'attrs' => ['ref' => 999999]]]))->toBe('')
        ->and(substr_count(app(BlockRenderer::class)->render([['id' => 'b_x', 'type' => 'synced', 'attrs' => ['ref' => $loop->id]]]), 'Once'))->toBe(1);
});

it('records where a synced block is used and redraws those pages when it changes', function () {
    $page = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'visit', 'blocks' => [['id' => 'b_s', 'type' => 'synced', 'attrs' => ['ref' => $this->banner->id]]]]);
    app(UsageIndex::class)->refresh($page);

    expect(app(UsageIndex::class)->entriesUsing('synced', [(string) $this->banner->id])->pluck('id')->all())->toBe([$page->id]);

    $this->get($page->url())->assertSee('Open every day from 7am.');
    app(LibraryStore::class)->save($this->banner, ['blocks' => [['id' => 'b_h', 'type' => 'paragraph', 'content' => 'Closed on Mondays.']]], $this->admin);

    expect($this->get($page->url())->getContent())->toContain('Closed on Mondays.');
});

it('saves blocks to the library from the editor', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.library.store'), [
            'kind' => 'pattern', 'title' => 'Three reasons', 'category' => 'Features',
            'blocks' => [['id' => 'b_1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Why us']],
        ])
        ->assertCreated()
        ->assertJsonPath('item.title', 'Three reasons')
        ->assertJsonPath('item.kind', 'pattern');

    $this->postJson(route('admin.library.store'), ['kind' => 'synced', 'title' => 'Empty', 'blocks' => []])->assertUnprocessable();
});

it('checks the blocks people save with their own rights', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->postJson(route('admin.library.store'), ['kind' => 'pattern', 'title' => 'Raw', 'blocks' => [['id' => 'b_1', 'type' => 'html', 'content' => '<script>alert(1)</script>']]])
        ->assertUnprocessable();
});

it('keeps the library for people who may manage it', function () {
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.library.index'))->assertForbidden();
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.library.index'))->assertOk();
});

it('sends the library to the editor', function () {
    $manifest = app(EditorManifest::class)->for($this->admin);

    expect($manifest['library']['canSave'])->toBeTrue()
        ->and(array_column($manifest['library']['items'], 'title'))->toContain('Opening hours banner')
        ->and(app(EditorManifest::class)->for(User::factory()->author()->create())['library']['canSave'])->toBeFalse();
});

it('exports and imports the library as JSON', function () {
    $export = $this->actingAs($this->admin)->get(route('admin.library.export'))->assertOk()->assertHeader('Content-Type', 'application/json');
    $data = json_decode($export->getContent(), true);

    expect($data['format'])->toBe(LibraryStore::FORMAT)->and($data['items'][0]['title'])->toBe('Opening hours banner');

    $file = UploadedFile::fake()->createWithContent('verso-library.json', (string) json_encode($data));
    $this->post(route('admin.library.import'), ['file' => $file])->assertRedirect(route('admin.library.index'));

    expect(LibraryItem::query()->where('title', 'Opening hours banner')->count())->toBe(2);
});

it('refuses library files that are not from Verso', function () {
    $file = UploadedFile::fake()->createWithContent('other.json', '{"format": 9, "items": []}');

    $this->actingAs($this->admin)->post(route('admin.library.import'), ['file' => $file])->assertSessionHasErrors('file');
});

it('lists saved items and theme patterns in one page and edits an item', function () {
    $this->actingAs($this->admin)->get(route('admin.library.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/library/index')->where('items.0.title', 'Opening hours banner')->has('patterns'));

    $this->get(route('admin.library.edit', $this->banner))->assertOk()->assertInertia(fn ($page) => $page->component('admin/library/edit'));
    $this->put(route('admin.library.update', $this->banner), ['title' => 'Hours banner', 'keywords' => ['opening', 'hours']])->assertRedirect();

    expect($this->banner->fresh())->title->toBe('Hours banner')->keywords->toBe(['opening', 'hours']);

    $this->delete(route('admin.library.destroy', $this->banner))->assertRedirect(route('admin.library.index'));
    expect(LibraryItem::query()->find($this->banner->id))->toBeNull();
});

it('empties the whole page cache when a header uses the changed synced block', function () {
    $cache = Mockery::mock(PageCache::class)->makePartial();
    $cache->shouldReceive('flush')->atLeast()->once();
    app()->instance(PageCache::class, $cache);

    SitePart::query()->create(['theme' => 'default', 'part' => 'header', 'blocks' => [], 'published_blocks' => [['id' => 'b_s', 'type' => 'synced', 'attrs' => ['ref' => (string) $this->banner->id]]], 'status' => 'published']);

    app(LibraryStore::class)->save($this->banner, ['title' => 'Banner'], $this->admin);
});

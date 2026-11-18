<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Drafts\PageDraft;
use App\Verso\Easy\Drafts\PageDrafts;
use App\Verso\Easy\Pages\PageVersions;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\BaseBlock;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    app()->forgetInstance(PageCache::class);

    $this->admin = User::factory()->admin()->create(['name' => 'Marta Oliveira']);
    $kit = app(SectionKit::class);
    $library = app(SectionLibrary::class);
    $this->blocks = [
        ...$library->build('hero', ['heading' => 'Fresh bread on Harbour Lane'], $kit),
        ...$library->build('faq', [], $kit),
    ];
    $this->page = Entry::factory()->page()->published()->by($this->admin)->create([
        'title' => 'Harbour Lane Bakery',
        'slug' => 'bakery',
        'blocks' => $this->blocks,
    ]);
    $this->changed = $this->blocks;
    $this->changed[0]['children'][0]['children'][0]['content'] = 'Sourdough every Saturday';
});

// A block whose render always fails, like a widget whose service is down.
function registerBrokenWeatherBlock(): void
{
    app(BlockRegistry::class)->register(new class extends BaseBlock
    {
        public function name(): string
        {
            return 'harbour-weather';
        }

        public function render(BlockData $block, BlockRenderer $renderer): string
        {
            throw new RuntimeException('The weather service did not answer.');
        }
    });
}

// The same failure in a block a plugin renders.
function registerBrokenTidesBlock(): void
{
    app(PluginBlockRegistry::class)->register(new class implements PluginBlock
    {
        public function name(): string
        {
            return 'harbour-tides';
        }

        public function label(): string
        {
            return 'Harbour tides';
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            throw new RuntimeException('The tides service did not answer.');
        }
    });
}

it('keeps a draft for the person without changing the page or adding a revision', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.easy.pages.draft', $this->page), ['blocks' => $this->changed])
        ->assertOk()
        ->assertJsonPath('blocks.0.children.0.children.0.content', 'Sourdough every Saturday')
        ->assertJsonStructure(['blocks', 'savedAt']);

    $this->page->refresh();

    expect($this->page->blocks)->toBe($this->blocks)
        ->and($this->page->revisions()->count())->toBe(0)
        ->and(PageDraft::query()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->putJson(route('admin.easy.pages.draft', $this->page), ['blocks' => $this->blocks])
        ->assertOk();

    expect(PageDraft::query()->count())->toBe(1);
});

it('never shows a draft to visitors', function () {
    app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);

    $this->get($this->page->url())
        ->assertOk()
        ->assertSee('Fresh bread on Harbour Lane')
        ->assertDontSee('Sourdough every Saturday');
});

it('opens the editor with a draft that is newer than the page', function () {
    Carbon::setTestNow(now()->addMinutes(5));
    app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);

    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.blocks.0.children.0.children.0.content', 'Sourdough every Saturday')
            ->has('draft.savedAt')
            ->where('entry.updatedAt', $this->page->updated_at->toIso8601String())
            ->where('entry.isFrontPage', false)
            ->where('autosaveDelay', 2000)
        );

    Carbon::setTestNow();
});

it('drops a draft that is older than the saved page', function () {
    $draft = app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);
    $draft->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertInertia(fn (Assert $page) => $page->where('draft', null));

    expect(PageDraft::query()->count())->toBe(0);
});

it('keeps drafts per person', function () {
    $editor = User::factory()->editor()->create(['name' => 'Rui Almeida']);
    app(PageDrafts::class)->save($this->page, $editor, $this->changed);

    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertInertia(fn (Assert $page) => $page->where('draft', null));
});

it('throws a draft away', function () {
    app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.easy.pages.draft.discard', $this->page))
        ->assertOk();

    expect(PageDraft::query()->count())->toBe(0);
});

it('clears the draft when the page is saved', function () {
    app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.pages.update', $this->page), ['blocks' => $this->changed])
        ->assertRedirect();

    expect(PageDraft::query()->count())->toBe(0)
        ->and($this->page->fresh()->blocks[0]['children'][0]['children'][0]['content'])->toBe('Sourdough every Saturday');
});

it('refuses invalid blocks in a draft with a plain message', function () {
    $this->actingAs($this->admin)
        ->putJson(route('admin.easy.pages.draft', $this->page), ['blocks' => [['id' => 'b_1', 'type' => 'heading', 'attrs' => ['level' => 9], 'content' => 'Hi']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    expect(PageDraft::query()->count())->toBe(0);
});

it('needs the right to change the page for drafts and versions', function () {
    $author = User::factory()->author()->create(['name' => 'Rui Almeida']);

    $this->actingAs($author)->putJson(route('admin.easy.pages.draft', $this->page), ['blocks' => $this->changed])->assertForbidden();
    $this->actingAs($author)->deleteJson(route('admin.easy.pages.draft.discard', $this->page))->assertForbidden();
    $this->actingAs($author)->getJson(route('admin.easy.pages.versions', $this->page))->assertForbidden();

    expect(PageDraft::query()->count())->toBe(0);
});

it('sends guests to the login page for drafts and versions', function () {
    $this->putJson(route('admin.easy.pages.draft', $this->page), ['blocks' => $this->changed])->assertUnauthorized();
    $this->getJson(route('admin.easy.pages.versions', $this->page))->assertUnauthorized();
});

it('declares a capability on every new safety net route', function () {
    foreach (['admin.easy.pages.draft', 'admin.easy.pages.draft.discard', 'admin.easy.pages.versions'] as $name) {
        expect(collect(app('router')->getRoutes()->getByName($name)?->gatherMiddleware())->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))->all())
            ->toContain('can:update,entry');
    }
});

it('lists the earlier versions of the page, newest first, with who and a summary', function () {
    Revision::capture($this->page, $this->admin);
    $this->page->blocks = [$this->blocks[0]];
    $this->page->save();
    Revision::capture($this->page, $this->admin);

    $this->actingAs($this->admin)
        ->getJson(route('admin.easy.pages.versions', $this->page))
        ->assertOk()
        ->assertJsonCount(2, 'versions')
        ->assertJsonPath('versions.0.summary', '1 section')
        ->assertJsonPath('versions.0.author', 'Marta Oliveira')
        ->assertJsonPath('versions.1.summary', '2 sections')
        ->assertJsonPath('versions.1.blocks.0.id', $this->blocks[0]['id']);
});

it('lists only as many versions as the setting allows', function () {
    config(['verso.easy.page_editor.versions' => 2]);

    foreach (range(1, 4) as $step) {
        Revision::capture($this->page, $this->admin);
    }

    $this->actingAs($this->admin)
        ->getJson(route('admin.easy.pages.versions', $this->page))
        ->assertJsonCount(2, 'versions');
});

it('summarises versions in plain words', function () {
    $versions = app(PageVersions::class);

    expect($versions->summary([]))->toBe('Empty page')
        ->and($versions->summary([['type' => 'section']]))->toBe('1 section')
        ->and($versions->summary([['type' => 'section'], ['type' => 'section'], ['type' => 'section']]))->toBe('3 sections');
});

it('restores a version by saving it, and the restore can be undone with the version before', function () {
    $old = $this->blocks;
    $this->actingAs($this->admin)->put(route('admin.easy.pages.update', $this->page), ['blocks' => $this->changed]);

    $version = $this->actingAs($this->admin)->getJson(route('admin.easy.pages.versions', $this->page))->json('versions.0');

    $heading = fn (array $blocks): string => $blocks[0]['children'][0]['children'][0]['content'];

    expect($heading($version['blocks']))->toBe('Fresh bread on Harbour Lane');

    $this->actingAs($this->admin)->putJson(route('admin.easy.pages.update', $this->page), ['blocks' => $version['blocks']]);

    expect($heading($this->page->fresh()->blocks))->toBe($heading($old))
        ->and($this->page->revisions()->count())->toBe(2)
        ->and($heading($this->page->revisions()->first()->blocks))->toBe('Sourdough every Saturday');
});

it('draws the frame when a block fails to render, with a note in its place', function () {
    registerBrokenWeatherBlock();
    $blocks = [...$this->blocks, ['id' => 'b_weather1', 'type' => 'harbour-weather', 'attrs' => [], 'content' => '', 'children' => []]];

    $html = $this->actingAs($this->admin)
        ->post(route('admin.easy.pages.frame', $this->page), ['blocks' => $blocks])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Fresh bread on Harbour Lane')
        ->toContain('data-verso-broken')
        ->toContain('data-verso-block="b_weather1"')
        ->toContain('This part could not be shown');
});

it('keeps the public page up when a stored block fails to render', function () {
    registerBrokenWeatherBlock();
    registerBrokenTidesBlock();
    $this->page->blocks = [
        ...$this->blocks,
        ['id' => 'b_weather1', 'type' => 'harbour-weather', 'attrs' => [], 'content' => '', 'children' => []],
        ['id' => 'b_tides001', 'type' => 'harbour-tides', 'attrs' => [], 'content' => '', 'children' => []],
    ];
    $this->page->save();

    $this->get($this->page->url())
        ->assertOk()
        ->assertSee('Fresh bread on Harbour Lane')
        ->assertDontSee('This part could not be shown')
        ->assertDontSee('data-verso-broken', false);
});

it('keeps the public page up with a block type nobody knows', function () {
    $this->page->blocks = [...$this->blocks, ['id' => 'b_old00001', 'type' => 'retired-slider', 'attrs' => [], 'content' => '', 'children' => []]];
    $this->page->save();

    $this->get($this->page->url())->assertOk()->assertSee('Fresh bread on Harbour Lane');
});

it('refuses unknown blocks in the frame and when saving with a plain message', function () {
    $blocks = [...$this->blocks, ['id' => 'b_old00001', 'type' => 'retired-slider', 'attrs' => [], 'content' => '', 'children' => []]];

    $this->actingAs($this->admin)->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $blocks])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');
    $this->actingAs($this->admin)->putJson(route('admin.easy.pages.update', $this->page), ['blocks' => $blocks])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    expect($this->page->fresh()->blocks)->toBe($this->blocks);
});

it('marks the empty content area in the frame so the editor can put the first section button there', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.easy.pages.frame', $this->page), ['blocks' => []])
        ->assertOk()
        ->assertSee('data-verso-empty', false);

    $this->page->blocks = [];
    $this->page->save();

    $this->get($this->page->url())->assertOk()->assertDontSee('data-verso-empty', false);
});

it('asks before publishing an empty page', function () {
    $draft = Entry::factory()->page()->by($this->admin)->create(['title' => 'Opening soon', 'slug' => 'opening-soon', 'blocks' => $this->blocks]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.easy.pages.update', $draft), ['blocks' => [], 'publish' => true])
        ->assertUnprocessable()
        ->assertJsonPath('errors.blocks.0', 'This page is empty. Add a section first, or say yes to publish an empty page.');

    expect($draft->fresh()->isPublished())->toBeFalse();

    $this->actingAs($this->admin)
        ->put(route('admin.easy.pages.update', $draft), ['blocks' => [], 'publish' => true, 'confirmEmpty' => true])
        ->assertRedirect();

    expect($draft->fresh()->isPublished())->toBeTrue();
});

it('saves an empty draft without asking', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.easy.pages.update', $this->page), ['blocks' => []])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->page->fresh()->blocks)->toBe([]);
});

it('tells the editor when the page is the home page', function () {
    app(Settings::class)->set('reading.front_page', $this->page->id);

    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertInertia(fn (Assert $page) => $page->where('entry.isFrontPage', true));
});

it('removes drafts with their page and their person', function () {
    $editor = User::factory()->editor()->create(['name' => 'Rui Almeida']);
    app(PageDrafts::class)->save($this->page, $this->admin, $this->changed);
    app(PageDrafts::class)->save($this->page, $editor, $this->changed);

    $editor->delete();
    expect(PageDraft::query()->count())->toBe(1);

    $this->page->forceDelete();
    expect(PageDraft::query()->count())->toBe(0);
});

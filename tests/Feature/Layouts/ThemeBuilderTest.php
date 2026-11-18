<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Settings\Settings;
use App\Verso\Layouts\Conditions\ConditionRegistry;
use App\Verso\Layouts\Conditions\PageContext;
use App\Verso\Layouts\LayoutKind;
use App\Verso\Layouts\LayoutSettings;
use App\Verso\Layouts\LayoutStore;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Layouts\Models\SiteLayoutRevision;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function liveLayout(LayoutKind $kind, string $name, array $blocks, array $conditions, array $settings = [], int $priority = 0): SiteLayout
{
    $layout = SiteLayout::query()->create([
        'kind' => $kind, 'name' => $name, 'blocks' => [], 'conditions' => [], 'settings' => [], 'status' => SiteLayout::DRAFT,
    ]);

    return app(LayoutStore::class)->publish($layout, ['blocks' => $blocks, 'conditions' => $conditions, 'settings' => $settings, 'priority' => $priority], null);
}

function rule(string $type, string $value = '', bool $exclude = false): array
{
    return ['type' => $type, 'value' => $value, 'exclude' => $exclude];
}

it('scores rules by how exact they are and honours exceptions', function () {
    $conditions = app(ConditionRegistry::class);
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Winter soups']);
    $page = new PageContext(kind: 'entry', entry: $post);

    expect($conditions->score([rule('everywhere')], $page))->toBe(0)
        ->and($conditions->score([rule('content_type', 'post')], $page))->toBe(30)
        ->and($conditions->score([rule('content_type', 'post'), rule('entry', (string) $post->id)], $page))->toBe(100)
        ->and($conditions->score([rule('content_type', 'page')], $page))->toBeNull()
        ->and($conditions->score([rule('everywhere'), rule('entry', (string) $post->id, true)], $page))->toBeNull()
        ->and($conditions->score([rule('page_kind', 'search')], new PageContext(kind: 'search')))->toBe(10);
});

it('matches terms on entries and on term pages, and visitors by sign in', function () {
    $conditions = app(ConditionRegistry::class);
    $recipes = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Recipes', 'slug' => 'recipes']);
    $post = Entry::factory()->published()->create(['type' => 'post']);
    $post->terms()->attach($recipes);
    $post->load('terms');

    expect($conditions->score([rule('term', (string) $recipes->id)], new PageContext(kind: 'entry', entry: $post)))->toBe(60)
        ->and($conditions->score([rule('term', (string) $recipes->id)], new PageContext(kind: 'taxonomy', term: $recipes)))->toBe(60)
        ->and($conditions->score([rule('visitor', 'guest')], new PageContext(kind: 'home')))->toBe(5)
        ->and($conditions->score([rule('visitor', 'guest')], new PageContext(kind: 'home', viewer: $this->admin)))->toBeNull()
        ->and($conditions->score([rule('role', 'admin')], new PageContext(kind: 'home', viewer: $this->admin)))->toBe(20);
});

it('cleans layout options and keeps their defaults', function () {
    expect(LayoutSettings::clean(LayoutKind::Header, ['sticky' => true, 'scrollEffect' => 'explode', 'scrollOffset' => 99999, 'extra' => 'x']))
        ->toBe(['sticky' => true, 'transparent' => false, 'scrollEffect' => 'none', 'scrollOffset' => 2000])
        ->and(LayoutSettings::clean(LayoutKind::Popup, ['devices' => ['phone', 'watch']])['devices'])->toBe(['phone']);
});

it('draws posts with the template whose rules fit, inside the theme', function () {
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Harbor walk at dawn', 'slug' => 'harbor-walk', 'blocks' => [
        ['id' => 'b_p', 'type' => 'paragraph', 'content' => 'The fog lifts at seven.'],
    ]]);
    liveLayout(LayoutKind::Template, 'Story template', [
        ['id' => 'b_t', 'type' => 'post-title', 'attrs' => ['level' => '1']],
        ['id' => 'b_r', 'type' => 'post-reading-time'],
        ['id' => 'b_c', 'type' => 'post-content'],
    ], [rule('content_type', 'post')]);

    $html = $this->get($post->url())->assertOk()->getContent();

    expect($html)->toContain('<h1 class="verso-post-title">Harbor walk at dawn</h1>')
        ->toContain('1 minute read')
        ->toContain('The fog lifts at seven.')
        ->toContain('data-verso-layout=')
        ->toContain('<main');
});

it('leaves pages without a fitting template to the theme', function () {
    $page = Entry::factory()->published()->create(['type' => 'page', 'title' => 'Visit us', 'slug' => 'visit-us']);
    liveLayout(LayoutKind::Template, 'Story template', [['id' => 'b_t', 'type' => 'post-title']], [rule('content_type', 'post')]);

    expect($this->get($page->url())->assertOk()->getContent())->not->toContain('data-verso-layout=');
});

it('picks the higher priority, then the more exact rule', function () {
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Market day', 'slug' => 'market-day']);
    liveLayout(LayoutKind::Template, 'General', [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'GENERAL']], [rule('everywhere')]);
    liveLayout(LayoutKind::Template, 'Exact', [['id' => 'b_2', 'type' => 'paragraph', 'content' => 'EXACT']], [rule('entry', (string) $post->id)]);

    expect($this->get($post->url())->getContent())->toContain('EXACT');

    liveLayout(LayoutKind::Template, 'Pinned', [['id' => 'b_3', 'type' => 'paragraph', 'content' => 'PINNED']], [rule('everywhere')], priority: 5);

    expect($this->get($post->url())->getContent())->toContain('PINNED');
});

it('lists the entries of an archive with a query loop and its pages', function () {
    Entry::factory()->count(3)->published()->sequence(['title' => 'Sourdough basics'], ['title' => 'Rye starter'], ['title' => 'Spelt rolls'])->create(['type' => 'post']);
    liveLayout(LayoutKind::Template, 'Blog list', [
        ['id' => 'b_h', 'type' => 'archive-title'],
        ['id' => 'b_q', 'type' => 'query-loop', 'attrs' => ['source' => 'page'], 'children' => [['id' => 'b_t', 'type' => 'post-title', 'attrs' => ['link' => true]]]],
    ], [rule('page_kind', 'archive')]);

    $html = $this->get('/blog')->assertOk()->getContent();

    expect($html)->toContain('<h1 class="verso-archive-title">Posts</h1>')
        ->toContain('Sourdough basics')
        ->toContain('Spelt rolls')
        ->toContain('verso-query__items');
});

it('pages through its own query and leaves out the entry being shown', function () {
    $entries = Entry::factory()->count(5)->published()->sequence(fn ($sequence) => ['title' => 'Loaf '.($sequence->index + 1), 'published_at' => now()->subDays($sequence->index)])->create(['type' => 'post']);
    $page = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'bakes', 'title' => 'All bakes', 'blocks' => [
        ['id' => 'b_loop', 'type' => 'query-loop', 'attrs' => ['type' => 'post', 'count' => 2, 'pagination' => true, 'layout' => 'list'], 'children' => [['id' => 'b_t', 'type' => 'post-title']]],
    ]]);

    $first = $this->get($page->url())->assertOk()->getContent();
    $second = $this->get($page->url().'?page-bloop=2')->assertOk()->getContent();

    expect($first)->toContain('Loaf 1')->toContain('Loaf 2')->not->toContain('Loaf 3')
        ->toContain('rel="next"')
        ->and($second)->toContain('Loaf 3')->toContain('aria-current="page"');
});

it('swaps the theme header for a header layout and keeps the theme footer', function () {
    liveLayout(LayoutKind::Header, 'Shop header', [['id' => 'b_s', 'type' => 'site-title']], [rule('everywhere')], ['sticky' => true, 'scrollEffect' => 'solid']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('class="site-part site-part-header verso-header verso-header--sticky"')
        ->toContain('data-scroll="solid"')
        ->toContain('verso-blocks/layout-header.js')
        ->toContain('site-footer');
});

it('stops showing a layout that is turned off and empties the page cache on changes', function () {
    $layout = liveLayout(LayoutKind::Header, 'Old header', [['id' => 'b_s', 'type' => 'site-title']], [rule('everywhere')]);
    expect($this->get('/')->getContent())->toContain('verso-header');

    app(LayoutStore::class)->switch($layout, false);

    expect($this->get('/')->getContent())->not->toContain('verso-header');
});

it('keeps the theme builder for people with the right capability', function () {
    $editor = User::factory()->editor()->create();
    $layout = liveLayout(LayoutKind::Popup, 'Newsletter', [], [rule('everywhere')]);

    $this->actingAs($editor)->get(route('admin.theme-builder.index'))->assertForbidden();

    $designer = User::factory()->editor()->create();
    DB::table('user_capabilities')->insert(['user_id' => $designer->id, 'capability' => Capability::EditPopups->value, 'granted' => true]);

    $this->actingAs($designer)->get(route('admin.theme-builder.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/site-editor/layouts/index')->where('kinds.0.value', 'popup')->has('kinds', 1));
    $this->actingAs($designer)->get(route('admin.theme-builder.edit', $layout))->assertOk();
    $this->actingAs($designer)->post(route('admin.theme-builder.store'), ['kind' => 'header', 'name' => 'Sneaky'])->assertSessionHasErrors('kind');
});

it('creates a layout from a starter, saves drafts and publishes', function () {
    $this->actingAs($this->admin)->post(route('admin.theme-builder.store'), ['kind' => 'template', 'name' => 'Recipe page'])->assertRedirect();
    $layout = SiteLayout::query()->firstOrFail();

    expect(array_column($layout->blocks[0]['children'], 'type'))->toContain('post-title', 'post-content')
        ->and($layout->conditions)->toBe([rule('page_kind', 'entry')]);

    $this->put(route('admin.theme-builder.update', $layout), ['conditions' => [rule('content_type', 'post')], 'settings' => ['width' => 'content']])->assertRedirect();
    expect($layout->fresh())->status->toBe('draft')->published_blocks->toBeNull();

    $this->post(route('admin.theme-builder.publish', $layout), ['blocks' => $layout->blocks])->assertRedirect();
    expect($layout->fresh())->status->toBe('published')->and($layout->revisions()->count())->toBe(1);
});

it('refuses unknown rules and odd values', function () {
    $layout = liveLayout(LayoutKind::Footer, 'Footer', [], [rule('everywhere')]);

    $this->actingAs($this->admin)->put(route('admin.theme-builder.update', $layout), ['conditions' => [rule('moon_phase', 'full')]])->assertSessionHasErrors('conditions');
    $this->actingAs($this->admin)->put(route('admin.theme-builder.update', $layout), ['conditions' => [rule('page_kind', 'galaxy')]])->assertSessionHasErrors('conditions');
});

it('previews the working copy on a chosen page without publishing', function () {
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Croissant lamination']);
    $layout = SiteLayout::query()->create([
        'kind' => LayoutKind::Template, 'name' => 'Draft template', 'status' => SiteLayout::DRAFT, 'settings' => [],
        'blocks' => [['id' => 'b_t', 'type' => 'post-title'], ['id' => 'b_p', 'type' => 'paragraph', 'content' => 'DRAFT ONLY']],
        'conditions' => [rule('entry', '999999')],
    ]);

    $this->actingAs($this->admin)->get(route('admin.theme-builder.preview', ['layout' => $layout, 'target' => 'entry:'.$post->id]))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee('DRAFT ONLY', false);

    // A new request starts without the preview, like every real request.
    app()->forgetScopedInstances();

    expect($this->get($post->url())->getContent())->not->toContain('DRAFT ONLY');
});

it('draws the preview with the views of the active theme, like the public page', function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Rye and caraway loaf']);
    $layout = SiteLayout::query()->create([
        'kind' => LayoutKind::Template, 'name' => 'Ashgrove template', 'status' => SiteLayout::DRAFT, 'settings' => [],
        'blocks' => [['id' => 'b_t', 'type' => 'post-title']],
        'conditions' => [rule('everywhere')],
    ]);

    $html = (string) $this->actingAs($this->admin)
        ->get(route('admin.theme-builder.preview', ['layout' => $layout, 'target' => 'entry:'.$post->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<a class="brand"')
        ->and($html)->toContain('Rye and caraway loaf');
});

it('puts an older version back into the working copy', function () {
    $layout = liveLayout(LayoutKind::Footer, 'Footer', [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'First']], [rule('everywhere')]);
    app(LayoutStore::class)->publish($layout, ['blocks' => [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Second']]], null);
    $first = SiteLayoutRevision::query()->where('site_layout_id', $layout->id)->orderBy('id')->firstOrFail();

    $this->actingAs($this->admin)->post(route('admin.theme-builder.restore', ['layout' => $layout, 'revision' => $first]))->assertRedirect();

    expect($layout->fresh()->blocks[0]['content'])->toBe('First')
        ->and($layout->fresh()->published_blocks[0]['content'])->toBe('Second');
});

it('opens the editor of a layout with conditions, preview pages and blocks', function () {
    $layout = liveLayout(LayoutKind::Header, 'Main header', [], [rule('everywhere')]);

    $this->actingAs($this->admin)->get(route('admin.theme-builder.edit', $layout))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-editor/layouts/edit')
            ->where('layout.kind', 'header')
            ->where('layout.settings.scrollEffect', 'none')
            ->where('conditions', fn ($conditions) => collect($conditions)->pluck('key')->contains('term'))
            ->where('siteBlocks', fn ($blocks) => collect($blocks)->pluck('name')->contains('mega-menu') || collect($blocks)->pluck('name')->contains('site-logo'))
            ->where('previewTargets.0.value', 'home'));
});

it('marks the preview pages the rules of the layout pick, so the editor opens on one of them', function () {
    $post = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Croissant lamination']);
    $about = Entry::factory()->published()->create(['type' => 'page', 'title' => 'About the bakery']);
    $template = liveLayout(LayoutKind::Template, 'Single post', [], [rule('content_type', 'post')]);

    $this->actingAs($this->admin)->get(route('admin.theme-builder.edit', $template))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('previewTargets.0.value', 'home')
            ->where('previewTargets.0.usesLayout', false)
            ->where('previewTargets', fn ($targets) => collect($targets)->firstWhere('value', 'entry:'.$post->id)['usesLayout'] === true
                && collect($targets)->firstWhere('value', 'entry:'.$about->id)['usesLayout'] === false
                && collect($targets)->firstWhere('value', 'archive:post')['usesLayout'] === true)
            ->where('previewDesktopMinWidth', config('verso.site_parts.preview_desktop_min_width')));
});

it('adds the entry of a "one entry" rule to the preview pages and counts signed in rules for the viewer', function () {
    $older = Entry::factory()->published()->count(4)->create(['type' => 'post', 'published_at' => now()->subYear()])->first();
    Entry::factory()->published()->count(3)->create(['type' => 'post']);
    $template = liveLayout(LayoutKind::Template, 'One old post', [], [rule('entry', (string) $older->id)]);
    $members = liveLayout(LayoutKind::Header, 'Members header', [], [rule('visitor', 'signed_in')]);

    $this->actingAs($this->admin)->get(route('admin.theme-builder.edit', $template))
        ->assertInertia(fn (Assert $page) => $page->where('previewTargets', fn ($targets) => collect($targets)->firstWhere('value', 'entry:'.$older->id)['usesLayout'] === true));

    $this->actingAs($this->admin)->get(route('admin.theme-builder.edit', $members))
        ->assertInertia(fn (Assert $page) => $page->where('previewTargets.0.usesLayout', true));
});

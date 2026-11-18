<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Models\SitePart;
use App\Verso\Site\Parts\SitePartStatus;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\ThemeManager;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    app(Settings::class)->set('site.name', 'Kvarnholmen Bakery');
    $this->admin = User::factory()->admin()->create(['name' => 'Ingrid Solberg']);
    $this->headerBlocks = [
        ['id' => 'bar', 'type' => 'announcement-bar', 'attrs' => ['text' => 'Saffron buns are back for Lucia']],
        ['id' => 'row', 'type' => 'section', 'attrs' => ['tag' => 'div', 'design' => ['layout' => ['display' => 'flex', 'justify' => 'between']]], 'children' => [
            ['id' => 'logo', 'type' => 'site-logo'],
            ['id' => 'menu', 'type' => 'navigation', 'attrs' => ['location' => 'primary', 'label' => 'Main']],
        ]],
    ];
});

it('lists the parts of the active theme in plain words', function () {
    app(SitePartStore::class)->publish('ashgrove', 'header', $this->headerBlocks);
    app(SitePartStore::class)->saveDraft('ashgrove', 'footer', [['id' => 'c', 'type' => 'copyright']]);

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-editor/index')
            ->where('theme.name', 'Ashgrove')
            ->where('parts', fn ($parts) => collect($parts)->pluck('label', 'key')->all() === ['author-box' => 'Author Box', 'footer' => 'Footer', 'footer-four-columns' => 'Footer: Four Columns', 'header' => 'Header', 'header-centered' => 'Header: Centered', 'header-minimal' => 'Header: Minimal'])
            ->where('parts.0.status', 'original')
            ->where('parts.1.status', 'unpublished')
            ->where('parts.3.status', 'published')
            ->where('parts.3.revisions', 1)
        );
});

it('opens a part with starter blocks and the editor settings', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.edit', 'header'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-editor/edit')
            ->where('part.label', 'Header')
            ->where('part.status', 'original')
            ->where('blocks.0.type', 'section')
            ->where('blocks.0.children.0.type', 'site-logo')
            ->where('blocks.0.children.1.attrs.location', 'primary')
            ->where('siteBlocks.0.name', 'section')
            ->where('siteBlocks.0.children', true)
            ->where('siteBlocks.1.name', 'site-logo')
            ->where('previewUrl', route('admin.site-editor.preview', ['styles' => ['header' => 'classic']]))
            ->where('shownWith', null)
            ->where('previewWidths', ['desktop' => null, 'tablet' => 768, 'phone' => 390])
            ->where('autosaveDelay', 1200)
        );
});

it('opens the saved draft when there is one', function () {
    app(SitePartStore::class)->saveDraft('ashgrove', 'header', $this->headerBlocks);

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.edit', 'header'))
        ->assertInertia(fn (Assert $page) => $page->where('blocks.0.attrs.text', 'Saffron buns are back for Lucia')->where('part.status', 'unpublished'));
});

it('returns 404 for a part the theme does not ship', function () {
    $this->actingAs($this->admin)->get(route('admin.site-editor.edit', 'sidebar'))->assertNotFound();
    $this->actingAs($this->admin)->put(route('admin.site-editor.update', '..%2Fsecrets'), ['blocks' => []])->assertNotFound();
});

it('saves a draft that visitors do not see yet', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site-editor.update', 'header'), ['blocks' => $this->headerBlocks])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $part = SitePart::query()->firstOrFail();

    expect($part->status)->toBe(SitePartStatus::Draft)->and($part->user_id)->toBe($this->admin->id);

    auth()->logout();
    $this->get('/')->assertOk()->assertDontSee('Saffron buns are back for Lucia');
});

it('shows saved drafts in the preview only', function () {
    app(SitePartStore::class)->saveDraft('ashgrove', 'header', $this->headerBlocks);

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.preview'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee('Saffron buns are back for Lucia')
        ->assertSee('data-site-part="header"', false);
});

it('publishes a part, empties the page cache and shows it to visitors', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $version = app(PageCache::class)->version();

    $this->actingAs($this->admin)
        ->post(route('admin.site-editor.publish', 'header'), ['blocks' => $this->headerBlocks])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Header published. Visitors see it now.');

    expect(app(PageCache::class)->version())->toBe($version + 1);

    auth()->logout();
    $this->get('/')->assertOk()->assertSee('Saffron buns are back for Lucia')->assertDontSee('class="site-header"', false);
});

it('restores the theme original', function () {
    app(SitePartStore::class)->publish('ashgrove', 'header', $this->headerBlocks);

    $this->actingAs($this->admin)
        ->delete(route('admin.site-editor.destroy', 'header'))
        ->assertRedirect(route('admin.site-editor.edit', 'header'));

    expect(SitePart::query()->count())->toBe(0);

    auth()->logout();
    $this->get('/')->assertOk()->assertSee('class="site-header"', false)->assertDontSee('Saffron buns');
});

it('rejects invalid blocks', function (array $blocks) {
    $this->actingAs($this->admin)
        ->from(route('admin.site-editor.edit', 'header'))
        ->post(route('admin.site-editor.publish', 'header'), ['blocks' => $blocks])
        ->assertSessionHasErrors('blocks');

    expect(SitePart::query()->count())->toBe(0);
})->with([
    'unknown block' => [[['id' => 'x', 'type' => 'marquee']]],
    'unsafe style' => [[['id' => 'x', 'type' => 'section', 'attrs' => ['design' => ['size' => ['width' => '1px;position:fixed']]]]]],
    'bad link' => [[['id' => 'x', 'type' => 'announcement-bar', 'attrs' => ['text' => 'Hi', 'url' => 'javascript:alert(1)']]]],
]);

it('lets only theme managers use the site editor', function () {
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.site-editor'))->assertForbidden();
    $this->actingAs($author)->get(route('admin.site-editor.preview'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.site-editor.publish', 'header'), ['blocks' => []])->assertForbidden();
    $this->actingAs($author)->delete(route('admin.site-editor.destroy', 'header'))->assertForbidden();
});

it('adds the site editor to the site menu of the admin', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => str_contains(json_encode($nav), '"siteEditor"')));
});

it('lists the header and footer styles of the theme', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('styles.0.name', 'header')
            ->where('styles.0.chosen', 'classic')
            ->where('styles.0.variants.1', ['key' => 'centered', 'label' => 'Centered', 'description' => 'Logo in the middle with the menu below it.', 'part' => 'header-centered'])
            ->where('styles.1.name', 'footer')
            ->where('styles.1.variants.1.label', 'Four columns')
            ->where('previewUrl', route('admin.site-editor.preview'))
        );
});

it('previews a style without saving it', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.preview', ['styles' => ['header' => 'centered', 'footer' => 'four-columns']]))
        ->assertOk()
        ->assertSee('class="site-header is-centered"', false)
        ->assertSee('class="site-footer is-columns"', false);

    app()->forgetScopedInstances();

    $this->actingAs($this->admin)->get(route('admin.site-editor.preview'))->assertDontSee('class="site-header is-centered"', false);
});

it('saves a style, empties the page cache and shows it to visitors', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $version = app(PageCache::class)->version();

    $this->actingAs($this->admin)
        ->put(route('admin.site-editor.styles'), ['part' => 'header', 'style' => 'centered'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Header style changed to Centered.');

    expect(app(Settings::class)->get('theme.part_variants.ashgrove'))->toBe(['header' => 'centered'])
        ->and(app(PageCache::class)->version())->toBeGreaterThan($version);

    auth()->logout();
    $this->get('/')->assertOk()->assertSee('class="site-header is-centered"', false);
});

it('refuses a style the theme does not offer', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.site-editor'))
        ->put(route('admin.site-editor.styles'), ['part' => 'header', 'style' => 'floating'])
        ->assertSessionHasErrors('style');

    expect(app(Settings::class)->get('theme.part_variants.ashgrove'))->toBeNull();
});

it('uses an edited style part when the style is chosen', function () {
    app(SitePartStore::class)->publish('ashgrove', 'header-centered', [['id' => 'n', 'type' => 'announcement-bar', 'attrs' => ['text' => 'Centered and edited']]]);
    app(SitePartStore::class)->publish('ashgrove', 'header', [['id' => 'n', 'type' => 'announcement-bar', 'attrs' => ['text' => 'Classic and edited']]]);

    $this->get('/')->assertSee('Classic and edited')->assertDontSee('Centered and edited');

    app(PartVariants::class)->choose(app(ThemeManager::class)->active(), 'header', 'centered');
    app()->forgetScopedInstances();

    $this->get('/')->assertSee('Centered and edited')->assertDontSee('Classic and edited');
});

it('sends the colors, fonts and other tokens to the styles panel', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('globalStyles.groups.0.name', 'color')
            ->where('globalStyles.groups.0.tokens.0', ['id' => 'color.accent', 'kind' => 'color', 'label' => 'Accent', 'help' => 'Links, buttons and category names.', 'default' => '#2e5e4e', 'value' => null])
            ->where('globalStyles.groups.1.name', 'font')
            ->where('globalStyles.values', fn ($values) => $values['color.accent'] === '#2e5e4e')
            ->where('globalStyles.fonts', fn ($fonts) => collect($fonts)->pluck('value')->contains('newsreader'))
            ->where('globalStyles.groups', fn ($groups) => collect($groups)->firstWhere('name', 'radius')['tokens'][0]['value']['negative'] === false)
            ->where('globalStyles.units', null)
        );
});

it('saves colors and fonts and keeps the customizer in step', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site-editor.global-styles'), ['values' => ['color.accent' => '#8a3b12', 'color.surface' => '#f2ece2', 'font.ui' => 'system']])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Colors and fonts saved.');

    expect(app(Settings::class)->get('theme.ashgrove.accent_color'))->toBe('#8a3b12')
        ->and(app(Settings::class)->get('theme.styles.ashgrove'))->toEqual(['color.surface' => '#f2ece2', 'font.ui' => 'system']);
});

it('explains which style value is not allowed', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.site-editor'))
        ->put(route('admin.site-editor.global-styles'), ['values' => ['radius.base' => '3px; color: red']])
        ->assertSessionHasErrors(['values.radius.base' => 'Corners: this value is not allowed.']);
});

it('previews colors before they are saved', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.preview', ['tokens' => ['color.accent' => '#5b2a86', 'color.surface' => '#efe8f7']]))
        ->assertOk()
        ->assertSee('--accent-light: #5b2a86;', false)
        ->assertSee('--surface-light: #efe8f7;', false);

    expect(app(Settings::class)->get('theme.ashgrove.accent_color'))->toBeNull();
});

it('previews the style that uses the edited part and says when another style is in use', function () {
    app(PartVariants::class)->choose(app(ThemeManager::class)->active(), 'header', 'centered');

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.edit', 'header'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('previewUrl', route('admin.site-editor.preview', ['styles' => ['header' => 'classic']]))
            ->where('shownWith', ['inUse' => 'Centered', 'thisStyle' => 'Classic'])
        );

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.edit', 'header-centered'))
        ->assertInertia(fn (Assert $page) => $page->where('shownWith', null));
});

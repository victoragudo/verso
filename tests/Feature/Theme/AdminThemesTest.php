<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->themesPath = storage_path('framework/testing/themes-'.uniqid());
    File::copyDirectory(base_path('themes/default'), $this->themesPath.'/default');
    File::ensureDirectoryExists($this->themesPath.'/harbour/views');
    File::put($this->themesPath.'/harbour/theme.json', json_encode([
        'name' => 'Harbour',
        'version' => '2.1.0',
        'description' => 'A bright theme for travel journals.',
        'author' => 'Nuno Almeida',
    ]));
    File::put($this->themesPath.'/harbour/views/home.blade.php', "@extends('theme::layouts.app')\n@section('content')<p>Harbour home page</p>@endsection");

    config(['verso.themes.path' => str_replace(base_path().'/', '', $this->themesPath)]);
    app()->forgetInstance(ThemeManager::class);

    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    File::deleteDirectory($this->themesPath);
});

it('lists installed themes and marks the active one', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.themes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/theme/index')
            ->has('themes', 2)
            ->where('themes.0.slug', 'harbour')
            ->where('themes.0.version', '2.1.0')
            ->where('themes.0.author', 'Nuno Almeida')
            ->where('themes.0.active', false)
            ->where('themes.1.slug', 'default')
            ->where('themes.1.active', true)
            ->where('pageCache.enabled', (bool) config('verso.page_cache.enabled'))
        );
});

it('activates a theme and uses it on the site', function () {
    config(['verso.page_cache.enabled' => false]);

    $this->actingAs($this->admin)
        ->from(route('admin.themes.index'))
        ->post(route('admin.themes.activate', 'harbour'))
        ->assertRedirect(route('admin.themes.index'));

    expect(app(Settings::class)->get('theme.active'))->toBe('harbour');

    auth()->logout();
    $this->get('/')->assertOk()->assertSee('Harbour home page');
});

it('falls back to the default views the active theme does not have', function () {
    config(['verso.page_cache.enabled' => false]);
    app(Settings::class)->set('theme.active', 'harbour');

    $this->get('/search')->assertOk()->assertSee('name="q"', false);
    $this->get('/missing-page')->assertNotFound()->assertSee('This page could not be found');
});

it('returns 404 for unknown themes', function () {
    $this->actingAs($this->admin)->post(route('admin.themes.activate', 'neon-nights'))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.themes.activate', '.hidden'))->assertNotFound();

    expect(app(Settings::class)->get('theme.active'))->toBe('default');
});

it('clears the page cache', function () {
    $cache = app(PageCache::class);
    $version = $cache->version();

    $this->actingAs($this->admin)
        ->post(route('admin.themes.cache.clear'))
        ->assertRedirect();

    expect($cache->version())->toBe($version + 1);
});

it('is only for users who can manage themes', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.themes.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.themes.activate', 'harbour'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.themes.cache.clear'))->assertForbidden();
});

it('asks guests to sign in', function () {
    $this->get(route('admin.themes.index'))->assertRedirect(route('login'));
});

it('adds the themes item to the admin nav', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.themes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($items) => collect($items)->contains('key', 'themes')));
});

describe('child themes', function () {
    beforeEach(function () {
        File::ensureDirectoryExists($this->themesPath.'/harbour-dusk/views/parts');
        File::put($this->themesPath.'/harbour-dusk/theme.json', json_encode([
            'name' => 'Harbour Dusk',
            'parent' => 'harbour',
            'templates' => ['landing' => ['label' => 'Landing page']],
        ]));
        File::put($this->themesPath.'/harbour-dusk/views/parts/header-dark.blade.php', '<header>Dusk</header>');
        File::ensureDirectoryExists($this->themesPath.'/lonely-dock');
        File::put($this->themesPath.'/lonely-dock/theme.json', json_encode(['name' => 'Lonely Dock', 'parent' => 'missing-pier']));
        app(ThemeManager::class)->refresh();
    });

    it('shows the parent theme and what each theme ships', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.themes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('themes', 4)
                ->where('themes.1.slug', 'harbour-dusk')
                ->where('themes.1.parent', 'harbour')
                ->where('themes.1.parentName', 'Harbour')
                ->where('themes.1.missingParent', null)
                ->where('themes.1.templates', 1)
                ->where('themes.1.patterns', 0)
                ->where('themes.1.parts', 1)
                ->where('themes.2.slug', 'lonely-dock')
                ->where('themes.2.parentName', null)
                ->where('themes.2.missingParent', 'missing-pier')
                ->where('themes.3.slug', 'default')
                ->where('themes.3.parent', null)
                ->where('themes.3.templates', 2)
                ->where('themes.3.patterns', 3)
                ->where('themes.3.parts', 3)
            );
    });

    it('activates a child theme and renders the parent views', function () {
        config(['verso.page_cache.enabled' => false]);

        $this->actingAs($this->admin)->post(route('admin.themes.activate', 'harbour-dusk'))->assertRedirect();

        expect(app(Settings::class)->get('theme.active'))->toBe('harbour-dusk');

        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Harbour home page');
    });

    it('refuses to activate a child theme without its parent', function () {
        $this->actingAs($this->admin)
            ->from(route('admin.themes.index'))
            ->post(route('admin.themes.activate', 'lonely-dock'))
            ->assertRedirect(route('admin.themes.index'))
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'The parent theme [missing-pier] of [lonely-dock] is not installed.']);

        expect(app(Settings::class)->get('theme.active'))->not->toBe('lonely-dock');
    });

});

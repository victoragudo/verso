<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->base = storage_path('framework/testing/themes-'.uniqid());

    $make = function (string $slug, array $manifest, array $files = []): void {
        File::ensureDirectoryExists($this->base.'/'.$slug.'/views');
        File::put($this->base.'/'.$slug.'/theme.json', json_encode($manifest));

        foreach ($files as $file => $content) {
            File::ensureDirectoryExists(dirname($this->base.'/'.$slug.'/'.$file));
            File::put($this->base.'/'.$slug.'/'.$file, $content);
        }
    };

    $make('default', ['name' => 'Verso Default'], [
        'views/home.blade.php' => 'Default home',
        'views/search.blade.php' => 'Default search',
        'assets/theme.css' => 'body { color: black; }',
    ]);
    $make('aurora', ['name' => 'Aurora'], ['views/home.blade.php' => 'Aurora home']);
    $make('meadow', ['name' => 'Meadow'], ['assets/theme.css' => 'body { color: green; }']);
    File::ensureDirectoryExists($this->base.'/no-manifest');
    File::ensureDirectoryExists($this->base.'/Bad Name');
    File::put($this->base.'/Bad Name/theme.json', '{}');

    $this->manager = new ThemeManager(app(Settings::class), app('view'), $this->base, 'default');
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

it('lists valid themes sorted by name', function () {
    expect(array_keys($this->manager->all()))->toBe(['aurora', 'meadow', 'default']);
});

it('finds themes by slug and rejects unsafe slugs', function () {
    expect($this->manager->find('aurora')?->name)->toBe('Aurora')
        ->and($this->manager->find('no-manifest'))->toBeNull()
        ->and($this->manager->find('../default'))->toBeNull()
        ->and($this->manager->find('Bad Name'))->toBeNull()
        ->and($this->manager->exists('meadow'))->toBeTrue()
        ->and($this->manager->exists('volcano'))->toBeFalse();
});

it('uses the theme from settings', function () {
    app(Settings::class)->set('theme.active', 'aurora');

    expect($this->manager->active()->slug)->toBe('aurora');
});

it('falls back when the active theme is missing', function () {
    app(Settings::class)->set('theme.active', 'deleted-theme');

    expect($this->manager->active()->slug)->toBe('default');
});

it('has no fallback theme when it was removed', function () {
    $manager = new ThemeManager(app(Settings::class), app('view'), $this->base, 'classic');

    expect($manager->fallback())->toBeNull();
});

it('uses the first installed theme when the chosen and the fallback themes are gone', function () {
    app(Settings::class)->set('theme.active', 'deleted-theme');
    $manager = new ThemeManager(app(Settings::class), app('view'), $this->base, 'classic');

    expect($manager->active()->slug)->toBe('aurora');
});

it('fails when no theme is installed at all', function () {
    $empty = storage_path('framework/testing/themes-empty-'.uniqid());
    File::ensureDirectoryExists($empty);
    $manager = new ThemeManager(app(Settings::class), app('view'), $empty, 'default');

    try {
        $manager->active();
    } finally {
        File::deleteDirectory($empty);
    }
})->throws(RuntimeException::class, 'No theme is installed');

it('leaves the fallback views out of the lookup when the fallback theme is gone', function () {
    app(Settings::class)->set('theme.active', 'aurora');
    $manager = new ThemeManager(app(Settings::class), app('view'), $this->base, 'classic');

    expect($manager->viewPaths())->toBe([$this->base.'/aurora/views']);
});

it('lists the required views a theme does not have', function () {
    expect($this->manager->missingRequiredViews($this->manager->find('default')))
        ->toBe(['layouts/app', 'entry', 'archive', 'taxonomy', '404']);
});

it('activates a theme and saves it', function () {
    $theme = $this->manager->activate('aurora');

    expect($theme->slug)->toBe('aurora')
        ->and(app(Settings::class)->get('theme.active'))->toBe('aurora')
        ->and(view('theme::home')->render())->toBe('Aurora home');
});

it('refuses to activate an unknown theme', function () {
    $this->manager->activate('volcano');
})->throws(InvalidArgumentException::class, 'Unknown theme [volcano]');

it('falls back to the default views', function () {
    $this->manager->activate('aurora');

    expect($this->manager->viewPaths())->toBe([$this->base.'/aurora/views', $this->base.'/default/views'])
        ->and(view('theme::search')->render())->toBe('Default search');
});

it('lists the default views only once when it is active', function () {
    expect($this->manager->viewPaths())->toBe([$this->base.'/default/views']);
});

it('registers the fallback views without the database', function () {
    $this->manager->registerFallbackViews();

    expect(view('theme::home')->render())->toBe('Default home');
});

it('reads the styles of the active theme with a fallback', function () {
    expect($this->manager->styles())->toBe('body { color: black; }');

    app(Settings::class)->set('theme.active', 'meadow');
    expect($this->manager->styles())->toBe('body { color: green; }');

    app(Settings::class)->set('theme.active', 'aurora');
    expect($this->manager->styles())->toBe('body { color: black; }');
});

it('reads the folders again after a refresh', function () {
    expect($this->manager->all())->toHaveCount(3);

    File::ensureDirectoryExists($this->base.'/coral');
    File::put($this->base.'/coral/theme.json', '{"name": "Coral"}');

    expect($this->manager->all())->toHaveCount(3);

    $this->manager->refresh();

    expect($this->manager->all())->toHaveCount(4)
        ->and($this->manager->basePath())->toBe($this->base);
});

it('ships a valid default theme', function () {
    $theme = app(ThemeManager::class)->fallback();

    expect($theme->slug)->toBe('default')
        ->and($theme->hasFile('assets/theme.css'))->toBeTrue();

    foreach (['layouts/app', 'home', 'entry', 'entry-page', 'archive', 'taxonomy', 'search', '404'] as $view) {
        expect($theme->hasFile('views/'.$view.'.blade.php'))->toBeTrue("Missing view {$view}");
    }
});

it('adds plugin views after the theme views', function () {
    File::ensureDirectoryExists($this->base.'/shop-views');
    File::put($this->base.'/shop-views/cart.blade.php', 'Plugin cart');
    File::put($this->base.'/shop-views/home.blade.php', 'Plugin home');

    $this->manager->addPluginViews($this->base.'/shop-views');
    $this->manager->addPluginViews($this->base.'/shop-views');
    $this->manager->registerViews();

    expect($this->manager->viewPaths())->toBe([$this->base.'/default/views', $this->base.'/shop-views'])
        ->and($this->manager->pluginViewPaths())->toBe([$this->base.'/shop-views'])
        ->and(view('theme::cart')->render())->toBe('Plugin cart')
        ->and(view('theme::home')->render())->toBe('Default home');
});

it('keeps plugin views when only the fallback views are registered', function () {
    File::ensureDirectoryExists($this->base.'/shop-views');
    File::put($this->base.'/shop-views/checkout.blade.php', 'Plugin checkout');

    $this->manager->addPluginViews($this->base.'/shop-views');
    $this->manager->registerFallbackViews();

    expect(view('theme::checkout')->render())->toBe('Plugin checkout');
});

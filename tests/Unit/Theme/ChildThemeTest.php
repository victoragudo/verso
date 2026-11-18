<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->base = storage_path('framework/testing/child-themes-'.uniqid());

    $this->makeTheme = function (string $slug, array $manifest, array $files = []): void {
        File::ensureDirectoryExists($this->base.'/'.$slug.'/views');
        File::put($this->base.'/'.$slug.'/theme.json', json_encode($manifest));

        foreach ($files as $file => $content) {
            File::ensureDirectoryExists(dirname($this->base.'/'.$slug.'/'.$file));
            File::put($this->base.'/'.$slug.'/'.$file, $content);
        }
    };

    ($this->makeTheme)('default', ['name' => 'Verso Default'], [
        'views/home.blade.php' => 'Default home',
        'views/search.blade.php' => 'Default search',
        'views/404.blade.php' => 'Default not found',
        'assets/theme.css' => 'body { color: black; }',
    ]);
    ($this->makeTheme)('harbor', [
        'name' => 'Harbor',
        'settings' => [
            'accent' => ['type' => 'color', 'label' => 'Accent', 'default' => '#0f766e', 'css' => '--accent'],
            'footer_text' => ['type' => 'text', 'label' => 'Footer text', 'default' => 'Harbor Press'],
        ],
        'templates' => ['wide' => ['label' => 'Wide page', 'types' => ['page']]],
    ], [
        'views/home.blade.php' => 'Harbor home',
        'views/search.blade.php' => 'Harbor search',
        'assets/theme.css' => '.harbor { margin: 0; }',
        'assets/logo.svg' => '<svg></svg>',
    ]);
    ($this->makeTheme)('harbor-dusk', [
        'name' => 'Harbor Dusk',
        'parent' => 'harbor',
        'settings' => ['accent' => ['type' => 'color', 'label' => 'Accent', 'default' => '#7c2d12', 'css' => '--accent']],
    ], [
        'views/home.blade.php' => 'Dusk home',
        'assets/theme.css' => '.dusk { color: orange; }',
    ]);
    ($this->makeTheme)('harbor-midnight', ['name' => 'Harbor Midnight', 'parent' => 'harbor-dusk']);
    ($this->makeTheme)('orphan', ['name' => 'Orphan', 'parent' => 'vanished-theme'], ['views/home.blade.php' => 'Orphan home']);
    ($this->makeTheme)('loop-a', ['name' => 'Loop A', 'parent' => 'loop-b']);
    ($this->makeTheme)('loop-b', ['name' => 'Loop B', 'parent' => 'loop-a']);

    $this->manager = new ThemeManager(app(Settings::class), app('view'), $this->base, 'default');
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

it('lists the lineage of a child theme from the child up', function () {
    $slugs = fn (string $slug): array => array_map(fn ($theme) => $theme->slug, $this->manager->lineage($this->manager->find($slug)));

    expect($slugs('harbor-midnight'))->toBe(['harbor-midnight', 'harbor-dusk', 'harbor'])
        ->and($slugs('harbor-dusk'))->toBe(['harbor-dusk', 'harbor'])
        ->and($slugs('harbor'))->toBe(['harbor'])
        ->and($slugs('orphan'))->toBe(['orphan']);
});

it('stops the lineage when parents point at each other', function () {
    $lineage = $this->manager->lineage($this->manager->find('loop-a'));

    expect(array_map(fn ($theme) => $theme->slug, $lineage))->toBe(['loop-a', 'loop-b'])
        ->and($this->manager->missingParent($this->manager->find('loop-a')))->toBe('loop-a');
});

it('reports a missing parent', function () {
    expect($this->manager->missingParent($this->manager->find('orphan')))->toBe('vanished-theme')
        ->and($this->manager->missingParent($this->manager->find('harbor-midnight')))->toBeNull()
        ->and($this->manager->missingParent($this->manager->find('harbor')))->toBeNull();
});

it('looks up views in the child, then the parents, then the default theme', function () {
    $this->manager->activate('harbor-midnight');

    expect($this->manager->viewPaths())->toBe([
        $this->base.'/harbor-midnight/views',
        $this->base.'/harbor-dusk/views',
        $this->base.'/harbor/views',
        $this->base.'/default/views',
    ])
        ->and(view('theme::home')->render())->toBe('Dusk home')
        ->and(view('theme::search')->render())->toBe('Harbor search')
        ->and(view('theme::404')->render())->toBe('Default not found');
});

it('inherits the settings schema and the page templates of the parents', function () {
    $midnight = $this->manager->find('harbor-midnight');

    expect($midnight->settings['accent']['default'])->toBe('#7c2d12')
        ->and($midnight->settings['footer_text']['default'])->toBe('Harbor Press')
        ->and($midnight->templates)->toHaveKey('wide')
        ->and($this->manager->find('harbor-dusk')->settings)->toHaveKeys(['accent', 'footer_text']);
});

it('prints the parent styles before the child styles', function () {
    app(Settings::class)->set('theme.active', 'harbor-midnight');

    expect($this->manager->styles())->toBe(".harbor { margin: 0; }\n.dusk { color: orange; }");

    app(Settings::class)->set('theme.active', 'orphan');

    expect($this->manager->styles())->toBe('body { color: black; }');
});

it('finds asset files in the child first and then in the parents', function () {
    $dusk = $this->manager->find('harbor-dusk');

    expect($this->manager->locateFile($dusk, 'assets/theme.css'))->toBe($this->base.'/harbor-dusk/assets/theme.css')
        ->and($this->manager->locateFile($dusk, 'assets/logo.svg'))->toBe($this->base.'/harbor/assets/logo.svg')
        ->and($this->manager->locateFile($dusk, 'assets/missing.png'))->toBeNull();
});

it('refuses to activate a child theme whose parent is missing', function () {
    $this->manager->activate('orphan');
})->throws(InvalidArgumentException::class, 'The parent theme [vanished-theme] of [orphan] is not installed.');

it('keeps the previous theme when activation of an orphan fails', function () {
    $this->manager->activate('harbor');

    try {
        $this->manager->activate('orphan');
    } catch (InvalidArgumentException) {
    }

    expect(app(Settings::class)->get('theme.active'))->toBe('harbor');
});

<?php

use App\Verso\Theme\Theme;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = storage_path('framework/testing/theme-'.uniqid().'/lighthouse');
    File::ensureDirectoryExists($this->path.'/assets');
});

afterEach(function () {
    File::deleteDirectory(dirname($this->path));
});

it('reads the manifest of a theme', function () {
    File::put($this->path.'/theme.json', json_encode([
        'name' => 'Lighthouse',
        'version' => '1.4.2',
        'description' => 'A quiet theme for coastal photo blogs.',
        'author' => 'Inês Duarte',
        'settings' => ['accent' => ['type' => 'color', 'default' => '#0f766e']],
    ]));

    $theme = Theme::fromDirectory($this->path);

    expect($theme->slug)->toBe('lighthouse')
        ->and($theme->name)->toBe('Lighthouse')
        ->and($theme->version)->toBe('1.4.2')
        ->and($theme->author)->toBe('Inês Duarte')
        ->and($theme->settings)->toHaveKey('accent')
        ->and($theme->viewsPath())->toBe($this->path.'/views')
        ->and($theme->toArray())->toBe([
            'slug' => 'lighthouse',
            'name' => 'Lighthouse',
            'version' => '1.4.2',
            'description' => 'A quiet theme for coastal photo blogs.',
            'author' => 'Inês Duarte',
            'parent' => null,
        ]);
});

it('reads the parent and the page templates of a child theme', function () {
    File::put($this->path.'/theme.json', json_encode([
        'name' => 'Lighthouse Night',
        'parent' => ' lighthouse-classic ',
        'templates' => ['landing' => ['label' => 'Landing page', 'types' => ['page']]],
    ]));

    $theme = Theme::fromDirectory($this->path);

    expect($theme->parent)->toBe('lighthouse-classic')
        ->and($theme->isChild())->toBeTrue()
        ->and($theme->templates)->toBe(['landing' => ['label' => 'Landing page', 'types' => ['page']]])
        ->and($theme->toArray()['parent'])->toBe('lighthouse-classic');
});

it('has no parent when the key is empty or not text', function () {
    File::put($this->path.'/theme.json', json_encode(['parent' => '', 'templates' => 'full-width']));

    $theme = Theme::fromDirectory($this->path);

    expect($theme->parent)->toBeNull()
        ->and($theme->isChild())->toBeFalse()
        ->and($theme->templates)->toBe([]);
});

it('inherits settings and templates from a parent, the child winning on the same key', function () {
    $parent = new Theme(
        slug: 'harbor',
        name: 'Harbor',
        path: '/themes/harbor',
        settings: ['accent' => ['type' => 'color', 'default' => '#0f766e'], 'logo' => ['type' => 'media']],
        templates: ['wide' => ['label' => 'Wide'], 'landing' => ['label' => 'Landing']],
    );
    $child = new Theme(
        slug: 'harbor-dusk',
        name: 'Harbor Dusk',
        path: '/themes/harbor-dusk',
        settings: ['accent' => ['type' => 'color', 'default' => '#7c2d12']],
        parent: 'harbor',
        templates: ['landing' => ['label' => 'Dusk landing']],
    );

    $resolved = $child->inheriting($parent);

    expect($resolved->slug)->toBe('harbor-dusk')
        ->and($resolved->parent)->toBe('harbor')
        ->and($resolved->settings['accent']['default'])->toBe('#7c2d12')
        ->and($resolved->settings)->toHaveKey('logo')
        ->and($resolved->templates['landing']['label'])->toBe('Dusk landing')
        ->and($resolved->templates)->toHaveKey('wide')
        ->and($child->settings)->not->toHaveKey('logo');
});

it('reads the Easy mode extras and merges them with the parent ones', function () {
    $parent = Theme::fromManifest('/themes/harbor', ['easy' => ['fontFiles' => ['newsreader' => 'fonts/newsreader.woff2'], 'presets' => ['calm' => ['tokens' => ['radius.base' => '4px']]]]]);
    $child = Theme::fromManifest('/themes/harbor-dusk', ['parent' => 'harbor', 'easy' => ['presets' => ['calm' => ['label' => 'Quiet dusk']]]]);

    expect(Theme::fromManifest('/themes/plain', ['easy' => 'yes'])->easy)->toBe([])
        ->and($child->inheriting($parent)->easy)->toBe([
            'fontFiles' => ['newsreader' => 'fonts/newsreader.woff2'],
            'presets' => ['calm' => ['tokens' => ['radius.base' => '4px'], 'label' => 'Quiet dusk']],
        ]);
});

it('fills missing fields with safe defaults', function () {
    File::put($this->path.'/theme.json', json_encode(['settings' => 'not a list']));

    $theme = Theme::fromDirectory($this->path);

    expect($theme->name)->toBe('lighthouse')
        ->and($theme->version)->toBe('1.0.0')
        ->and($theme->description)->toBe('')
        ->and($theme->settings)->toBe([]);
});

it('fails without a theme.json file', function () {
    Theme::fromDirectory($this->path);
})->throws(InvalidArgumentException::class, 'has no theme.json');

it('fails with broken JSON', function () {
    File::put($this->path.'/theme.json', '{"name": "Lighthouse",');

    Theme::fromDirectory($this->path);
})->throws(InvalidArgumentException::class, 'not valid JSON');

it('fails when the manifest is not an object', function () {
    File::put($this->path.'/theme.json', '"Lighthouse"');

    Theme::fromDirectory($this->path);
})->throws(InvalidArgumentException::class, 'must be an object');

it('finds files inside the theme', function () {
    File::put($this->path.'/theme.json', '{}');
    File::put($this->path.'/assets/theme.css', 'body { color: navy; }');

    $theme = Theme::fromDirectory($this->path);

    expect($theme->hasFile('assets/theme.css'))->toBeTrue()
        ->and($theme->hasFile('/assets/print.css'))->toBeFalse()
        ->and($theme->filePath('/assets/theme.css'))->toBe($this->path.'/assets/theme.css');
});

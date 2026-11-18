<?php

use App\Verso\Site\Parts\SitePartDefaults;
use App\Verso\Theme\ThemeManager;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [], [
        'views/parts/header.blade.php' => '<header>Harbor</header>',
        'parts/footer.json' => json_encode(['blocks' => [
            ['type' => 'section', 'children' => [['type' => 'copyright'], ['id' => 'legal', 'type' => 'navigation', 'attrs' => ['location' => 'footer']]]],
        ]]),
        'parts/broken.json' => '{"blocks": "nope"',
    ]);
    $this->theme = app(ThemeManager::class)->find('harbor');
    $this->defaults = app(SitePartDefaults::class);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('uses starter blocks the theme ships, with ids', function () {
    expect($this->defaults->blocks($this->theme, 'footer'))->toBe([
        ['type' => 'section', 'children' => [
            ['type' => 'copyright', 'id' => 'footer-2'],
            ['id' => 'legal', 'type' => 'navigation', 'attrs' => ['location' => 'footer']],
        ], 'id' => 'footer-1'],
    ]);
});

it('falls back to a simple starter for the header and footer', function () {
    $header = $this->defaults->blocks($this->theme, 'header-centered');

    expect($header[0]['type'])->toBe('section')
        ->and(array_column($header[0]['children'], 'type'))->toBe(['site-logo', 'navigation'])
        ->and(array_column($header[0]['children'], 'id'))->toBe(['header-centered-2', 'header-centered-3'])
        ->and($this->defaults->blocks($this->theme, 'broken'))->toBe([['type' => 'paragraph', 'content' => '', 'id' => 'broken-1']]);
});

it('names parts in plain words', function (string $key, string $label) {
    expect($this->defaults->label($key, ['header', 'footer', 'footer-minimal', 'author-box']))->toBe($label);
})->with([
    ['header', 'Header'],
    ['footer-minimal', 'Footer: Minimal'],
    ['author-box', 'Author Box'],
]);

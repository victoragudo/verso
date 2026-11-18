<?php

use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\ThemeManager;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', ['parts' => [
        'header' => ['label' => 'Top bar', 'default' => 'classic', 'variants' => [
            'classic' => ['label' => 'Classic'],
            'centered' => ['label' => 'Centered', 'description' => 'Logo in the middle.'],
            'floating' => ['label' => 'Floating'],
        ]],
        'footer' => ['variants' => ['simple' => [], 'missing' => []]],
        'Bad Name' => ['variants' => ['a' => [], 'b' => []]],
    ]], [
        'views/parts/header.blade.php' => '<header>Classic harbor header</header>',
        'views/parts/header-centered.blade.php' => '<header>Centered harbor header</header>',
        'views/parts/footer.blade.php' => '<footer>Harbor footer</footer>',
        'views/page.blade.php' => "@templatePart('header')\n@templatePart('footer')",
    ]);
    $this->sandbox->theme('harbor-dusk', ['parent' => 'harbor'], []);
    $this->sandbox->activate('harbor');
    $this->theme = app(ThemeManager::class)->find('harbor');
});

afterEach(function () {
    $this->sandbox->destroy();
});

function harborVariantPage(): string
{
    return view('theme::page')->render();
}

it('lists only styles whose part file exists, and parts with a real choice', function () {
    $definitions = app(PartVariants::class)->definitions($this->theme);

    expect(array_keys($definitions))->toBe(['header'])
        ->and($definitions['header']['label'])->toBe('Top bar')
        ->and(array_keys($definitions['header']['variants']))->toBe(['classic', 'centered'])
        ->and($definitions['header']['variants']['classic']['part'])->toBe('header')
        ->and($definitions['header']['variants']['centered']['part'])->toBe('header-centered');
});

it('uses the default style until another one is chosen', function () {
    expect(harborVariantPage())->toContain('Classic harbor header');

    expect(app(PartVariants::class)->choose($this->theme, 'header', 'centered'))->toBeTrue();
    app()->forgetScopedInstances();

    expect(harborVariantPage())->toContain('Centered harbor header')->not->toContain('Classic harbor header')
        ->and(app(PartVariants::class)->choices($this->theme))->toBe(['header' => 'centered']);
});

it('refuses unknown styles and parts', function () {
    $variants = app(PartVariants::class);

    expect($variants->choose($this->theme, 'header', 'floating'))->toBeFalse()
        ->and($variants->choose($this->theme, 'footer', 'missing'))->toBeFalse()
        ->and($variants->choose($this->theme, 'sidebar', 'left'))->toBeFalse()
        ->and($variants->chosen($this->theme, 'header'))->toBe('classic');
});

it('shows a previewed style in one request only', function () {
    app(PartVariants::class)->preview('header', 'centered');

    expect(harborVariantPage())->toContain('Centered harbor header');

    app()->forgetScopedInstances();

    expect(harborVariantPage())->toContain('Classic harbor header');
});

it('lets an explicit variant in the view win over the chosen style', function () {
    app(PartVariants::class)->choose($this->theme, 'header', 'centered');
    app()->forgetScopedInstances();

    expect(Blade::render("@templatePart('header', 'classic')"))->toContain('Classic harbor header');
});

it('gives child themes the styles of the parent', function () {
    $child = app(ThemeManager::class)->find('harbor-dusk');

    expect(array_keys(app(PartVariants::class)->definitions($child)['header']['variants']))->toBe(['classic', 'centered']);
});

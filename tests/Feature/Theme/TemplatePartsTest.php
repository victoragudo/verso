<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Templates\TemplateContext;
use App\Verso\Theme\Templates\TemplateDebug;
use App\Verso\Theme\Templates\TemplateParts;
use App\Verso\Theme\ThemeManager;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [], [
        'views/parts/header.blade.php' => '<header>Harbor header for {{ $siteTitle }}</header>',
        'views/parts/footer.blade.php' => '<footer>Harbor footer</footer>',
        'views/parts/footer-minimal.blade.php' => '<footer>Harbor minimal footer</footer>',
        'views/page.blade.php' => "@templatePart('header')\n@templatePart('footer', \$footerStyle)\n@templatePart('sidebar')",
    ]);
    $this->sandbox->theme('harbor-dusk', ['parent' => 'harbor'], [
        'views/parts/header.blade.php' => '<header>Dusk header for {{ $siteTitle }}</header>',
    ]);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('includes a part with the data of the view', function () {
    $this->sandbox->activate('harbor');

    $html = view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => null])->render();

    expect($html)->toContain('<header>Harbor header for Kvarnholmen Notes</header>')
        ->toContain('<footer>Harbor footer</footer>');
});

it('picks the variant when the theme has it', function () {
    $this->sandbox->activate('harbor');

    $html = view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => 'minimal'])->render();

    expect($html)->toContain('<footer>Harbor minimal footer</footer>')->not->toContain('<footer>Harbor footer</footer>');
});

it('falls back to the plain part for an unknown variant', function () {
    $this->sandbox->activate('harbor');

    expect(view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => 'neon'])->render())->toContain('<footer>Harbor footer</footer>');
});

it('prints nothing for a part the theme does not have', function () {
    $this->sandbox->activate('harbor');

    expect(app(TemplateParts::class)->view('sidebar'))->toBeNull()
        ->and(view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => null])->render())->not->toContain('sidebar');
});

it('uses the part of the child theme before the parent part', function () {
    $this->sandbox->activate('harbor-dusk');

    $html = view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => 'minimal'])->render();

    expect($html)->toContain('<header>Dusk header for Kvarnholmen Notes</header>')
        ->toContain('<footer>Harbor minimal footer</footer>');
});

it('returns full view names and refuses unsafe names', function () {
    $this->sandbox->activate('harbor');
    $parts = app(TemplateParts::class);

    expect($parts->view('footer', 'minimal'))->toBe('theme::parts.footer-minimal')
        ->and($parts->view('footer', '../minimal'))->toBe('theme::parts.footer')
        ->and($parts->view('../footer'))->toBeNull()
        ->and($parts->view('Footer'))->toBeNull();
});

it('records part lookups for the debug panel', function () {
    $this->sandbox->activate('harbor');

    app(TemplateParts::class)->view('footer', 'compact');
    $lookup = app(TemplateDebug::class)->lookups()[0];

    expect($lookup->context)->toBe(TemplateContext::Part)
        ->and(array_column($lookup->candidates, 'name'))->toBe(['parts.footer-compact', 'parts.footer'])
        ->and($lookup->chosen)->toBe('parts.footer');
});

it('lists the parts of a theme and its parents', function () {
    $themes = app(ThemeManager::class);

    expect(app(TemplateParts::class)->available($themes->find('harbor-dusk')))->toBe(['footer', 'footer-minimal', 'header'])
        ->and(app(TemplateParts::class)->available($themes->find('default')))->toBe(['footer', 'header', 'header-minimal']);
});

it('lets a child theme of the default theme replace only the header', function () {
    $this->sandbox->theme('default-compact', ['parent' => 'default'], [
        'views/parts/header.blade.php' => '<header class="compact-header">{{ $site[\'name\'] }}</header>',
    ])->activate('default-compact');
    app(Settings::class)->set('site.name', 'Saltsjö Journal');
    config(['verso.page_cache.enabled' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<header class="compact-header">Saltsjö Journal</header>', false)
        ->assertDontSee('class="site-header"', false)
        ->assertSee('class="site-footer"', false);
});

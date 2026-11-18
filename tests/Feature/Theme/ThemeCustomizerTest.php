<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Packages\PackageInspector;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\ThemeManager;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PackageZip;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->admin = User::factory()->admin()->create();
    $this->theme = app(ThemeManager::class)->find('default');
    $this->customizer = app(ThemeCustomizer::class);
});

it('uses the defaults when nothing is saved', function () {
    $values = $this->customizer->values($this->theme);

    expect($values['accent_color'])->toBe('#9a3412')
        ->and($values['layout_width'])->toBe(62)
        ->and($values['logo'])->toBeNull()
        ->and($this->customizer->css($this->theme))->toBe('');
});

it('saves values in theme settings and outputs only the changed ones as CSS', function () {
    $this->customizer->save($this->theme, ['accent_color' => '#1D4ED8', 'layout_width' => '70', 'body_font' => 'georgia', 'text_color' => '#23211d']);

    expect(app(Settings::class)->get('theme.default.accent_color'))->toBe('#1d4ed8')
        ->and(app(Settings::class)->get('theme.default.layout_width'))->toBe(70)
        ->and(app(Settings::class)->has('theme.default.text_color'))->toBeFalse()
        ->and($this->customizer->css($this->theme))
        ->toBe(":root { --accent-light: #1d4ed8; --font-text: Georgia, 'Times New Roman', Times, serif; --wide: 70rem; }");
});

it('goes back to the default when a value is cleared', function () {
    $this->customizer->save($this->theme, ['footer_text' => 'Photos by the Porto Walking Club']);
    $this->customizer->save($this->theme, ['footer_text' => null]);

    expect(app(Settings::class)->has('theme.default.footer_text'))->toBeFalse();
});

it('ignores stored values that are no longer valid', function () {
    app(Settings::class)->set('theme.default.accent_color', 'red;} body { display: none }');
    app(Settings::class)->set('theme.default.layout_width', 500);

    expect($this->customizer->values($this->theme)['accent_color'])->toBe('#9a3412')
        ->and($this->customizer->values($this->theme)['layout_width'])->toBe(62)
        ->and($this->customizer->css($this->theme))->toBe('');
});

it('prints the custom properties, logo and footer text on the public site', function () {
    $logo = Media::factory()->create(['filename' => 'harbour-logo.png']);
    $this->customizer->save($this->theme, ['accent_color' => '#0f766e', 'logo' => $logo->id, 'footer_text' => 'Stories from the Atlantic coast since 2019']);

    $this->get('/')
        ->assertOk()
        ->assertSee(':root { --accent-light: #0f766e; }', false)
        ->assertSee('class="site-logo"', false)
        ->assertSee('Stories from the Atlantic coast since 2019');
});

it('escapes the footer text', function () {
    $this->customizer->save($this->theme, ['footer_text' => '<script>alert("hi")</script>']);

    $this->get('/')->assertOk()->assertDontSee('<script>alert', false)->assertSee('&lt;script&gt;', false);
});

it('shows the customizer page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.themes.customize', 'default'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/theme/customize')
            ->where('theme.slug', 'default')
            ->where('theme.active', true)
            ->where('fields.0.key', 'accent_color')
            ->where('fields.0.type', 'color')
            ->where('values.accent_color', '#9a3412')
            ->where('fonts.georgia', 'Georgia (serif)')
            ->where('schemaErrors', [])
        );

    $this->actingAs($this->admin)->get(route('admin.themes.customize', 'missing-theme'))->assertNotFound();
});

it('saves the customizer form', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.themes.customize.update', 'default'), ['values' => ['accent_color' => '#7c3aed', 'layout_width' => 72]])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Verso Default settings saved.']);

    expect(app(Settings::class)->get('theme.default.accent_color'))->toBe('#7c3aed');
});

it('validates the customizer form', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.themes.customize.update', 'default'), ['values' => [
            'accent_color' => 'tomato',
            'body_font' => 'papyrus',
            'layout_width' => 300,
            'logo' => 99999,
            'footer_text' => str_repeat('Long text ', 30),
        ]])
        ->assertSessionHasErrors(['values.accent_color', 'values.body_font', 'values.layout_width', 'values.logo', 'values.footer_text']);
});

it('keeps people without theme rights out', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.themes.customize', 'default'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.themes.customize.update', 'default'), ['values' => []])->assertForbidden();
    $this->actingAs($editor)->get(route('admin.themes.preview', 'default'))->assertForbidden();
});

it('renders a live preview with draft values without saving them', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.themes.preview', ['theme' => 'default', 'values' => ['accent_color' => '#be123c', 'layout_width' => 'wide', 'footer_text' => 'Draft footer for the preview']]))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee(':root { --accent-light: #be123c; }', false)
        ->assertDontSee('--wide: wide', false)
        ->assertSee('Draft footer for the preview');

    expect(app(Settings::class)->has('theme.default.accent_color'))->toBeFalse();
});

it('marks the default theme as customizable', function () {
    $this->actingAs($this->admin)->get(route('admin.themes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('themes.0.customizable', true));
});

it('refuses theme packages with an unsafe settings schema', function () {
    $zip = PackageZip::theme('lighthouse', '1.0.0', ['settings' => ['accent' => ['type' => 'color', 'default' => '#000000', 'css' => '--accent;}*{x:y']]]);
    $path = $zip->build(storage_path('framework/testing/schema-'.uniqid().'.zip'));

    try {
        expect(fn () => app(PackageInspector::class)->inspect($path, ExtensionKind::Theme))
            ->toThrow(InvalidPackage::class, 'CSS variable name that is not valid');
    } finally {
        unlink($path);
    }
});

it('saves sizes in any CSS unit and prints them as they are', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.themes.customize.update', 'default'), ['values' => ['layout_width' => 'min(92vw, 1200PX)', 'text_width' => '68ch']])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('theme.default.layout_width'))->toBe('min(92vw, 1200px)')
        ->and(app(Settings::class)->get('theme.default.text_width'))->toBe('68ch')
        ->and($this->customizer->css($this->theme))->toContain('--wide: min(92vw, 1200px);')->toContain('--measure: 68ch;');
});

it('refuses unsafe sizes and whole numbers outside the range', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.themes.customize.update', 'default'), ['values' => ['layout_width' => 'calc(1px); background: url(x)', 'text_width' => '90rem']])
        ->assertSessionHasErrors(['values.layout_width', 'values.text_width']);

    expect(app(Settings::class)->has('theme.default.layout_width'))->toBeFalse();
});

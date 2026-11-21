<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Oliveira']);
    $this->theme = fn () => app(ThemeManager::class)->find('ashgrove');
    $this->palette = ['accent' => '#1f4e79', 'background' => '#fbfaf7', 'text' => '#1b2420', 'muted' => '#4b5563', 'surface' => '#eef2f6'];
});

it('sends guests to the login page', function () {
    $this->get(route('admin.easy.style'))->assertRedirect(route('login'));
    $this->put(route('admin.easy.style.update'), ['colors' => $this->palette])->assertRedirect(route('login'));
});

it('is only for people who manage themes', function () {
    $editor = User::factory()->editor()->create(['name' => 'Tomás Reis']);

    $this->actingAs($editor)->get(route('admin.easy.style'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.easy.style.update'), ['colors' => $this->palette])->assertForbidden();

    expect(app(Settings::class)->get('theme.styles.ashgrove'))->toBeNull();
});

it('shows the color roles, presets, font pairs and the preview of the active theme', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.easy.style'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/easy/style')
            ->where('theme.slug', 'ashgrove')
            ->where('roles.0', ['role' => 'accent', 'token' => 'color.accent', 'label' => 'Accent'])
            ->has('roles', 5)
            ->where('colors.background', '#f6f7f3')
            ->where('presets.0.key', 'calm')
            ->has('presets', 4)
            ->where('preset', 'calm')
            ->where('fontPair', 'classic')
            ->where('fontPairs.0.tokens', ['font.heading' => 'georgia', 'font.body' => 'charter'])
            ->where('fontPairs.0.headingLabel', 'Georgia (serif)')
            ->has('fontFaces', 2)
            ->where('logo', null)
            ->where('logoSupported', true)
            ->where('canUpload', true)
            ->where('previewUrl', route('admin.site-editor.preview'))
            ->where('previewDelay', 350)
            ->where('palette', ['sampleSize' => 96, 'maxColors' => 6])
        );
});

it('sends font pair names, font labels and samples in the admin language', function () {
    $this->admin->forceFill(['admin_locale' => 'es'])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.easy.style'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fontPairs.0.headingLabel', 'Georgia (con serifa)')
            ->where('fontPairs.0.sample', 'Pan recién hecho cada mañana, horneado junto al puerto.')
        );
});

it('picks the style preset of the chosen starter site first, then the saved one', function () {
    app(EasyState::class)->rememberStarter('portfolio');

    $this->actingAs($this->admin)
        ->get(route('admin.easy.style'))
        ->assertInertia(fn (Assert $page) => $page->where('preset', 'elegant')->where('fontPair', 'editorial'));

    app(EasyState::class)->rememberStyle('playful', 'modern');

    $this->actingAs($this->admin)
        ->get(route('admin.easy.style'))
        ->assertInertia(fn (Assert $page) => $page->where('preset', 'playful')->where('fontPair', 'modern'));
});

it('saves the palette, preset, fonts and logo and opens the dashboard', function () {
    $logo = Media::factory()->create(['filename' => 'harbor-lane-logo.png', 'mime_type' => 'image/png']);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.style.update'), ['colors' => $this->palette, 'preset' => 'bold', 'fontPair' => 'modern', 'logo' => $logo->id])
        ->assertRedirect(route('admin.dashboard'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Your new look is saved. Visitors see it now.']);

    app()->forgetScopedInstances();
    $theme = ($this->theme)();

    expect(app(GlobalStyles::class)->values($theme))->toMatchArray([
        'color.accent' => '#1f4e79',
        'color.background' => '#fbfaf7',
        'color.surface' => '#eef2f6',
        'radius.base' => '0px',
        'spacing.x-large' => '3.5rem',
        'font.heading' => 'system',
        'font.body' => 'system',
    ])
        ->and(app(ThemeCustomizer::class)->logo($theme)?->id)->toBe($logo->id)
        ->and(app(EasyState::class)->stylePreset())->toBe('bold')
        ->and(app(EasyState::class)->fontPair())->toBe('modern')
        ->and(app(EasyState::class)->hasStyled())->toBeTrue()
        ->and(app(Settings::class)->get(EasyState::STYLED_AT))->toBeString();
});

it('keeps the logo when the form does not send one and removes it when asked', function () {
    $logo = Media::factory()->create(['mime_type' => 'image/png']);
    $customizer = app(ThemeCustomizer::class);
    $customizer->save(($this->theme)(), ['logo' => $logo->id]);

    $this->actingAs($this->admin)->put(route('admin.easy.style.update'), ['colors' => $this->palette]);
    expect($customizer->logo(($this->theme)())?->id)->toBe($logo->id);

    $this->actingAs($this->admin)->put(route('admin.easy.style.update'), ['colors' => $this->palette, 'logo' => null]);
    expect($customizer->logo(($this->theme)()))->toBeNull();
});

it('saves the colors but warns when they are hard to read', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.easy.style.update'), ['colors' => [...$this->palette, 'text' => '#c9c6bf']])
        ->assertRedirect(route('admin.dashboard'))
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'Your new look is saved, but some colors are hard to read: The text is hard to read on this background. Try a darker text color.',
        ]);

    app()->forgetScopedInstances();

    expect(app(GlobalStyles::class)->values(($this->theme)())['color.text'])->toBe('#c9c6bf');
});

it('refuses bad colors, unknown picks and logos that are not pictures', function (array $data, string $field) {
    $this->actingAs($this->admin)
        ->from(route('admin.easy.style'))
        ->put(route('admin.easy.style.update'), $data)
        ->assertRedirect(route('admin.easy.style'))
        ->assertSessionHasErrors($field);

    expect(app(Settings::class)->get('theme.styles.ashgrove'))->toBeNull()
        ->and(app(EasyState::class)->hasStyled())->toBeFalse();
})->with([
    'a color name' => [['colors' => ['accent' => 'tomato']], 'colors.accent'],
    'a short code' => [['colors' => ['text' => '#123']], 'colors.text'],
    'a script' => [['colors' => ['background' => '#fff;}body{']], 'colors.background'],
    'an unknown role' => [['colors' => ['border' => '#e2ded5']], 'colors'],
    'an unknown preset' => [['preset' => 'retro'], 'preset'],
    'a font pair the theme cannot use' => [['fontPair' => 'gothic'], 'fontPair'],
    'a missing logo' => [['logo' => 999999], 'logo'],
]);

it('refuses a document as logo', function () {
    $pdf = Media::factory()->document()->create(['filename' => 'opening-hours.pdf']);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.style.update'), ['logo' => $pdf->id])
        ->assertSessionHasErrors(['logo' => 'Pick a picture file, like a PNG or JPG, for your logo.']);
});

it('previews a logo picked but not saved yet', function () {
    $logo = Media::factory()->create(['filename' => 'harbor-lane-logo.png', 'mime_type' => 'image/png', 'path' => 'media/harbor-lane-logo.png']);

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.preview', ['logo' => $logo->id, 'tokens' => ['color.accent' => '#1f4e79']]))
        ->assertOk()
        ->assertSee('harbor-lane-logo.png', false)
        ->assertSee('--accent-light: #1f4e79', false);

    expect(app(Settings::class)->get('theme.ashgrove.logo'))->toBeNull();
});

it('adds Make it yours to the Design menu with keywords', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.easy.style'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains(
            fn ($item) => $item['key'] === 'easy-style' && $item['group'] === 'design' && in_array('palette', $item['keywords'], true),
        )));
});

it('invites admins to make the site theirs after a starter, until they save once', function () {
    $notice = fn () => collect($this->actingAs($this->admin)->get(route('admin.dashboard'))->viewData('page')['props']['notices'])->firstWhere('key', 'easy-style');

    expect($notice())->toBeNull();

    app(EasyState::class)->rememberStarter('restaurant');
    expect($notice())->toMatchArray(['title' => 'Make it yours', 'href' => '/admin/easy/style', 'icon' => 'palette', 'tone' => 'info']);

    app(EasyState::class)->rememberStyle('calm', 'classic');
    expect($notice())->toBeNull();
});

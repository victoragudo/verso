<?php

use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Capabilities\ManifestAccess;
use App\Verso\Access\Roles\Roles;
use App\Verso\Theme\Theme;
use Tests\Support\ThemeFixtures;

afterEach(fn () => ThemeFixtures::cleanup());

// Harbor Light with a capability and a role in theme.json.
function harborLightWithAccess(array $access): Theme
{
    $path = ThemeFixtures::copy('harbor-light');
    $manifest = json_decode((string) file_get_contents($path.'/theme.json'), true);
    file_put_contents($path.'/theme.json', json_encode([...$manifest, ...$access]));

    return Theme::fromDirectory($path);
}

it('registers the capabilities and roles of a theme', function () {
    $theme = harborLightWithAccess([
        'capabilities' => [['key' => 'harbor-light.edit_banner', 'label' => 'Change the harbor banner', 'risk' => 'low', 'roles' => ['editor']]],
        'roles' => [['slug' => 'harbor_designer', 'name' => 'Harbor designer', 'capabilities' => ['harbor-light.edit_banner', 'manage_menus']]],
    ]);

    app(ManifestAccess::class)->registerTheme($theme);
    app(Roles::class)->flush();

    expect(app(CapabilityRegistry::class)->get('harbor-light.edit_banner')->source)->toBe('theme:harbor-light')
        ->and(app(CapabilityRegistry::class)->get('harbor-light.edit_banner')->group)->toBe('Harbor Light')
        ->and(app(Roles::class)->find('editor')->capabilities)->toContain('harbor-light.edit_banner')
        ->and(app(Roles::class)->find('harbor_designer')->capabilities)->toBe(['harbor-light.edit_banner', 'manage_menus']);
});

it('skips a theme with broken capabilities without breaking the site', function () {
    $theme = harborLightWithAccess(['capabilities' => [['key' => 'Harbor Banner', 'label' => 'Broken']]]);

    app(ManifestAccess::class)->registerTheme($theme);

    expect(app(CapabilityRegistry::class)->has('Harbor Banner'))->toBeFalse();
});

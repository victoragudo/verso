<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\PluginManifest;
use Tests\Support\PluginFixtures;

function seoToolkitManifest(array $overrides = []): array
{
    return array_merge([
        'name' => 'SEO Toolkit',
        'slug' => 'seo-toolkit',
        'version' => '1.4.0',
        'description' => 'Meta tags and social cards for every entry.',
        'provider' => 'Verso\\Plugins\\SeoToolkit\\SeoToolkitServiceProvider',
        'requires' => '^0.1',
        'permissions' => ['manage_settings', 'edit_any_entry'],
        'autoload' => ['psr-4' => ['Verso\\Plugins\\SeoToolkit\\' => 'src/']],
    ], $overrides);
}

it('reads a valid manifest', function () {
    $manifest = PluginManifest::fromArray(seoToolkitManifest(), '/srv/site/plugins/seo-toolkit');

    expect($manifest->name)->toBe('SEO Toolkit')
        ->and($manifest->slug)->toBe('seo-toolkit')
        ->and($manifest->version)->toBe('1.4.0')
        ->and($manifest->provider)->toBe('Verso\\Plugins\\SeoToolkit\\SeoToolkitServiceProvider')
        ->and($manifest->permissions)->toBe([Capability::ManageSettings, Capability::EditAnyEntry])
        ->and($manifest->autoload)->toBe(['Verso\\Plugins\\SeoToolkit\\' => 'src/'])
        ->and($manifest->path)->toBe('/srv/site/plugins/seo-toolkit');
});

it('uses defaults for optional fields', function () {
    $data = seoToolkitManifest();
    unset($data['description'], $data['requires'], $data['permissions'], $data['autoload']);

    $manifest = PluginManifest::fromArray($data, '/srv/site/plugins/seo-toolkit');

    expect($manifest->description)->toBe('')
        ->and($manifest->requires)->toBe('*')
        ->and($manifest->permissions)->toBe([])
        ->and($manifest->autoload)->toBe([]);
});

it('drops a leading backslash from the provider and repeated permissions', function () {
    $manifest = PluginManifest::fromArray(seoToolkitManifest([
        'provider' => '\\Verso\\Plugins\\SeoToolkit\\SeoToolkitServiceProvider',
        'permissions' => ['upload_files', 'upload_files'],
    ]), '/srv/site/plugins/seo-toolkit');

    expect($manifest->provider)->toBe('Verso\\Plugins\\SeoToolkit\\SeoToolkitServiceProvider')
        ->and($manifest->permissions)->toBe([Capability::UploadFiles]);
});

it('rejects bad manifests', function (array $overrides, string $message) {
    expect(fn () => PluginManifest::fromArray(seoToolkitManifest($overrides), '/srv/site/plugins/seo-toolkit'))
        ->toThrow(InvalidManifest::class, $message);
})->with([
    'missing name' => [['name' => ''], 'name field is required'],
    'bad slug' => [['slug' => 'SEO Toolkit'], 'slug field format is invalid'],
    'slug and folder differ' => [['slug' => 'seo-tools'], 'must match the plugin folder name'],
    'bad version' => [['version' => 'one'], 'version field format is invalid'],
    'bad provider' => [['provider' => 'SeoToolkit'], 'provider field format is invalid'],
    'unknown permission' => [['permissions' => ['launch_rockets']], 'is invalid'],
    'bad constraint' => [['requires' => 'newest'], 'not a valid version constraint'],
    'bad autoload' => [['autoload' => ['psr-4' => ['Verso\\Plugins\\SeoToolkit' => 'src/']]], 'autoload.psr-4'],
    'autoload outside the plugin' => [['autoload' => ['psr-4' => ['Verso\\Plugins\\SeoToolkit\\' => '../../app']]], 'autoload.psr-4'],
]);

it('collects every error', function () {
    try {
        PluginManifest::fromArray(['slug' => 'seo-toolkit'], '/srv/site/plugins/seo-toolkit');
    } catch (InvalidManifest $exception) {
        expect($exception->errors)->toHaveCount(3)
            ->and($exception->path)->toBe('/srv/site/plugins/seo-toolkit');

        return;
    }

    $this->fail('No exception was thrown.');
});

it('reads a manifest file', function () {
    $manifest = PluginManifest::fromFile(PluginFixtures::path().'/event-calendar/verso.json');

    expect($manifest->name)->toBe('Event Calendar')
        ->and($manifest->path)->toBe(PluginFixtures::path().'/event-calendar');
});

it('rejects missing files and broken JSON', function () {
    expect(fn () => PluginManifest::fromFile(PluginFixtures::path().'/nowhere/verso.json'))
        ->toThrow(InvalidManifest::class, 'does not exist');

    expect(fn () => PluginManifest::fromFile(PluginFixtures::path().'/broken-json/verso.json'))
        ->toThrow(InvalidManifest::class, 'not valid JSON');
});

it('rejects a JSON file that is not an object', function () {
    $file = storage_path('framework/testing/plugins-scalar/verso.json');
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, '"Harbor"');

    expect(fn () => PluginManifest::fromFile($file))->toThrow(InvalidManifest::class, 'must be a JSON object');
});

it('checks the Verso version it needs', function () {
    $manifest = PluginManifest::fromArray(seoToolkitManifest(), '/srv/site/plugins/seo-toolkit');

    expect($manifest->supports('0.1.3'))->toBeTrue()
        ->and($manifest->supports('0.2.0'))->toBeFalse();
});

it('turns into an array for the admin', function () {
    $manifest = PluginManifest::fromArray(seoToolkitManifest(), '/srv/site/plugins/seo-toolkit');

    expect($manifest->toArray())->toBe([
        'name' => 'SEO Toolkit',
        'slug' => 'seo-toolkit',
        'version' => '1.4.0',
        'description' => 'Meta tags and social cards for every entry.',
        'requires' => '^0.1',
        'permissions' => ['manage_settings', 'edit_any_entry'],
        'network' => [],
    ]);
});

it('reads the network hosts in lower case', function () {
    $manifest = PluginManifest::fromArray([...seoToolkitManifest(), 'network' => ['API.Songkick.com', '*.stripe.com', 'api.songkick.com']], '/srv/site/plugins/seo-toolkit');

    expect($manifest->network)->toBe(['api.songkick.com', '*.stripe.com'])
        ->and($manifest->toArray()['network'])->toBe(['api.songkick.com', '*.stripe.com']);
});

it('rejects network entries that are not host names', function (mixed $network) {
    expect(fn () => PluginManifest::fromArray([...seoToolkitManifest(), 'network' => $network], '/srv/site/plugins/seo-toolkit'))
        ->toThrow(InvalidManifest::class);
})->with([
    'a url' => [['https://api.songkick.com/v3']],
    'a path' => [['api.songkick.com/events']],
    'not a list' => [['songkick' => 'api.songkick.com']],
    'a number' => [[443]],
]);

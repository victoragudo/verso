<?php

use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManifest;

function newsletterManifest(string $requires = '^0.1'): PluginManifest
{
    return PluginManifest::fromArray([
        'name' => 'Newsletter',
        'slug' => 'newsletter',
        'version' => '1.0.2',
        'description' => 'Collects email sign ups.',
        'provider' => 'Verso\\Plugins\\Newsletter\\NewsletterServiceProvider',
        'requires' => $requires,
        'permissions' => ['manage_users'],
    ], '/srv/site/plugins/newsletter');
}

it('knows its status', function () {
    $valid = new Plugin('newsletter', '/srv/site/plugins/newsletter', newsletterManifest());

    expect($valid->status())->toBe('disabled')
        ->and($valid->withEnabled(true)->status())->toBe('enabled')
        ->and((new Plugin('newsletter', '/p', newsletterManifest(), compatible: false))->status())->toBe('incompatible')
        ->and((new Plugin('newsletter', '/p', null, ['Broken.']))->status())->toBe('invalid');
});

it('can be enabled only when valid and compatible', function () {
    expect((new Plugin('newsletter', '/p', newsletterManifest()))->canBeEnabled())->toBeTrue()
        ->and((new Plugin('newsletter', '/p', newsletterManifest(), compatible: false))->canBeEnabled())->toBeFalse()
        ->and((new Plugin('newsletter', '/p', null, ['Broken.']))->canBeEnabled())->toBeFalse();
});

it('explains its problems', function () {
    config(['verso.version' => '0.1.0']);

    expect((new Plugin('newsletter', '/p', newsletterManifest('^2.0'), compatible: false))->problems())
        ->toBe(['It needs Verso ^2.0, but this site runs Verso 0.1.0.'])
        ->and((new Plugin('newsletter', '/p', null, ['The name field is required.']))->problems())
        ->toBe(['The name field is required.'])
        ->and((new Plugin('newsletter', '/p', newsletterManifest()))->problems())->toBe([]);
});

it('turns into an array for the admin', function () {
    $plugin = (new Plugin('newsletter', '/srv/site/plugins/newsletter', newsletterManifest()))->withEnabled(true);

    expect($plugin->toArray())->toBe([
        'slug' => 'newsletter',
        'name' => 'Newsletter',
        'version' => '1.0.2',
        'description' => 'Collects email sign ups.',
        'requires' => '^0.1',
        'permissions' => ['manage_users'],
        'status' => 'enabled',
        'enabled' => true,
        'problems' => [],
    ]);
});

it('uses the slug as name when the manifest is broken', function () {
    $array = (new Plugin('newsletter', '/p', null, ['Broken.']))->toArray();

    expect($array['name'])->toBe('newsletter')
        ->and($array['version'])->toBeNull()
        ->and($array['permissions'])->toBe([]);
});

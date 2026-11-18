<?php

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\PluginManifest;
use Illuminate\Support\Facades\DB;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/Helpers.php';

// The smallest valid manifest, with extra parts.
function accessManifest(array $extra): array
{
    return [
        'name' => 'Harbor Bookings',
        'slug' => 'harbor-bookings',
        'version' => '1.0.0',
        'provider' => 'Verso\\Plugins\\HarborBookings\\HarborBookingsServiceProvider',
        ...$extra,
    ];
}

function accessManifestErrors(array $extra): array
{
    try {
        PluginManifest::fromArray(accessManifest($extra), '/plugins/harbor-bookings');
    } catch (InvalidManifest $exception) {
        return $exception->errors;
    }

    return [];
}

it('registers the capabilities and roles of an enabled plugin', function () {
    $manager = PluginFixtures::use();
    $manager->enable('event-calendar');
    $manager->register($manager->get('event-calendar'));
    app(Roles::class)->flush();

    $capability = app(CapabilityRegistry::class)->get('event-calendar.manage');
    $host = app(Roles::class)->find('event_host');

    expect($capability->group)->toBe('Event Calendar')
        ->and($capability->source)->toBe('plugin:event-calendar')
        ->and(app(Roles::class)->find('editor')->capabilities)->toContain('event-calendar.manage')
        ->and(app(Roles::class)->find('author')->capabilities)->not->toContain('event-calendar.manage')
        ->and($host->builtIn)->toBeTrue()
        ->and($host->capabilities)->toBe(['event-calendar.manage', 'upload_files']);

    $user = giveExtraRoles(User::factory()->create(), 'event_host');

    expect($user->hasCapability('event-calendar.manage'))->toBeTrue()
        ->and(User::factory()->editor()->create()->can('event-calendar.manage'))->toBeTrue();
});

it('knows nothing of a plugin that is off', function () {
    PluginFixtures::use();

    expect(app(CapabilityRegistry::class)->has('event-calendar.manage'))->toBeFalse()
        ->and(app(Roles::class)->exists('event_host'))->toBeFalse();
});

it('reads capabilities and roles from verso.json', function () {
    $manifest = PluginManifest::fromArray(accessManifest([
        'capabilities' => [['key' => 'harbor-bookings.manage', 'label' => 'Manage bookings', 'risk' => 'high', 'roles' => ['editor']]],
        'roles' => [['slug' => 'front_desk', 'name' => 'Front desk', 'capabilities' => ['harbor-bookings.manage']]],
        'admin' => ['group' => 'tools', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'capability' => 'harbor-bookings.manage']]],
    ]), '/plugins/harbor-bookings');

    expect($manifest->capabilities[0]['key'])->toBe('harbor-bookings.manage')
        ->and($manifest->roles[0]['slug'])->toBe('front_desk');
});

it('refuses bad capabilities and roles in verso.json', function (array $extra, string $error) {
    expect(implode(' ', accessManifestErrors($extra)))->toContain($error);
})->with([
    'key without the plugin slug' => [['capabilities' => [['key' => 'bookings.manage', 'label' => 'Manage bookings']]], 'must start with "harbor-bookings."'],
    'unknown risk' => [['capabilities' => [['key' => 'harbor-bookings.manage', 'label' => 'Manage', 'risk' => 'extreme']]], 'capabilities.0.risk'],
    'default role that does not exist' => [['capabilities' => [['key' => 'harbor-bookings.manage', 'label' => 'Manage', 'roles' => ['captain']]]], 'capabilities.0.roles.0'],
    'role with a built-in slug' => [['roles' => [['slug' => 'editor', 'name' => 'Editor', 'capabilities' => []]]], 'roles.0.slug'],
    'role without capabilities' => [['roles' => [['slug' => 'front_desk', 'name' => 'Front desk']]], 'roles.0.capabilities'],
    'admin link with an unknown capability' => [['admin' => ['group' => 'tools', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'capability' => 'harbor-bookings.rule']]]], 'admin.items.0.capability'],
]);

it('lets every bundled plugin manifest pass the new checks', function () {
    foreach (glob(base_path('plugins/*/verso.json')) as $file) {
        expect(PluginManifest::fromFile($file))->toBeInstanceOf(PluginManifest::class);
    }
});

it('keeps changes to a plugin role hidden while the plugin is off', function () {
    DB::table('roles')->insert([
        'slug' => 'event_host',
        'name' => 'Event host',
        'capabilities' => json_encode(['manage_menus']),
        'removed_capabilities' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    PluginFixtures::use();
    app(Roles::class)->flush();

    expect(app(Roles::class)->exists('event_host'))->toBeFalse();

    $manager = PluginFixtures::use();
    $manager->enable('event-calendar');
    $manager->register($manager->get('event-calendar'));
    app(Roles::class)->flush();

    expect(app(Roles::class)->find('event_host')->capabilities)->toContain('manage_menus', 'event-calendar.manage')
        ->and(app(Roles::class)->find('event_host')->modified)->toBeTrue();
});

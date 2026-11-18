<?php

use App\Verso\Extensions\Verification\Checks\PermissionCheck;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Plugins\PluginManifest;

// A Tour Dates manifest with the given permissions and network hosts.
function tourManifest(array $permissions = [], array $network = []): PluginManifest
{
    return PluginManifest::fromArray([
        'name' => 'Tour Dates',
        'slug' => 'tour-dates',
        'version' => '1.0.0',
        'provider' => 'Verso\\Plugins\\TourDates\\TourDatesServiceProvider',
        'permissions' => $permissions,
        'network' => $network,
    ], '/srv/site/plugins/tour-dates');
}

it('passes when the code uses only what is declared', function () {
    $scan = new ScanResult;
    $scan->useCapability('publish_entries', 'src/Publisher.php', 8);
    $scan->nameHost('api.songkick.com', 'src/Feed.php', 12);
    $scan->callNetwork('src/Feed.php', 12);

    $facts = (new PermissionCheck)->check(tourManifest(['publish_entries'], ['api.songkick.com']), $scan);

    expect($scan->findings)->toBe([])
        ->and($facts)->toBe([
            'declared' => ['publish_entries'],
            'used' => ['publish_entries'],
            'network' => ['api.songkick.com'],
            'hosts' => ['api.songkick.com'],
        ]);
});

it('reports capabilities the code uses without declaring them', function () {
    $scan = new ScanResult;
    $scan->useCapability('manage_users', 'src/Robot.php', 14);
    $scan->useCapability('upload_files', 'src/Robot.php', 20);

    (new PermissionCheck)->check(tourManifest(['upload_files', 'manage_terms']), $scan);

    expect(array_map(fn (Finding $finding) => [$finding->rule, $finding->severity->value, $finding->file, $finding->line], $scan->findings))->toBe([
        ['permission.undeclared', 'medium', 'src/Robot.php', 14],
        ['permission.unused', 'info', 'verso.json', null],
    ])
        ->and($scan->findings[0]->message)->toContain('manage_users')
        ->and($scan->findings[1]->message)->toContain('manage_terms');
});

it('reports hosts that are not in the network list', function () {
    $scan = new ScanResult;
    $scan->nameHost('api.stripe.com', 'src/Pay.php', 4);
    $scan->nameHost('files.stripe.com', 'src/Pay.php', 9);
    $scan->nameHost('collect.adnetwork-metrics.com', 'src/Track.php', 3);

    (new PermissionCheck)->check(tourManifest(network: ['*.stripe.com']), $scan);

    expect($scan->findings)->toHaveCount(1)
        ->and($scan->findings[0]->rule)->toBe('permission.undeclared-network')
        ->and($scan->findings[0]->message)->toContain('collect.adnetwork-metrics.com');
});

it('reports network calls to unknown hosts when no hosts are declared', function () {
    $scan = new ScanResult;
    $scan->callNetwork('src/Relay.php', 17);

    (new PermissionCheck)->check(tourManifest(), $scan);

    expect($scan->findings)->toHaveCount(1)
        ->and($scan->findings[0]->rule)->toBe('permission.undeclared-network')
        ->and($scan->findings[0]->line)->toBe(17);
});

it('matches wildcard hosts', function (string $host, bool $allowed) {
    expect((new PermissionCheck)->allows(['*.stripe.com', 'api.songkick.com'], $host))->toBe($allowed);
})->with([
    ['api.stripe.com', true],
    ['stripe.com', true],
    ['API.Songkick.com', true],
    ['songkick.com', false],
    ['evilstripe.com', false],
    ['stripe.com.evil.io', false],
]);

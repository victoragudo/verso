<?php

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Enums\AutomaticUpdates;
use App\Verso\Updates\Feed\Release;
use Tests\Support\UpdateSandbox;

function automaticRelease(string $version, bool $security = false): Release
{
    $release = UpdateSandbox::release($version);

    return new Release($release->version, $release->name, $release->notes, $release->publishedAt, false, $security, $release->packageUrl, $release->checksumUrl, null);
}

it('never installs on its own when off', function () {
    expect(AutomaticUpdates::Off->allows(automaticRelease('1.4.3', security: true), Version::parse('1.4.2')))->toBeFalse();
});

it('installs only security patches of the same minor version in security mode', function (string $version, bool $security, bool $expected) {
    expect(AutomaticUpdates::Security->allows(automaticRelease($version, $security), Version::parse('1.4.2')))->toBe($expected);
})->with([
    'security patch' => ['1.4.3', true, true],
    'plain patch' => ['1.4.3', false, false],
    'security minor' => ['1.5.0', true, false],
    'security major' => ['2.0.0', true, false],
]);

it('installs minor and patch releases of the same major version in minor mode', function (string $version, bool $expected) {
    expect(AutomaticUpdates::Minor->allows(automaticRelease($version), Version::parse('1.4.2')))->toBe($expected);
})->with([
    'patch' => ['1.4.3', true],
    'minor' => ['1.9.0', true],
    'major' => ['2.0.0', false],
    'older' => ['1.4.1', false],
    'same' => ['1.4.2', false],
]);

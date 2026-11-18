<?php

use App\Verso\Core\Support\Version;

it('reads the version from the VERSION file at the root', function () {
    $file = trim((string) file_get_contents(base_path('VERSION')));

    expect(config('verso.version'))->toBe($file)
        ->and(Version::isValid($file))->toBeTrue();
});

it('parses plain, tagged and pre-release versions', function (string $input, string $expected) {
    expect((string) Version::parse($input))->toBe($expected);
})->with([
    ['1.4.2', '1.4.2'],
    ['v2.0.0', '2.0.0'],
    ['3.1.0-beta.2', '3.1.0-beta.2'],
    ['1.0.0-rc.1+build.2026.11', '1.0.0-rc.1'],
    [' 0.9.12 ', '0.9.12'],
]);

it('refuses strings that are not versions', function (string $input) {
    expect(Version::tryParse($input))->toBeNull()
        ->and(Version::isValid($input))->toBeFalse();
})->with(['1.2', 'latest', '01.2.3', '1.2.3-', '1.2.3.4', 'release-1.2.3', '']);

it('throws on a broken version', function () {
    Version::parse('next-week');
})->throws(InvalidArgumentException::class, '"next-week" is not a valid version.');

it('compares versions with semver rules', function (string $a, string $b, int $expected) {
    expect(Version::compareStrings($a, $b))->toBe($expected)
        ->and(Version::compareStrings($b, $a))->toBe(-$expected);
})->with([
    ['1.0.0', '1.0.0', 0],
    ['1.0.1', '1.0.0', 1],
    ['1.10.0', '1.9.9', 1],
    ['2.0.0', '1.99.99', 1],
    ['1.0.0', '1.0.0-rc.1', 1],
    ['1.0.0-beta.11', '1.0.0-beta.2', 1],
    ['1.0.0-beta', '1.0.0-alpha.9', 1],
    ['1.0.0-alpha.beta', '1.0.0-alpha.1', 1],
    ['1.0.0-alpha.1', '1.0.0-alpha', 1],
    ['v1.2.3', '1.2.3+build.7', 0],
]);

it('answers newer, older and equal questions', function () {
    $current = Version::parse('1.4.2');

    expect(Version::parse('1.5.0')->isNewerThan($current))->toBeTrue()
        ->and(Version::parse('1.4.1')->isOlderThan($current))->toBeTrue()
        ->and(Version::parse('v1.4.2')->equals($current))->toBeTrue()
        ->and(Version::parse('1.4.2-rc.1')->isPreRelease())->toBeTrue()
        ->and($current->isPreRelease())->toBeFalse();
});

it('tells when two versions share the major or minor number', function () {
    $current = Version::parse('1.4.2');

    expect(Version::parse('1.9.0')->isSameMajor($current))->toBeTrue()
        ->and(Version::parse('2.0.0')->isSameMajor($current))->toBeFalse()
        ->and(Version::parse('1.4.7')->isSameMinor($current))->toBeTrue()
        ->and(Version::parse('1.5.0')->isSameMinor($current))->toBeFalse();
});

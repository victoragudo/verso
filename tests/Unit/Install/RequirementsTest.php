<?php

use App\Verso\Install\Requirement;
use App\Verso\Install\Requirements;
use Dom\HTMLDocument;

function requirements(array $overrides = []): Requirements
{
    return new Requirements(...array_merge([
        'phpVersion' => '8.4.0',
        'extensions' => ['json', 'pdo'],
        'writable' => [storage_path()],
        'basePath' => base_path(),
        'currentPhpVersion' => '8.4.12',
    ], $overrides));
}

it('passes on a good server', function () {
    $requirements = requirements();

    expect($requirements->passes())->toBeTrue()
        ->and($requirements->failures())->toBe([])
        ->and(array_map(fn (Requirement $requirement) => $requirement->label, $requirements->check()))
        ->toBe(['PHP 8.4.0 or newer', 'json extension', 'pdo extension', 'storage is writable']);
});

it('fails on an old PHP version', function () {
    $requirements = requirements(['currentPhpVersion' => '8.2.7']);

    expect($requirements->passes())->toBeFalse()
        ->and($requirements->failures()[0]->label)->toBe('PHP 8.4.0 or newer')
        ->and($requirements->failures()[0]->detail)->toBe('This server runs PHP 8.2.7.');
});

it('fails when an extension is missing', function () {
    $failures = requirements(['extensions' => ['json', 'imaginary_turbo']])->failures();

    expect($failures)->toHaveCount(1)
        ->and($failures[0]->group)->toBe('extensions')
        ->and($failures[0]->detail)->toContain('Ask your host');
});

it('fails when a folder is missing or read only', function () {
    $readOnly = storage_path('framework/testing/install-read-only');
    @mkdir($readOnly, 0755, true);
    chmod($readOnly, 0555);

    $failures = requirements(['writable' => [base_path('does-not-exist'), $readOnly]])->failures();
    chmod($readOnly, 0755);

    expect(array_map(fn (Requirement $requirement) => $requirement->label, $failures))
        ->toBe(['does-not-exist is writable', 'storage/framework/testing/install-read-only is writable']);
});

it('shows folders outside the project with their full path', function () {
    $label = requirements(['writable' => ['/var/tmp']])->check()[3]->label;

    expect($label)->toBe('/var/tmp is writable');
});

it('turns a requirement into an array', function () {
    expect((new Requirement('php', 'PHP 8.4.0 or newer', true, 'This server runs PHP 8.4.12.'))->toArray())->toBe([
        'group' => 'php',
        'label' => 'PHP 8.4.0 or newer',
        'passed' => true,
        'detail' => 'This server runs PHP 8.4.12.',
        'fix' => '',
    ]);
});

it('is built from config', function () {
    config(['verso.install.php_version' => '99.0.0']);

    expect(app(Requirements::class)->passes())->toBeFalse();
});

it('rejects PHP 8.3 because the HTML sanitizer needs PHP 8.4', function () {
    $requirements = requirements(['currentPhpVersion' => '8.3.14']);

    expect($requirements->passes())->toBeFalse()
        ->and($requirements->failures()[0]->detail)->toBe('This server runs PHP 8.3.14.');
});

it('accepts the first PHP 8.4 release', function () {
    expect(requirements(['currentPhpVersion' => '8.4.0'])->passes())->toBeTrue();
});

it('asks for PHP 8.4 by default', function () {
    expect(config('verso.install.php_version'))->toBe('8.4.0')
        ->and(app(Requirements::class)->check()[0]->label)->toBe('PHP 8.4.0 or newer');
});

it('matches the PHP version in composer.json', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require']['php'])->toBe('^8.4')
        ->and(class_exists(HTMLDocument::class))->toBeTrue();
});

it('needs one of the image extensions', function () {
    expect(requirements(['imageExtensions' => ['gd', 'imaginary_pixels']])->passes())->toBeTrue();

    $failure = requirements(['imageExtensions' => ['imaginary_pixels', 'imaginary_vectors']])->failures()[0];

    expect($failure->label)->toBe('imaginary_pixels or imaginary_vectors extension')
        ->and($failure->fix)->toContain('php.ini');
});

it('checks the memory limit', function (string $current, bool $passes) {
    $requirements = requirements(['memoryLimit' => '128M', 'currentMemoryLimit' => $current]);

    expect($requirements->passes())->toBe($passes);
})->with([
    'unlimited' => ['-1', true],
    'one gigabyte' => ['1G', true],
    'exactly enough' => ['128M', true],
    'too little' => ['64M', false],
]);

it('turns memory sizes into bytes', function () {
    expect(Requirements::bytes('2K'))->toBe(2048)
        ->and(Requirements::bytes('256M'))->toBe(268435456)
        ->and(Requirements::bytes('1g'))->toBe(1073741824)
        ->and(Requirements::bytes('-1'))->toBe(-1);
});

it('checks that the env file can be written', function () {
    $folder = storage_path('framework/testing/env-check-'.uniqid());
    mkdir($folder, 0755, true);

    expect(requirements(['envPath' => $folder.'/.env'])->passes())->toBeTrue();

    file_put_contents($folder.'/.env', "APP_NAME=Verso\n");
    chmod($folder.'/.env', 0444);
    $failure = requirements(['envPath' => $folder.'/.env'])->failures()[0] ?? null;
    chmod($folder.'/.env', 0644);

    expect($failure?->fix)->toContain('chmod 640');
});

it('gives every failed check a fix', function () {
    $failures = requirements(['currentPhpVersion' => '8.1.0', 'extensions' => ['imaginary_turbo'], 'writable' => [base_path('nowhere')]])->failures();

    expect($failures)->toHaveCount(3)
        ->and(array_filter(array_map(fn (Requirement $requirement) => $requirement->toArray()['fix'], $failures)))->toHaveCount(3);
});

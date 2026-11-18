<?php

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Packages\Package;
use App\Verso\Extensions\Packages\PackageExtractor;
use App\Verso\Extensions\Packages\PackageInspector;
use App\Verso\Extensions\Packages\PackageRules;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Tests\Support\PackageZip;

beforeEach(function () {
    $this->workDir = storage_path('framework/testing/extractor-'.uniqid());
});

afterEach(function () {
    File::deleteDirectory($this->workDir);
});

it('unpacks the package files without the top folder', function () {
    $zip = PackageZip::plugin('tour-dates')->add('tour-dates/assets/poster.svg', '<svg></svg>')->build($this->workDir.'/tour-dates.zip');
    $package = app(PackageInspector::class)->inspect($zip, ExtensionKind::Plugin);

    app(PackageExtractor::class)->extract($zip, $package, $this->workDir.'/out/tour-dates');

    expect(File::exists($this->workDir.'/out/tour-dates/verso.json'))->toBeTrue()
        ->and(File::get($this->workDir.'/out/tour-dates/assets/poster.svg'))->toBe('<svg></svg>')
        ->and(File::exists($this->workDir.'/out/tour-dates/src/TourDatesServiceProvider.php'))->toBeTrue()
        ->and(File::exists($this->workDir.'/out/tour-dates/tour-dates'))->toBeFalse();
});

it('refuses to unpack into a folder that already exists', function () {
    $zip = PackageZip::plugin()->build($this->workDir.'/tour-dates.zip');
    $package = app(PackageInspector::class)->inspect($zip, ExtensionKind::Plugin);
    File::ensureDirectoryExists($this->workDir.'/out/tour-dates');

    expect(fn () => app(PackageExtractor::class)->extract($zip, $package, $this->workDir.'/out/tour-dates'))
        ->toThrow(InvalidPackage::class, 'already exists');
});

it('stops and cleans up when the real size goes over the budget', function () {
    $zip = PackageZip::plugin()->add('tour-dates/assets/setlist.txt', str_repeat('Encore ', 4000))->build($this->workDir.'/tour-dates.zip');
    $package = app(PackageInspector::class)->inspect($zip, ExtensionKind::Plugin);

    $rules = new PackageRules(maxUploadBytes: 1_000_000, maxUnpackedBytes: 2048, maxFiles: 10, maxManifestBytes: 4096, allowedExtensions: ['txt'], allowedPlainFiles: []);
    $extractor = new PackageExtractor($rules, new Filesystem);

    expect(fn () => $extractor->extract($zip, $package, $this->workDir.'/out/tour-dates'))
        ->toThrow(InvalidPackage::class, 'unpacks to more than');

    expect(File::exists($this->workDir.'/out/tour-dates'))->toBeFalse();
});

it('fails on a file that is not a zip', function () {
    File::ensureDirectoryExists($this->workDir);
    File::put($this->workDir.'/broken.zip', 'not a zip');

    $package = new Package(ExtensionKind::Plugin, 'tour-dates', 'Tour Dates', '1.0.0', '', '', '*', [], ['verso.json' => 0], 10);

    expect(fn () => app(PackageExtractor::class)->extract($this->workDir.'/broken.zip', $package, $this->workDir.'/out'))
        ->toThrow(InvalidPackage::class, 'not a valid zip');
});

it('builds the rules from config', function () {
    config([
        'verso.extensions.max_upload_kb' => 512,
        'verso.extensions.allowed_extensions' => ['.PHP', ' json '],
    ]);

    $rules = PackageRules::fromConfig();

    expect($rules->maxUploadBytes)->toBe(512 * 1024)
        ->and($rules->allowedExtensions)->toBe(['php', 'json']);
});

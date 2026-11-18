<?php

use App\Verso\Extensions\Actions\StageRegistryPackage;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Registry\RegistryClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;

// Index URL used by every registry test.
const REGISTRY_URL = 'https://registry.verso.example/index.json';

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.registry.url' => REGISTRY_URL]);

    $this->tourDates = File::get(PackageZip::plugin('tour-dates', '1.4.0')->build($this->sandbox->zipPath('tour-dates')));
    $this->lighthouse = File::get(PackageZip::theme('lighthouse', '2.0.0')->build($this->sandbox->zipPath('lighthouse')));

    $this->index = str_replace(
        ['__TOUR_DATES_SHA__', '__LIGHTHOUSE_SHA__'],
        [hash('sha256', $this->tourDates), hash('sha256', $this->lighthouse)],
        File::get(base_path('tests/Fixtures/registry/index.json')),
    );

    $this->fakeRegistry = fn (array $overrides = []) => Http::fake([
        REGISTRY_URL => Http::response($this->index, 200, ['Content-Type' => 'application/json']),
        'registry.verso.example/packages/tour-dates-1.4.0.zip' => Http::response($this->tourDates),
        'registry.verso.example/packages/lighthouse-2.0.0.zip' => Http::response($this->lighthouse),
        ...$overrides,
    ]);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('lists the valid entries of the index and skips broken ones', function () {
    ($this->fakeRegistry)();

    $entries = app(RegistryClient::class)->entries();

    expect(array_map(fn ($entry) => $entry->kind->value.':'.$entry->slug, $entries))
        ->toBe(['plugin:tour-dates', 'theme:lighthouse', 'plugin:recipe-cards'])
        ->and($entries[0]->toArray())->toMatchArray(['name' => 'Tour Dates', 'version' => '1.4.0']);
});

it('caches the index', function () {
    ($this->fakeRegistry)();

    app(RegistryClient::class)->entries();
    app(RegistryClient::class)->entries();

    Http::assertSentCount(1);

    app(RegistryClient::class)->forget();
    app(RegistryClient::class)->entries();

    Http::assertSentCount(2);
});

it('accepts an index that is a plain list', function () {
    Http::fake([REGISTRY_URL => Http::response(json_decode($this->index, true)['extensions'])]);

    expect(app(RegistryClient::class)->entries())->toHaveCount(3);
});

it('is off when no registry URL is set', function () {
    config(['verso.extensions.registry.url' => '']);
    Http::fake();

    $client = app(RegistryClient::class);

    expect($client->isConfigured())->toBeFalse()
        ->and($client->entries())->toBe([]);

    Http::assertNothingSent();
});

it('reports a registry that cannot be reached', function () {
    Http::fake([REGISTRY_URL => Http::response('Service unavailable', 503)]);

    expect(fn () => app(RegistryClient::class)->entries())->toThrow(ExtensionInstallFailed::class, 'could not be reached');
});

it('downloads, checks and stages a registry package', function () {
    ($this->fakeRegistry)();

    $staged = app(StageRegistryPackage::class)->handle(ExtensionKind::Plugin, 'tour-dates');

    expect($staged->source)->toBe('registry')
        ->and($staged->package->version)->toBe('1.4.0')
        ->and($staged->sha256)->toBe(hash('sha256', $this->tourDates));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://registry.verso.example/packages/tour-dates-1.4.0.zip');

    $result = app(ExtensionInstaller::class)->install($staged->token);

    expect($result->version)->toBe('1.4.0')
        ->and(File::exists($this->sandbox->pluginsPath().'/tour-dates/verso.json'))->toBeTrue();
});

it('stages registry themes', function () {
    ($this->fakeRegistry)();

    expect(app(StageRegistryPackage::class)->handle(ExtensionKind::Theme, 'lighthouse')->package->kind)->toBe(ExtensionKind::Theme);
});

it('refuses a download whose sha256 sum does not match', function () {
    ($this->fakeRegistry)([
        'registry.verso.example/packages/tour-dates-1.4.0.zip' => Http::response(File::get(PackageZip::plugin('tour-dates', '1.4.0')->add('tour-dates/src/Backdoor.php', '<?php')->build($this->sandbox->zipPath('tampered')))),
    ]);

    expect(fn () => app(StageRegistryPackage::class)->handle(ExtensionKind::Plugin, 'tour-dates'))
        ->toThrow(ExtensionInstallFailed::class, 'does not match its sha256 sum');

    expect(File::exists($this->sandbox->root.'/storage/staging'))->toBeFalse()
        ->and(File::glob($this->sandbox->root.'/storage/tmp/*'))->toBe([]);
});

it('refuses a package that is not the one the registry lists', function () {
    $other = File::get(PackageZip::plugin('tour-dates', '1.3.0')->build($this->sandbox->zipPath('older')));
    $this->index = str_replace(hash('sha256', $this->tourDates), hash('sha256', $other), $this->index);

    ($this->fakeRegistry)(['registry.verso.example/packages/tour-dates-1.4.0.zip' => Http::response($other)]);

    expect(fn () => app(StageRegistryPackage::class)->handle(ExtensionKind::Plugin, 'tour-dates'))
        ->toThrow(ExtensionInstallFailed::class, 'but the registry lists tour-dates 1.4.0');
});

it('refuses unknown slugs, failed downloads and oversized downloads', function () {
    ($this->fakeRegistry)([
        'registry.verso.example/packages/lighthouse-2.0.0.zip' => Http::response('Not found', 404),
        'registry.verso.example/packages/tour-dates-1.4.0.zip' => Http::response(str_repeat('Backstage pass ', 400)),
    ]);

    expect(fn () => app(StageRegistryPackage::class)->handle(ExtensionKind::Plugin, 'weather-widget'))
        ->toThrow(ExtensionInstallFailed::class, 'is not in the registry')
        ->and(fn () => app(StageRegistryPackage::class)->handle(ExtensionKind::Theme, 'lighthouse'))
        ->toThrow(ExtensionInstallFailed::class, 'could not be downloaded');

    config(['verso.extensions.max_upload_kb' => 1]);

    expect(fn () => app(StageRegistryPackage::class)->handle(ExtensionKind::Plugin, 'tour-dates'))
        ->toThrow(ExtensionInstallFailed::class, 'is bigger than 1 KB');
});

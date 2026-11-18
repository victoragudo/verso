<?php

use Illuminate\Support\Facades\Artisan;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;
use Tests\Support\ThemeFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => false]);
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox->destroy();
});

it('verifies a clean theme folder and succeeds', function () {
    $this->artisan('verso:verify-theme', ['path' => ThemeFixtures::withScreenshot('harbor-light'), '--no-sandbox' => true])
        ->expectsOutputToContain('Harbor Light 1.2.0: Low risk (score 0)')
        ->expectsOutputToContain('theme.raw-output')
        ->assertSuccessful();
});

it('fails for a theme with missing views and names the blocking rules', function () {
    $this->artisan('verso:verify-theme', ['path' => ThemeFixtures::path('half-built'), '--no-sandbox' => true])
        ->expectsOutputToContain('Half Built 1.2.0: High risk')
        ->expectsOutputToContain('views/archive.blade.php')
        ->expectsOutputToContain('These findings stop the install: theme.missing-view.')
        ->assertFailed();
});

it('prints the report as JSON with the theme details', function () {
    Artisan::call('verso:verify-theme', ['path' => ThemeFixtures::path('driftwood'), '--json' => true, '--no-sandbox' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['slug'])->toBe('driftwood')
        ->and($report['risk'])->toBe('high')
        ->and($report['details']['blocking'])->toBe([])
        ->and($report['details']['theme']['quality']['lang'])->toBeFalse()
        ->and($report['findings'][0]['severity'])->toBe('high');
});

it('verifies a theme zip', function () {
    $zip = ThemeFixtures::zip(ThemeFixtures::withScreenshot('saltmarsh'))->build($this->sandbox->zipPath('saltmarsh'));

    $this->artisan('verso:verify-theme', ['path' => $zip, '--no-sandbox' => true])
        ->expectsOutputToContain('Saltmarsh 1.2.0: Low risk (score 0)')
        ->expectsOutputToContain('sandbox.skipped')
        ->assertSuccessful();

    expect(glob($this->sandbox->root.'/storage/verify/*') ?: [])->toBe([]);
});

it('refuses a zip that is not a valid theme package', function () {
    $zip = PackageZip::theme()->add('lighthouse/src/Tracker.php', "<?php\n")->build($this->sandbox->zipPath('lighthouse'));

    $this->artisan('verso:verify-theme', ['path' => $zip])
        ->expectsOutputToContain('The zip file is not a valid theme package.')
        ->assertExitCode(2);
});

it('reports a path that does not exist', function () {
    $this->artisan('verso:verify-theme', ['path' => base_path('themes/seaside-base')])
        ->expectsOutputToContain('No theme folder or zip file was found')
        ->assertExitCode(2);
});

it('renders the theme in the sandbox unless told not to', function () {
    config(['verso.extensions.verification.sandbox.enabled' => true]);

    Artisan::call('verso:verify-theme', ['path' => ThemeFixtures::path('broken-pier'), '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['details']['blocking'])->toBe(['theme.render-failed'])
        ->and($report['details']['sandbox']['status'])->toBe('failed');
});

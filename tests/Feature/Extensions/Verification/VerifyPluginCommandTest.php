<?php

use Illuminate\Support\Facades\Artisan;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.plugins.composer_installed' => null]);
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

it('verifies a clean plugin folder', function () {
    $this->artisan('verso:verify-plugin', ['path' => VerificationFixtures::path('harbor-tides')])
        ->expectsOutputToContain('Harbor Tides 1.2.0: Low risk (score 0)')
        ->expectsOutputToContain('No findings.')
        ->assertSuccessful();
});

it('prints the findings as a table and fails for high risk plugins', function () {
    $this->artisan('verso:verify-plugin', ['path' => VerificationFixtures::path('backup-runner'), '--no-sandbox' => true])
        ->expectsOutputToContain('Backup Runner 1.2.0: High risk')
        ->expectsOutputToContain('| high     | php.shell')
        ->expectsOutputToContain('src/Backup.php')
        ->assertFailed();
});

it('prints the report as JSON', function () {
    Artisan::call('verso:verify-plugin', ['path' => VerificationFixtures::path('auto-publisher'), '--json' => true, '--no-sandbox' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['slug'])->toBe('auto-publisher')
        ->and($report['risk'])->toBe('high')
        ->and($report['counts']['medium'])->toBe(5)
        ->and($report['findings'][0])->toBe([
            'rule' => 'permission.undeclared',
            'severity' => 'medium',
            'message' => 'The code needs "publish_entries", but verso.json does not ask for it.',
            'file' => 'src/Publisher.php',
            'line' => 6,
        ])
        ->and($report['details']['sandbox']['status'])->toBe('skipped');
});

it('verifies a zip file and cleans up after it', function () {
    $zip = VerificationFixtures::zip('weather-widget')->build($this->sandbox->zipPath('weather-widget'));

    $this->artisan('verso:verify-plugin', ['path' => $zip, '--no-sandbox' => true])
        ->expectsOutputToContain('Weather Widget 1.2.0: High risk')
        ->expectsOutputToContain('collect.adnetwork-metrics.com')
        ->assertFailed();

    expect(glob($this->sandbox->root.'/storage/verify/*') ?: [])->toBe([]);
});

it('refuses a zip that is not a safe plugin package', function () {
    $zip = PackageZip::plugin()->add('../../public/shell.php', '<?php')->build($this->sandbox->zipPath('unsafe'));

    $this->artisan('verso:verify-plugin', ['path' => $zip])
        ->expectsOutputToContain('The zip file is not a valid plugin package.')
        ->expectsOutputToContain('points outside the package folder')
        ->assertExitCode(2);
});

it('complains about a path that does not exist', function () {
    $this->artisan('verso:verify-plugin', ['path' => '/srv/plugins/missing-gallery'])
        ->expectsOutputToContain('No plugin folder or zip file was found')
        ->assertExitCode(2);
});

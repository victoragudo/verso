<?php

use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\PluginVerifier;
use App\Verso\Extensions\Verification\VerificationReport;
use Tests\Support\ExtensionSandbox;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.plugins.composer_installed' => null, 'verso.extensions.verification.sandbox.enabled' => true]);
    $this->verify = fn (string $slug, bool $sandbox = false): VerificationReport => app(PluginVerifier::class)->verify(VerificationFixtures::path($slug), $sandbox);
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

// The distinct rule ids of a report.
function reportRules(VerificationReport $report): array
{
    $rules = array_values(array_unique(array_map(fn (Finding $finding): string => $finding->rule, $report->findings)));
    sort($rules);

    return $rules;
}

it('gives a clean plugin a low risk with no findings after a sandbox boot', function () {
    $report = ($this->verify)('harbor-tides', true);

    expect($report->risk)->toBe(RiskLevel::Low)
        ->and($report->score)->toBe(0)
        ->and($report->findings)->toBe([])
        ->and($report->slug)->toBe('harbor-tides')
        ->and($report->version)->toBe('1.2.0')
        ->and($report->details['name'])->toBe('Harbor Tides')
        ->and($report->details['files'])->toBe(5)
        ->and($report->details['sandbox']['status'])->toBe('passed')
        ->and($report->details['permissions'])->toBe(['declared' => [], 'used' => [], 'network' => [], 'hosts' => []]);
});

it('marks obfuscated code as high risk', function () {
    $report = ($this->verify)('quiet-optimizer');

    expect($report->risk)->toBe(RiskLevel::High)
        ->and(reportRules($report))->toBe([
            'php.assert-string', 'php.create-function', 'php.dynamic-function', 'php.dynamic-include', 'php.dynamic-variable',
            'php.encoded-string', 'php.eval', 'php.obfuscated-code', 'php.preg-replace-eval', 'php.shell', 'sandbox.skipped',
        ])
        ->and($report->findings[0]->file)->toBe('src/Support/Warmer.php');
});

it('reports undeclared network hosts and external scripts', function () {
    $report = ($this->verify)('weather-widget');
    $hosts = array_values(array_filter($report->findings, fn (Finding $finding) => $finding->rule === 'permission.undeclared-network'));

    expect($report->risk)->toBe(RiskLevel::High)
        ->and(array_map(fn (Finding $finding) => $finding->file, $hosts))->toBe(['assets/widget.js', 'resources/views/widget.blade.php', 'src/Forecast.php', 'src/Forecast.php'])
        ->and(reportRules($report))->toContain('blade.external-script', 'js.external-request', 'php.network')
        ->and($report->details['permissions']['hosts'])->toBe(['api.open-meteo.com', 'cdn.weatherwidgets.io', 'collect.adnetwork-metrics.com', 'geo.lookup-service.net', 'stats.weatherwidgets.io'])
        ->and($report->details['permissions']['network'])->toBe(['api.open-meteo.com']);
});

it('marks shell commands as high risk', function () {
    $report = ($this->verify)('backup-runner');

    expect($report->risk)->toBe(RiskLevel::High)
        ->and(reportRules($report))->toBe([
            'permission.unused', 'php.dl', 'php.environment', 'php.file-write', 'php.raw-sql', 'php.shell', 'php.superglobal', 'sandbox.skipped',
        ])
        ->and(count(array_filter($report->findings, fn (Finding $finding) => $finding->rule === 'php.shell')))->toBe(9);
});

it('lists the permissions the code uses without declaring them', function () {
    $report = ($this->verify)('auto-publisher');

    expect($report->details['permissions']['used'])->toBe(['delete_entries', 'manage_settings', 'manage_users', 'publish_entries', 'upload_files'])
        ->and($report->details['permissions']['declared'])->toBe(['manage_terms'])
        ->and(count(array_filter($report->findings, fn (Finding $finding) => $finding->rule === 'permission.undeclared')))->toBe(5)
        ->and($report->risk)->toBe(RiskLevel::High);
});

it('reports every clash of a plugin that reuses names of the site', function () {
    $report = ($this->verify)('calendar-clash', true);

    expect(reportRules($report))->toBe([
        'compat.class-clash', 'compat.composer-require', 'compat.config-clash', 'compat.php-extension', 'compat.php-version',
        'compat.route-clash', 'compat.slug-clash', 'compat.table-clash', 'compat.verso-version', 'sandbox.migration-failed',
    ])->and($report->risk)->toBe(RiskLevel::High);
});

it('reports a broken migration from the sandbox', function () {
    $report = ($this->verify)('broken-ledger', true);

    expect($report->risk)->toBe(RiskLevel::High)
        ->and($report->findings)->toHaveCount(1)
        ->and($report->findings[0]->rule)->toBe('sandbox.migration-failed')
        ->and($report->findings[0]->file)->toBe('database/migrations/2026_12_01_000000_create_donations_table.php')
        ->and($report->findings[0]->line)->toBe(17);
});

it('reports a provider that cannot boot', function () {
    $report = ($this->verify)('stormy-boot', true);

    expect(reportRules($report))->toBe(['sandbox.boot-failed'])
        ->and($report->findings[0]->message)->toBe('The plugin could not boot: RuntimeException: The storm warning feed URL is missing.');
});

it('reports a broken manifest as high risk without scanning', function () {
    $path = VerificationFixtures::make('tour-dates', ['verso.json' => '{"name": "Tour Dates"}']);

    $report = app(PluginVerifier::class)->verify($path, false);

    expect($report->risk)->toBe(RiskLevel::High)
        ->and(reportRules($report))->toBe(['manifest.invalid'])
        ->and($report->slug)->toBe('tour-dates');
});

it('skips files that are too big and folders like node_modules', function () {
    config(['verso.extensions.verification.max_file_kb' => 1]);
    $path = VerificationFixtures::make('tour-dates', [
        'assets/big.js' => str_repeat("console.log('Lisbon');\n", 100),
        'node_modules/left-pad/index.js' => "eval('1');\n",
        'assets/readme.txt' => "eval('1');\n",
    ]);

    $report = app(PluginVerifier::class)->verify($path, false);

    expect(reportRules($report))->toBe(['sandbox.skipped', 'scan.file-too-large'])
        ->and($report->details['files'])->toBe(1);
});

it('scans only the configured script extensions', function () {
    config(['verso.extensions.verification.script_extensions' => ['mjs']]);
    $path = VerificationFixtures::make('tour-dates', ['assets/a.js' => "eval('1');\n", 'assets/b.mjs' => "eval('2');\n"]);

    $report = app(PluginVerifier::class)->verify($path, false);

    expect(array_map(fn (Finding $finding) => $finding->file, array_values(array_filter($report->findings, fn (Finding $finding) => $finding->rule === 'js.eval'))))->toBe(['assets/b.mjs']);
});

it('rates the bundled plugins as low or medium risk', function (string $slug) {
    $report = app(PluginVerifier::class)->verify(base_path('plugins/'.$slug), false);

    expect($report->risk)->not->toBe(RiskLevel::High)
        ->and(array_filter($report->findings, fn (Finding $finding) => str_starts_with($finding->rule, 'permission.undeclared')))->toBe([]);
})->with(['ai-assistant', 'analytics', 'memberships', 'newsletter', 'reading-time', 'shop']);

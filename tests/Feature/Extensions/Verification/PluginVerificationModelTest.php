<?php

use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\VerificationReport;

// A report for the Tour Dates plugin with the given findings.
function tourDatesReport(array $findings = [], string $version = '1.0.0'): VerificationReport
{
    return VerificationReport::make('tour-dates', $version, $findings, ['name' => 'Tour Dates']);
}

it('stores a report with its findings', function () {
    $verification = PluginVerification::record(
        tourDatesReport([new Finding('php.shell', Severity::High, 'exec() runs a shell command.', 'src/Backup.php', 12)]),
        PluginVerification::SOURCE_UPLOAD,
        str_repeat('a', 64),
        '0b9d2f8e-3c1a-4c55-9e0f-6a7b8c9d0e1f',
    )->fresh();

    expect($verification->slug)->toBe('tour-dates')
        ->and($verification->risk)->toBe(RiskLevel::High)
        ->and($verification->score)->toBe(25)
        ->and($verification->source)->toBe('upload')
        ->and($verification->findings)->toBe([['rule' => 'php.shell', 'severity' => 'high', 'message' => 'exec() runs a shell command.', 'file' => 'src/Backup.php', 'line' => 12]])
        ->and($verification->report()->findings[0]->rule)->toBe('php.shell')
        ->and($verification->toReportArray())->toMatchArray(['risk' => 'high', 'source' => 'upload', 'installedAt' => null]);
});

it('finds the report of a staged zip only while the zip is the same', function () {
    $token = '0b9d2f8e-3c1a-4c55-9e0f-6a7b8c9d0e1f';
    $verification = PluginVerification::record(tourDatesReport(), 'upload', str_repeat('a', 64), $token);

    expect(PluginVerification::forStaged($token, str_repeat('a', 64))?->is($verification))->toBeTrue()
        ->and(PluginVerification::forStaged($token, str_repeat('b', 64)))->toBeNull();
});

it('keeps only the newest reports and the installed one', function () {
    config(['verso.extensions.verification.reports_kept' => 2]);

    $installed = PluginVerification::record(tourDatesReport(version: '1.0.0'), 'installed');
    $installed->markInstalled();

    foreach (['1.1.0', '1.2.0', '1.3.0'] as $version) {
        PluginVerification::record(tourDatesReport(version: $version), 'upload');
    }

    expect(PluginVerification::query()->orderBy('id')->pluck('version')->all())->toBe(['1.0.0', '1.2.0', '1.3.0'])
        ->and(PluginVerification::latestFor('tour-dates')?->version)->toBe('1.3.0');
});

it('moves the installed mark to the newest install', function () {
    $old = PluginVerification::record(tourDatesReport(version: '1.0.0'), 'upload');
    $old->markInstalled();
    $new = PluginVerification::record(tourDatesReport(version: '1.1.0'), 'upload', null, '0b9d2f8e-3c1a-4c55-9e0f-6a7b8c9d0e1f');
    $new->markInstalled();

    expect($old->fresh()->installed_at)->toBeNull()
        ->and($new->fresh()->installed_at)->not->toBeNull()
        ->and($new->fresh()->staging_token)->toBeNull();
});

it('asks for the slug before a high risk install', function () {
    $high = PluginVerification::record(tourDatesReport([new Finding('php.eval', Severity::High, 'Runs eval.')]), 'upload');
    $low = PluginVerification::record(tourDatesReport(), 'upload');

    expect($low->installProblem(null, true))->toBeNull()
        ->and($high->installProblem(null, false))->toContain('Type "tour-dates"')
        ->and($high->installProblem('tour-date', false))->toContain('Type "tour-dates"')
        ->and($high->installProblem(' tour-dates ', false))->toBeNull()
        ->and($high->installProblem('tour-dates', true))->toBe('This plugin is high risk, and this site blocks high risk plugins.');
});

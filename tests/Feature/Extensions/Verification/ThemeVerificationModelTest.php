<?php

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\VerificationReport;

// A stored theme report of Harbor Light with the given findings and blocking rules.
function harborLightReport(array $findings = [], array $blocking = []): VerificationReport
{
    return VerificationReport::make('harbor-light', '1.2.0', $findings, ['name' => 'Harbor Light', 'blocking' => $blocking]);
}

it('stores the kind and lists the blocking rules', function () {
    $verification = PluginVerification::record(harborLightReport([new Finding('theme.missing-view', Severity::High, 'The required view "404" is missing.')], ['theme.missing-view']), 'upload', kind: ExtensionKind::Theme);

    expect($verification->fresh()?->kind)->toBe(ExtensionKind::Theme)
        ->and($verification->blockingRules())->toBe(['theme.missing-view'])
        ->and($verification->toReportArray())->toMatchArray(['kind' => 'theme', 'blocking' => ['theme.missing-view']]);
});

it('treats old reports without a kind as plugin reports', function () {
    $verification = PluginVerification::query()->create([
        'slug' => 'tour-dates', 'version' => '1.0.0', 'source' => 'upload', 'risk' => 'low', 'score' => 0, 'findings' => [],
    ]);

    expect($verification->kind)->toBe(ExtensionKind::Plugin)
        ->and($verification->fresh()?->kind)->toBe(ExtensionKind::Plugin);
});

it('never allows a theme with blocking rules, whatever is typed', function () {
    $verification = PluginVerification::record(harborLightReport(blocking: ['theme.render-failed']), 'upload', kind: ExtensionKind::Theme);

    expect($verification->installProblem('harbor-light', false))->toBe('This theme cannot be installed: theme.render-failed. Fix the findings and upload it again.');
});

it('asks for the slug of a high risk theme, but does not apply the plugin block switch', function () {
    $verification = PluginVerification::record(harborLightReport([new Finding('theme.raw-user-output', Severity::High, 'Prints request data.')]), 'upload', kind: ExtensionKind::Theme);

    expect($verification->installProblem('', true))->toBe('This theme is high risk. Type "harbor-light" to confirm the install.')
        ->and($verification->installProblem('harbor-light', true))->toBeNull();
});

it('prunes and marks installed reports per kind', function () {
    config(['verso.extensions.verification.reports_kept' => 1]);

    $plugin = PluginVerification::record(harborLightReport(), 'upload');
    $plugin->markInstalled();
    PluginVerification::record(harborLightReport(), 'upload', kind: ExtensionKind::Theme);
    $theme = PluginVerification::record(harborLightReport(), 'upload', kind: ExtensionKind::Theme);
    $theme->markInstalled();

    expect(PluginVerification::query()->where('kind', 'theme')->count())->toBe(1)
        ->and($plugin->fresh()?->installed_at)->not->toBeNull()
        ->and($theme->fresh()?->installed_at)->not->toBeNull();
});

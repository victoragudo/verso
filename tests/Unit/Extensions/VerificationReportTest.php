<?php

use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\VerificationReport;

it('reads severity weights from config', function () {
    config(['verso.extensions.verification.weights' => ['info' => 0, 'low' => 3, 'medium' => 7, 'high' => 40]]);

    expect(Severity::Low->weight())->toBe(3)
        ->and(Severity::Medium->weight())->toBe(7)
        ->and(Severity::High->weight())->toBe(40)
        ->and(Severity::Info->weight())->toBe(0);
});

it('picks the risk level from the score and high findings', function (int $score, bool $high, RiskLevel $expected) {
    config(['verso.extensions.verification.medium_score' => 6, 'verso.extensions.verification.high_score' => 25]);

    expect(RiskLevel::fromScore($score, $high))->toBe($expected);
})->with([
    'nothing' => [0, false, RiskLevel::Low],
    'a few lows' => [5, false, RiskLevel::Low],
    'medium threshold' => [6, false, RiskLevel::Medium],
    'high threshold' => [25, false, RiskLevel::High],
    'one high finding' => [0, true, RiskLevel::High],
]);

it('counts code rules once per file and other rules once per problem', function () {
    $findings = [
        new Finding('php.dynamic-function', Severity::Low, 'Calls $format.', 'src/Prices.php', 10),
        new Finding('php.dynamic-function', Severity::Low, 'Calls $format.', 'src/Prices.php', 11),
        new Finding('php.dynamic-function', Severity::Low, 'Calls $render.', 'src/Views.php', 4),
        new Finding('permission.undeclared', Severity::Medium, 'Needs publish_entries.', 'src/Publisher.php', 6),
        new Finding('permission.undeclared', Severity::Medium, 'Needs manage_users.', 'src/Publisher.php', 9),
        new Finding('permission.unused', Severity::Info, 'Asks for manage_terms.', 'verso.json'),
    ];

    expect(VerificationReport::score($findings))->toBe(2 + 2 + 6 + 6);
});

it('sorts findings with the most serious first and counts them', function () {
    $report = VerificationReport::make('tour-dates', '1.0.0', [
        new Finding('php.superglobal', Severity::Low, 'Reads $_GET.', 'src/B.php', 3),
        new Finding('php.eval', Severity::High, 'Runs eval.', 'src/A.php', 9),
        new Finding('php.raw-sql', Severity::Medium, 'Runs SQL.', 'src/A.php', 2),
        new Finding('php.eval', Severity::High, 'Runs eval.', 'src/A.php', 4),
    ], ['name' => 'Tour Dates']);

    expect(array_map(fn (Finding $finding) => [$finding->rule, $finding->line], $report->findings))->toBe([
        ['php.eval', 4], ['php.eval', 9], ['php.raw-sql', 2], ['php.superglobal', 3],
    ])
        ->and($report->risk)->toBe(RiskLevel::High)
        ->and($report->counts())->toBe(['info' => 0, 'low' => 1, 'medium' => 1, 'high' => 2])
        ->and($report->toArray())->toMatchArray(['slug' => 'tour-dates', 'version' => '1.0.0', 'risk' => 'high', 'score' => 33, 'details' => ['name' => 'Tour Dates']]);
});

it('turns findings into arrays and back', function () {
    $finding = new Finding('php.shell', Severity::High, 'exec() runs a shell command.', 'src/Backup.php', 12);

    expect(Finding::fromArray($finding->toArray()))->toEqual($finding)
        ->and(Finding::fromArray(['rule' => 'php.eval', 'severity' => 'unknown']))->toEqual(new Finding('php.eval', Severity::Info, ''));
});

<?php

use App\Verso\Easy\Styles\ContrastChecker;

// tests/Fixtures/contrast.json holds cases that both the PHP and the TS tests check.
function contrastFixture(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/contrast.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('uses the shared minimums and checks', function () {
    $fixture = contrastFixture();

    expect($fixture['minimums'])->toBe(['body' => ContrastChecker::BODY_TEXT, 'large' => ContrastChecker::LARGE_TEXT])
        ->and($fixture['checks'])->toBe(array_keys(ContrastChecker::CHECKS));
});

it('measures contrast ratios like the shared fixture', function (string $foreground, string $background, float $ratio) {
    expect(round((new ContrastChecker)->ratio($foreground, $background), 2))->toBe($ratio);
})->with(fn (): array => array_map(fn (array $case): array => [$case['foreground'], $case['background'], (float) $case['ratio']], contrastFixture()['ratios']));

it('warns about palettes like the shared fixture', function (array $colors, array $expected) {
    $warnings = array_map(
        fn (array $warning): array => ['key' => $warning['key'], 'fix' => $warning['fix'], 'direction' => $warning['direction'], 'message' => $warning['message']],
        (new ContrastChecker)->review($colors),
    );

    expect($warnings)->toBe($expected);
})->with(fn (): array => array_combine(
    array_column(contrastFixture()['reviews'], 'name'),
    array_map(fn (array $case): array => [$case['colors'], $case['warnings']], contrastFixture()['reviews']),
));

it('fixes colors like the shared fixture', function (string $color, string $other, float $minimum, string $expected) {
    $checker = new ContrastChecker;
    $fixed = $checker->fix($color, $other, $minimum);

    expect($fixed)->toBe($expected)
        ->and($checker->ratio($fixed, $other))->toBeGreaterThanOrEqual($minimum);
})->with(fn (): array => array_map(fn (array $case): array => [$case['color'], $case['other'], (float) $case['minimum'], $case['expected']], contrastFixture()['fixes']));

it('knows black on white passes and pale grey on white fails', function () {
    $checker = new ContrastChecker;

    expect($checker->passes('#000000', '#ffffff'))->toBeTrue()
        ->and($checker->passes('#c9c6bf', '#ffffff'))->toBeFalse()
        ->and($checker->passes('#949494', '#ffffff', ContrastChecker::LARGE_TEXT))->toBeTrue()
        ->and($checker->direction('#1b2420'))->toBe('lighter')
        ->and($checker->review(['text' => 'tomato', 'background' => '#ffffff']))->toBe([]);
});

it('agrees with the page editor on hard to read sections and their fixes', function (array $colors, array $blocks, array $warnings, array $fixed) {
    $checker = new ContrastChecker;
    $resolve = fn (string $value): string => str_starts_with($value, 'var:') ? $colors[substr($value, 4)] : $value;

    foreach (array_merge(...array_column($warnings, 'groups')) as $group) {
        expect($checker->passes($group['foreground'], $group['background'], (float) $group['minimum']))->toBeFalse();
        expect($checker->passes($resolve($fixed[$group['originId']]), $group['background'], (float) $group['minimum']))->toBeTrue();
    }

    expect(array_keys($fixed))->toBe(array_column(array_merge(...array_column($warnings, 'groups')), 'originId'))
        ->and($blocks)->not->toBeEmpty();
})->with(fn (): array => array_combine(
    array_column(contrastFixture()['sections']['cases'], 'name'),
    array_map(fn (array $case): array => [contrastFixture()['sections']['colors'], $case['blocks'], $case['warnings'], $case['fixed']], contrastFixture()['sections']['cases']),
));

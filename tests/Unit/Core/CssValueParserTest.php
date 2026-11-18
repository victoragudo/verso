<?php

use App\Verso\Core\Css\CssUnits;
use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Core\Css\CssValueType;

// resources/js/lib/css-value.ts checks the same values in TypeScript. Both read this file.
function cssValueFixture(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/css-values.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('has the same unit lists as the TypeScript side', function () {
    $units = cssValueFixture()['units'];

    expect(CssUnits::COMMON)->toBe($units['common'])
        ->and(CssUnits::LENGTH_GROUPS)->toBe($units['lengthGroups'])
        ->and(CssUnits::ANGLE)->toBe($units['angle'])
        ->and(CssUnits::TIME)->toBe($units['time'])
        ->and(CssUnits::KEYWORDS)->toBe($units['keywords'])
        ->and(CssUnits::FUNCTIONS)->toBe($units['functions'])
        ->and(CssUnits::MAX_LENGTH)->toBe($units['maxLength'])
        ->and(CssUnits::MAX_DEPTH)->toBe($units['maxDepth']);
});

it('normalizes every shared case', function (array $case) {
    $rule = CssValueRule::fromArray(cssValueFixture()['rules'][$case['rule']]);

    expect((new CssValueParser)->normalize($case['value'], $rule))->toBe($case['css']);
})->with(fn (): array => collect(cssValueFixture()['cases'])->mapWithKeys(fn (array $case): array => [$case['name'] => [$case]])->all());

it('gives the same answer for its own output', function () {
    $fixture = cssValueFixture();
    $parser = new CssValueParser;

    foreach ($fixture['cases'] as $case) {
        if ($case['css'] !== null) {
            expect($parser->normalize($case['css'], CssValueRule::fromArray($fixture['rules'][$case['rule']])))->toBe($case['css']);
        }
    }
});

it('never lets a value close the rule or load a file', function (string $value) {
    expect((new CssValueParser)->normalize($value, new CssValueRule))->toBeNull();
})->with([
    'calc(1px)} body {display:none',
    'calc(1px); background: url(https://tracker.example/pixel.gif)',
    'var(--gap, url(x))',
    "calc(1px)\n}",
    'calc(1px) !important',
    'min(1px, 2px) /*',
    '<script>',
    'calc(1px + javascript:alert(1))',
]);

it('lists every length unit', function () {
    expect(CssUnits::lengths())->toHaveCount(50)
        ->toContain('px', 'Q', 'rlh', 'dvmax', 'cqmin', '%');
});

it('reads property rules with the right keywords', function () {
    expect(CssValueRule::forProperty('width')->keywords)->toBe(['auto', 'fit-content', 'min-content', 'max-content'])
        ->and(CssValueRule::forProperty('max-width')->keywords)->toContain('none')
        ->and(CssValueRule::forProperty('margin-top')->keywords)->toBe(['auto'])
        ->and(CssValueRule::forProperty('margin-top')->negative)->toBeTrue()
        ->and(CssValueRule::forProperty('padding-left')->negative)->toBeFalse()
        ->and(CssValueRule::forProperty('border-top-left-radius')->negative)->toBeFalse()
        ->and(CssValueRule::forProperty('line-height')->unitless)->toBeTrue()
        ->and(CssValueRule::forProperty('rotate', 'angle')->units)->toBe(CssUnits::ANGLE)
        ->and(CssValueRule::forProperty('outline-offset')->toArray())->toBe((new CssValueRule)->toArray());
});

it('refuses unknown units and keywords in code', function () {
    new CssValueRule(units: ['furlong']);
})->throws(InvalidArgumentException::class, 'furlong');

it('refuses unknown keywords in code', function () {
    new CssValueRule(keywords: ['inherit']);
})->throws(InvalidArgumentException::class, 'inherit');

it('keeps only allowed units when a theme limits them', function () {
    $rule = (new CssValueRule(CssValueType::Length))->onlyUnits(['rem', '%', 'furlong']);

    expect($rule->units)->toBe(['rem', '%'])
        ->and((new CssValueParser)->valid('12px', $rule))->toBeFalse()
        ->and((new CssValueParser)->valid('1.5rem', $rule))->toBeTrue();
});

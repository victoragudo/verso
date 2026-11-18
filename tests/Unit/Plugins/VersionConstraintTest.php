<?php

use App\Verso\Plugins\Support\VersionConstraint;

it('matches versions against constraints', function (string $version, string $constraint, bool $expected) {
    expect(VersionConstraint::satisfies($version, $constraint))->toBe($expected);
})->with([
    'any version' => ['0.1.0', '*', true],
    'empty constraint' => ['3.2.1', '', true],
    'caret on zero major' => ['0.1.4', '^0.1', true],
    'caret on zero major blocks next minor' => ['0.2.0', '^0.1', false],
    'caret on major' => ['1.9.0', '^1.2', true],
    'caret blocks next major' => ['2.0.0', '^1.2', false],
    'caret below minimum' => ['1.1.9', '^1.2', false],
    'caret on 0.0.x' => ['0.0.4', '^0.0.3', false],
    'tilde with minor' => ['1.8.0', '~1.2', true],
    'tilde with minor blocks major' => ['2.0.0', '~1.2', false],
    'tilde with patch' => ['1.2.9', '~1.2.3', true],
    'tilde with patch blocks minor' => ['1.3.0', '~1.2.3', false],
    'greater or equal' => ['0.1.0', '>=0.1.0', true],
    'greater' => ['0.1.0', '>0.1.0', false],
    'lower' => ['0.9.9', '<1.0', true],
    'lower or equal' => ['1.0.0', '<=1.0', true],
    'exact' => ['0.1.0', '0.1.0', true],
    'exact with equals' => ['0.1.1', '=0.1.0', false],
    'range' => ['1.5.0', '>=1.0 <2.0', true],
    'range upper bound' => ['2.0.0', '>=1.0 <2.0', false],
    'alternatives' => ['2.1.0', '^1.0 || ^2.0', true],
    'leading v' => ['0.1.0', '^v0.1', true],
    'garbage' => ['0.1.0', 'latest', false],
]);

it('tells valid constraints from invalid ones', function (string $constraint, bool $valid) {
    expect(VersionConstraint::isValid($constraint))->toBe($valid);
})->with([
    ['^0.1', true],
    ['*', true],
    ['>=1.0 <2.0', true],
    ['^1.0 || ^2.0', true],
    ['latest', false],
    ['^1.0 ||', false],
    ['>> 1.0', false],
]);

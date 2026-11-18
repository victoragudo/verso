<?php

use App\Verso\System\Config\Unit;

it('writes an amount with the unit and the right plural form', function (Unit $unit, int|float $count, string $expected) {
    expect($unit->amount($count))->toBe($expected);
})->with([
    'one second' => [Unit::Seconds, 1, '1 second'],
    'many hours' => [Unit::Hours, 36, '36 hours'],
    'one item' => [Unit::Items, 1, '1 item'],
    'kilobytes' => [Unit::Kilobytes, 10240, '10,240 KB'],
    'percent' => [Unit::Percent, 85, '85%'],
    'no unit' => [Unit::None, 1500, '1,500'],
]);

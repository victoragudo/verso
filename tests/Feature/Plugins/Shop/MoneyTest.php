<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Support\Money;

beforeEach(function () {
    ShopFixtures::boot();
    $this->money = new Money(['JPY', 'KRW'], 'en');
});

it('knows how many decimals a currency has', function () {
    expect($this->money->decimals('EUR'))->toBe(2)
        ->and($this->money->decimals('usd'))->toBe(2)
        ->and($this->money->decimals('JPY'))->toBe(0)
        ->and($this->money->decimals('krw'))->toBe(0);
});

it('formats minor units for people', function () {
    expect($this->money->format(3450, 'EUR'))->toBe('€34.50')
        ->and($this->money->format(129900, 'USD'))->toBe('$1,299.00')
        ->and($this->money->format(5, 'GBP'))->toBe('£0.05')
        ->and($this->money->format(1500, 'JPY'))->toBe('¥1,500');
});

it('formats with the site locale', function () {
    $money = new Money([], 'de');

    expect($money->format(3450, 'EUR'))->toBe("34,50\u{a0}€");
});

it('turns minor units into plain decimals for forms', function (int $minor, string $currency, string $expected) {
    expect($this->money->toDecimal($minor, $currency))->toBe($expected);
})->with([
    [3450, 'EUR', '34.50'],
    [5, 'EUR', '0.05'],
    [0, 'EUR', '0.00'],
    [100000, 'USD', '1000.00'],
    [-250, 'EUR', '-2.50'],
    [1500, 'JPY', '1500'],
]);

it('reads typed amounts into minor units', function (string $typed, string $currency, int $expected) {
    expect($this->money->toMinor($typed, $currency))->toBe($expected);
})->with([
    ['34.50', 'EUR', 3450],
    ['34.5', 'EUR', 3450],
    ['34', 'EUR', 3400],
    ['0.05', 'EUR', 5],
    ['.99', 'EUR', 99],
    ['1,299.99', 'USD', 129999],
    [' 12.00 ', 'EUR', 1200],
    ['1500', 'JPY', 1500],
    ['-3.20', 'EUR', -320],
]);

it('rounds extra digits half up', function (string $typed, int $expected) {
    expect($this->money->toMinor($typed, 'EUR'))->toBe($expected);
})->with([
    ['19.994', 1999],
    ['19.995', 2000],
    ['0.005', 1],
    ['0.0049', 0],
    ['2.675', 268],
]);

it('rounds yen amounts to whole units', function () {
    expect($this->money->toMinor('1499.5', 'JPY'))->toBe(1500)
        ->and($this->money->toMinor('1499.4', 'JPY'))->toBe(1499);
});

it('does not suffer from float errors', function () {
    expect($this->money->toMinor('0.29', 'EUR'))->toBe(29)
        ->and($this->money->toMinor('1.15', 'EUR'))->toBe(115)
        ->and($this->money->toMinor(0.1 + 0.2, 'EUR'))->toBe(30);
});

it('refuses text that is not an amount', function (string $typed) {
    $this->money->toMinor($typed, 'EUR');
})->with(['twelve', '12.50 EUR', '', '.', '1.2.3'])->throws(InvalidArgumentException::class);

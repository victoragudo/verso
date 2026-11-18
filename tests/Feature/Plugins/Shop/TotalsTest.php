<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Support\ShopSettings;
use Verso\Plugins\Shop\Support\Totals;

beforeEach(fn () => ShopFixtures::autoload());

it('adds flat shipping and tax on top of the subtotal', function () {
    $totals = Totals::calculate(subtotal: 5000, shippingRate: 495, taxRate: 2100);

    expect($totals->subtotal)->toBe(5000)
        ->and($totals->shipping)->toBe(495)
        ->and($totals->tax)->toBe(1154)
        ->and($totals->total())->toBe(6649)
        ->and($totals->taxPercent())->toBe(21.0);
});

it('can leave shipping out of the taxed amount', function () {
    $totals = Totals::calculate(subtotal: 5000, shippingRate: 495, taxRate: 2100, taxShipping: false);

    expect($totals->tax)->toBe(1050)
        ->and($totals->total())->toBe(6545);
});

it('ships for free from the threshold on', function () {
    expect(Totals::calculate(6000, 495, 0, true, 6000)->shipping)->toBe(0)
        ->and(Totals::calculate(5999, 495, 0, true, 6000)->shipping)->toBe(495)
        ->and(Totals::calculate(5999, 495, 0, true, null)->shipping)->toBe(495);
});

it('charges nothing for an empty cart', function () {
    $totals = Totals::calculate(0, 495, 2100);

    expect($totals->shipping)->toBe(0)
        ->and($totals->tax)->toBe(0)
        ->and($totals->total())->toBe(0);
});

it('rounds tax half up to whole cents', function (int $amount, int $rate, int $expected) {
    expect(Totals::percentOf($amount, $rate))->toBe($expected);
})->with([
    'exact' => [10000, 2100, 2100],
    'half cent rounds up' => [250, 1000, 25],
    'below half rounds down' => [1234, 2100, 259],
    'above half rounds up' => [1238, 2100, 260],
    'exactly half' => [50, 1000, 5],
    'tiny amount' => [1, 2100, 0],
    'three cents at 21 percent' => [3, 2100, 1],
    'reduced rate 7.25 percent' => [1999, 725, 145],
    'zero rate' => [9999, 0, 0],
    'negative amount' => [-500, 2100, 0],
]);

it('reads totals from the shop settings', function () {
    ShopFixtures::boot();
    app(ShopSettings::class)->save(['tax_rate' => 1000, 'shipping_rate' => 390, 'tax_shipping' => false, 'free_shipping_over' => 10000]);

    $settings = app(ShopSettings::class);

    expect($settings->totals(4000))->toEqual(new Totals(4000, 390, 400, 1000))
        ->and($settings->totals(12000))->toEqual(new Totals(12000, 0, 1200, 1000));
});

<?php

use App\Verso\Content\Enums\EntryStatus;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Support\ShopSettings;

// A new cart object for the same session, like on the next request.
function freshCart(): Cart
{
    app()->forgetScopedInstances();

    return app(Cart::class);
}

beforeEach(function () {
    ShopFixtures::boot();
    $this->cart = app(Cart::class);
});

it('starts empty', function () {
    expect($this->cart->isEmpty())->toBeTrue()
        ->and($this->cart->count())->toBe(0)
        ->and($this->cart->subtotal())->toBe(0);
});

it('adds products and joins the same product into one line', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $spoon = ShopFixtures::product('Olive Wood Spoon', 1450);

    $this->cart->add($mug, null, 1);
    $this->cart->add($mug, null, 2);
    $this->cart->add($spoon, null, 1);

    expect($this->cart->lines()->keys()->all())->toBe(['p'.$mug->id, 'p'.$spoon->id])
        ->and($this->cart->lines()->first()->quantity)->toBe(3)
        ->and($this->cart->count())->toBe(4)
        ->and($this->cart->subtotal())->toBe(3 * 2200 + 1450);
});

it('keeps one line per variant', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Small', null, 5], ['Size', 'Large', 2400, 5]]);
    [$small, $large] = $tote->variants->all();

    $this->cart->add($tote, $small, 1);
    $line = $this->cart->add($tote, $large, 2);

    expect($this->cart->lines())->toHaveCount(2)
        ->and($line->key)->toBe(Cart::lineKey($tote->id, $large->id))
        ->and($line->unitPrice())->toBe(2400)
        ->and($line->total())->toBe(4800)
        ->and($line->variantLabel())->toBe('Size: Large')
        ->and($this->cart->subtotal())->toBe(1900 + 4800);
});

it('refuses more items than the stock', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 2]);

    $this->cart->add($mug, null, 2);

    expect(fn () => $this->cart->add($mug, null, 1))->toThrow(OutOfStock::class, 'Only 2 of "Stoneware Mug" left in stock.')
        ->and($this->cart->count())->toBe(2);
});

it('caps a line at the shop limit', function () {
    config(['shop.max_quantity' => 5]);
    $card = ShopFixtures::product('Gift Card', 2500);

    $this->cart->add($card, null, 4);
    $this->cart->add($card, null, 4);

    expect($this->cart->count())->toBe(5);
});

it('changes and removes lines', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $spoon = ShopFixtures::product('Olive Wood Spoon', 1450);
    $this->cart->add($mug, null, 1);
    $this->cart->add($spoon, null, 1);

    $this->cart->update('p'.$mug->id, 4);
    $this->cart->update('p'.$spoon->id, 0);
    $this->cart->update('p999', 3);

    expect($this->cart->lines()->keys()->all())->toBe(['p'.$mug->id])
        ->and($this->cart->count())->toBe(4);

    $this->cart->remove('p'.$mug->id);

    expect($this->cart->isEmpty())->toBeTrue();
});

it('checks the stock when a quantity changes', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 3]);
    $this->cart->add($mug, null, 1);

    expect(fn () => $this->cart->update('p'.$mug->id, 4))->toThrow(OutOfStock::class);
    expect($this->cart->count())->toBe(1);
});

it('drops products that are no longer for sale', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $vase = ShopFixtures::product('Ceramic Vase', 4500);
    $this->cart->add($mug, null, 1);
    $this->cart->add($vase, null, 1);

    $vase->entry->forceFill(['status' => EntryStatus::Draft, 'published_at' => null])->save();
    $fresh = freshCart();

    expect($fresh->lines()->keys()->all())->toBe(['p'.$mug->id])
        ->and(session(config('shop.cart_session_key')))->toHaveCount(1);
});

it('drops lines whose variant was deleted', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Small', null, 5]]);
    $this->cart->add($tote, $tote->variants->first(), 1);

    $tote->variants->first()->delete();

    expect(freshCart()->isEmpty())->toBeTrue();
});

it('always uses the current product price', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->cart->add($mug, null, 2);

    $mug->update(['price' => 2500]);

    expect(freshCart()->subtotal())->toBe(5000);
});

it('ignores broken data in the session', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    session([config('shop.cart_session_key') => [
        'p'.$mug->id => ['product' => $mug->id, 'variant' => null, 'quantity' => 2],
        'broken' => 'not an array',
        'p77' => ['product' => 'abc'],
    ]]);

    expect(freshCart()->count())->toBe(2);
});

it('works out totals with the shop settings', function () {
    app(ShopSettings::class)->save(['tax_rate' => 2100, 'shipping_rate' => 495, 'tax_shipping' => true]);
    $mug = ShopFixtures::product('Stoneware Mug', 2199);
    $this->cart->add($mug, null, 3);

    $totals = $this->cart->totals();

    expect($totals->subtotal)->toBe(6597)
        ->and($totals->shipping)->toBe(495)
        ->and($totals->tax)->toBe(1489)
        ->and($totals->total())->toBe(8581);
});

it('empties the cart', function () {
    $this->cart->add(ShopFixtures::product('Stoneware Mug', 2200), null, 1);

    $this->cart->clear();

    expect($this->cart->isEmpty())->toBeTrue();
});

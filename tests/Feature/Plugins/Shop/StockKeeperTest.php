<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Support\StockKeeper;

beforeEach(function () {
    ShopFixtures::boot();
    $this->stock = app(StockKeeper::class);
});

it('takes items out of stock', function () {
    $product = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 5]);

    $this->stock->reduce($product, null, 3);

    expect($product->stock)->toBe(2)
        ->and($product->fresh()->stock)->toBe(2);
});

it('refuses to sell more than the stock', function () {
    $product = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 2]);

    expect(fn () => $this->stock->reduce($product, null, 3))
        ->toThrow(OutOfStock::class, 'Only 2 of "Stoneware Mug" left in stock.');

    expect($product->fresh()->stock)->toBe(2);
});

it('says when nothing is left', function () {
    $product = ShopFixtures::product('Tea Towel', 900, ['track_stock' => true, 'stock' => 0]);

    expect(fn () => $this->stock->reduce($product, null, 1))->toThrow(OutOfStock::class, '"Tea Towel" is out of stock.');
});

it('uses the variant stock for variants', function () {
    $product = ShopFixtures::product('Canvas Tote', 1900, ['track_stock' => true, 'stock' => 50], [['Size', 'Large', null, 1]]);
    $large = $product->variants->first();

    $this->stock->reduce($product, $large, 1);

    expect($large->fresh()->stock)->toBe(0)
        ->and($product->fresh()->stock)->toBe(50);

    expect(fn () => $this->stock->reduce($product, $large, 1))->toThrow(OutOfStock::class, '"Canvas Tote (Size: Large)" is out of stock.');
});

it('leaves untracked products alone', function () {
    $product = ShopFixtures::product('Gift Card', 5000, ['stock' => 0]);

    $this->stock->reduce($product, null, 10);
    $this->stock->restore($product, null, 10);

    expect($product->fresh()->stock)->toBe(0);
});

it('puts items back in stock', function () {
    $product = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 1], [['Glaze', 'Moss', null, 0]]);

    $this->stock->restore($product, null, 2);
    $this->stock->restore($product, $product->variants->first(), 4);

    expect($product->fresh()->stock)->toBe(3)
        ->and($product->variants->first()->fresh()->stock)->toBe(4);
});

<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Models\Product;

beforeEach(fn () => ShopFixtures::boot());

it('uses the variant price when it has one', function () {
    $product = ShopFixtures::product('Merino Beanie', 2800, variants: [
        ['Colour', 'Charcoal', null, 4],
        ['Colour', 'Saffron', 3200, 2],
    ]);
    [$charcoal, $saffron] = $product->variants->all();

    expect($product->priceFor())->toBe(2800)
        ->and($product->priceFor($charcoal))->toBe(2800)
        ->and($product->priceFor($saffron))->toBe(3200)
        ->and($product->priceRange())->toBe([2800, 3200]);
});

it('gives a single price range without variants', function () {
    expect(ShopFixtures::product('Olive Wood Spoon', 1450)->priceRange())->toBe([1450, 1450]);
});

it('counts stock only when tracking is on', function () {
    $untracked = ShopFixtures::product('Gift Card', 5000, ['stock' => 0]);
    $tracked = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 3]);
    $soldOut = ShopFixtures::product('Tea Towel', 900, ['track_stock' => true, 'stock' => 0]);

    expect($untracked->availableStock())->toBeNull()
        ->and($untracked->inStock())->toBeTrue()
        ->and($tracked->availableStock())->toBe(3)
        ->and($tracked->inStock())->toBeTrue()
        ->and($soldOut->inStock())->toBeFalse();
});

it('adds up variant stock for the product', function () {
    $product = ShopFixtures::product('Canvas Tote', 1900, ['track_stock' => true, 'stock' => 99], [
        ['Size', 'Small', null, 2],
        ['Size', 'Large', null, 0],
    ]);
    [$small, $large] = $product->variants->all();

    expect($product->availableStock())->toBe(2)
        ->and($product->availableStock($small))->toBe(2)
        ->and($product->inStock($large))->toBeFalse()
        ->and($product->findVariant($large->id)?->label())->toBe('Size: Large')
        ->and($product->findVariant(999))->toBeNull()
        ->and($product->findVariant(null))->toBeNull();
});

it('uses the variant SKU before the product SKU', function () {
    $product = ShopFixtures::product('Linen Shirt', 6900, ['sku' => 'LIN-SHIRT'], [['Size', 'M', null, 1]]);

    expect($product->skuFor())->toBe('LIN-SHIRT')
        ->and($product->skuFor($product->variants->first()))->toBe('LINEN-SHIRT-M');
});

it('only sells products whose entry is published', function () {
    ShopFixtures::product('Ceramic Vase', 4500);
    ShopFixtures::product('Draft Planter', 3000, published: false);

    expect(Product::query()->sellable()->get()->map(fn (Product $product) => $product->entry->title)->all())->toBe(['Ceramic Vase']);
});

it('keeps the gallery order and skips deleted media', function () {
    $first = Media::factory()->create();
    $second = Media::factory()->create();
    $product = ShopFixtures::product('Hand Thrown Bowl', 3800, ['gallery' => [$second->id, 999, $first->id]]);

    expect($product->galleryIds())->toBe([$second->id, 999, $first->id])
        ->and(array_map(fn (Media $media) => $media->id, $product->galleryMedia()))->toBe([$second->id, $first->id]);
});

it('makes an empty record for a product entry without shop data', function () {
    $entry = Entry::factory()->create(['type' => 'product', 'title' => 'Walnut Board']);
    $product = Product::forEntry($entry);

    expect($product->exists)->toBeFalse()
        ->and($product->entry_id)->toBe($entry->id)
        ->and($product->price)->toBe(0);
});

it('deletes the shop data with the entry', function () {
    $product = ShopFixtures::product('Brass Candle Holder', 2600, variants: [['Finish', 'Polished', null, 3]]);

    $product->entry->forceDelete();

    expect(Product::query()->count())->toBe(0);
});

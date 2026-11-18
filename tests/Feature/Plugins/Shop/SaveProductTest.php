<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Cache\PageCache;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Actions\SaveProduct;

beforeEach(fn () => ShopFixtures::boot());

function productData(array $overrides = []): array
{
    return ['price' => 2490, 'sku' => null, 'track_stock' => false, 'stock' => 0, 'gallery' => [], 'variants' => [], ...$overrides];
}

it('creates shop data for a product entry', function () {
    $entry = Entry::factory()->create(['type' => 'product', 'title' => 'Linen Apron']);

    $product = app(SaveProduct::class)->handle($entry, productData(['sku' => 'APRON-LINEN']));

    expect($product->exists)->toBeTrue()
        ->and($product->entry_id)->toBe($entry->id)
        ->and($product->price)->toBe(2490)
        ->and($product->variants)->toHaveCount(0);
});

it('never saves negative numbers', function () {
    $entry = Entry::factory()->create(['type' => 'product']);

    $product = app(SaveProduct::class)->handle($entry, productData([
        'price' => -100,
        'stock' => -4,
        'variants' => [['id' => null, 'option_name' => 'Size', 'option_value' => 'Large', 'sku' => null, 'price' => -1, 'stock' => -2]],
    ]));

    expect($product->price)->toBe(0)
        ->and($product->stock)->toBe(0)
        ->and($product->variants->first()->price)->toBe(0)
        ->and($product->variants->first()->stock)->toBe(0);
});

it('refuses entries of other types', function () {
    $post = Entry::factory()->create(['type' => 'post']);

    app(SaveProduct::class)->handle($post, productData());
})->throws(InvalidArgumentException::class, 'Only product entries can have shop data.');

it('empties the page cache so product pages show the new price', function () {
    $cache = Mockery::mock(PageCache::class);
    $cache->shouldReceive('flush')->once();
    app()->instance(PageCache::class, $cache);

    app(SaveProduct::class)->handle(Entry::factory()->create(['type' => 'product']), productData());
});

it('empties the page cache when an order takes items out of stock', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 3]);
    $cache = Mockery::mock(PageCache::class);
    $cache->shouldReceive('flush')->atLeast()->once();
    app()->instance(PageCache::class, $cache);

    ShopFixtures::order([[$mug, 1]]);
});

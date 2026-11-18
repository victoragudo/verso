<?php

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Tests\Support\ShopFixtures;

beforeEach(fn () => ShopFixtures::boot());

it('registers the product type and both blocks', function () {
    expect(app(ContentTypeRegistry::class)->find('product')?->routeBase)->toBe('shop')
        ->and(app(PluginBlockRegistry::class)->has('shop-product'))->toBeTrue()
        ->and(app(PluginBlockRegistry::class)->has('shop-product-grid'))->toBeTrue();
});

it('shows a product page', function () {
    $product = ShopFixtures::product('Linen Apron', 3450);

    $this->get('/shop/linen-apron')->assertOk()->assertSee('Linen Apron')->assertSee('€34.50');
});

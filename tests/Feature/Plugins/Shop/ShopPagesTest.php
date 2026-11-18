<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(fn () => ShopFixtures::boot());

it('lists products for sale on the shop page', function () {
    ShopFixtures::product('Stoneware Mug', 2200);
    ShopFixtures::product('Merino Beanie', 2800, variants: [['Colour', 'Charcoal', null, 1], ['Colour', 'Saffron', 3200, 1]]);
    ShopFixtures::product('Draft Planter', 3000, published: false);

    $this->get('/shop')
        ->assertOk()
        ->assertSee('<h1 class="page-title">Shop</h1>', false)
        ->assertSee('Stoneware Mug')
        ->assertSee('€22.00')
        ->assertSee('From €28.00')
        ->assertDontSee('Draft Planter');
});

it('says when nothing is for sale', function () {
    $this->get('/shop')->assertOk()->assertSee('There are no products for sale yet.');
});

it('sorts the shop page', function () {
    ShopFixtures::product('Ceramic Vase', 4500);
    ShopFixtures::product('Olive Wood Spoon', 1450);

    $this->get('/shop?sort=price_asc')->assertSeeInOrder(['Olive Wood Spoon', 'Ceramic Vase']);
    $this->get('/shop?sort=price_desc')->assertSeeInOrder(['Ceramic Vase', 'Olive Wood Spoon']);
    $this->get('/shop?sort=nonsense')->assertOk();
});

it('splits the shop page into pages', function () {
    config(['shop.per_page' => 2]);
    foreach (['Ceramic Vase', 'Olive Wood Spoon', 'Stoneware Mug'] as $title) {
        ShopFixtures::product($title, 1500);
    }

    $this->get('/shop?sort=title')->assertSee('Page 1 of 2')->assertSee('More products')->assertDontSee('Stoneware Mug');
    $this->get('/shop?sort=title&page=2')->assertSee('Stoneware Mug')->assertSee('sort=title', false);
});

it('marks sold out products', function () {
    ShopFixtures::product('Tea Towel', 900, ['track_stock' => true, 'stock' => 0]);

    $this->get('/shop')->assertSee('Out of stock');
});

it('shows the product page with price, SKU, stock and the buy form', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['sku' => 'MUG-STONE', 'track_stock' => true, 'stock' => 4]);

    $this->get('/shop/stoneware-mug')
        ->assertOk()
        ->assertSee('<h1 class="page-title">Stoneware Mug</h1>', false)
        ->assertSee('€22.00')
        ->assertSee('SKU MUG-STONE')
        ->assertSee('4 in stock')
        ->assertSee('action="'.route('theme.shop.cart.add').'"', false)
        ->assertSee('max="4"', false)
        ->assertSee('<a href="'.route('theme.shop.cart').'"', false);
});

it('shows the gallery on the product page', function () {
    $photo = Media::factory()->create(['alt' => 'Mug on a linen cloth']);
    ShopFixtures::product('Stoneware Mug', 2200, ['gallery' => [$photo->id]]);

    $this->get('/shop/stoneware-mug')->assertSee('Mug on a linen cloth')->assertSee('More photos');
});

it('shows a sold out product without the form', function () {
    ShopFixtures::product('Tea Towel', 900, ['track_stock' => true, 'stock' => 0]);

    $this->get('/shop/tea-towel')->assertSee('Out of stock')->assertDontSee('Add to cart');
});

it('shows product entries without shop data as not for sale', function () {
    Entry::factory()->published()->create(['type' => 'product', 'title' => 'Walnut Board', 'slug' => 'walnut-board']);

    $this->get('/shop/walnut-board')->assertOk()->assertSee('This product is not for sale yet.');
});

it('hides unpublished products', function () {
    ShopFixtures::product('Draft Planter', 3000, published: false);

    $this->get('/shop/draft-planter')->assertNotFound();
});

it('adds the shop styles to every public page', function () {
    $this->get('/shop')->assertSee('.shop-grid', false);
    $this->get('/search')->assertSee('.shop-card__title', false);
});

it('shows the thank you page with bank details', function () {
    app(ShopSettings::class)->save(['bank_transfer_instructions' => "IBAN ES91 2100 0418 4502 0005 1332\nHarbour Pottery SL"]);
    $order = ShopFixtures::order([[ShopFixtures::product('Stoneware Mug', 2200), 2]]);

    $this->get($order->url())
        ->assertOk()
        ->assertSee('Order '.$order->number())
        ->assertSee('Thank you for your order')
        ->assertSee('IBAN ES91 2100 0418 4502 0005 1332<br />', false)
        ->assertSee('Carrer de la Pau 14')
        ->assertSee('2 × Stoneware Mug')
        ->assertSee('€44.00')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('confirms paid orders and hides the bank details', function () {
    $order = ShopFixtures::order([[ShopFixtures::product('Stoneware Mug', 2200), 1]]);
    $order->forceFill(['status' => OrderStatus::Paid])->save();

    $this->get($order->url())->assertSee('Thank you, your order is confirmed')->assertDontSee('How to pay');
});

it('shows orders in their own currency after the shop currency changed', function () {
    $order = ShopFixtures::order([[ShopFixtures::product('Stoneware Mug', 2200), 1]]);
    app(ShopSettings::class)->save(['currency' => 'GBP']);

    $this->get($order->url())->assertSee('€22.00')->assertDontSee('£22.00');
});

it('hides orders behind their secret token', function () {
    ShopFixtures::order([[ShopFixtures::product('Stoneware Mug', 2200), 1]]);

    $this->get('/checkout/orders/9b2f6a4e-6f2d-4c47-9a43-0c5d2f1e8b11')->assertNotFound();
    $this->get('/checkout/orders/1')->assertNotFound();
});

it('lets a theme override the shop views', function () {
    $base = storage_path('framework/testing/shop-themes-'.uniqid());
    File::copyDirectory(base_path('themes/default'), $base.'/default');
    File::ensureDirectoryExists($base.'/default/views/shop');
    File::put($base.'/default/views/shop/cart.blade.php', 'Harbour cart page');

    config(['verso.themes.path' => str_replace(base_path().'/', '', $base)]);
    app()->forgetInstance(ThemeManager::class);
    app(ThemeManager::class)->addPluginViews(base_path('plugins/shop/resources/views/theme'));

    try {
        $this->get('/cart')->assertOk()->assertSee('Harbour cart page');
        $this->get('/checkout')->assertRedirect('/cart');
        $this->get('/shop')->assertOk()->assertSee('There are no products for sale yet.');
    } finally {
        File::deleteDirectory($base);
    }
});

it('reserves the shop paths for pages', function () {
    expect(config('verso.content.reserved_slugs'))->toContain('shop', 'cart', 'checkout');
});

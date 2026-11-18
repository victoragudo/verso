<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(fn () => ShopFixtures::boot());

it('shows an empty cart', function () {
    $this->get('/cart')
        ->assertOk()
        ->assertSee('Your cart is empty.')
        ->assertSee('<meta name="robots" content="noindex">', false);
});

it('adds a product from the product page form', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2])
        ->assertRedirect('/cart')
        ->assertSessionHas('shop.notice', '"Stoneware Mug" was added to your cart.');

    $this->get('/cart')
        ->assertOk()
        ->assertSee('Stoneware Mug')
        ->assertSee('€44.00')
        ->assertSee('Go to checkout');
});

it('adds one item when no quantity is sent', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    $this->post('/cart', ['product_id' => $mug->id]);

    expect(app(Cart::class)->count())->toBe(1);
});

it('asks for an option when the product has variants', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Small', null, 5]]);

    $this->from('/shop/canvas-tote')
        ->post('/cart', ['product_id' => $tote->id])
        ->assertRedirect('/shop/canvas-tote')
        ->assertSessionHasErrors(['variant_id' => 'Please choose an option.']);
});

it('refuses a variant of another product', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Small', null, 5]]);
    $beanie = ShopFixtures::product('Merino Beanie', 2800, variants: [['Colour', 'Saffron', null, 5]]);

    $this->post('/cart', ['product_id' => $tote->id, 'variant_id' => $beanie->variants->first()->id])
        ->assertSessionHasErrors('variant_id');

    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->post('/cart', ['product_id' => $mug->id, 'variant_id' => $beanie->variants->first()->id])
        ->assertSessionHasErrors(['variant_id' => 'This product has no options.']);
});

it('refuses products that are not for sale', function () {
    $draft = ShopFixtures::product('Draft Planter', 3000, published: false);

    $this->post('/cart', ['product_id' => $draft->id])->assertSessionHasErrors(['product_id' => 'This product is not for sale.']);
    $this->post('/cart', ['product_id' => 99999])->assertSessionHasErrors('product_id');
});

it('validates the quantity', function (mixed $quantity) {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => $quantity])->assertSessionHasErrors('quantity');
})->with([0, -2, 'many', 1000]);

it('explains when the stock is too low', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 1]);

    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 3])
        ->assertSessionHasErrors(['quantity' => 'Only 1 of "Stoneware Mug" left in stock.']);
});

it('updates quantities from the cart page', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $spoon = ShopFixtures::product('Olive Wood Spoon', 1450);
    $this->post('/cart', ['product_id' => $mug->id]);
    $this->post('/cart', ['product_id' => $spoon->id]);

    $this->patch('/cart', ['quantities' => ['p'.$mug->id => 3, 'p'.$spoon->id => 0]])
        ->assertRedirect('/cart')
        ->assertSessionHas('shop.notice', 'Your cart was updated.');

    app()->forgetScopedInstances();
    expect(app(Cart::class)->lines()->keys()->all())->toBe(['p'.$mug->id])
        ->and(app(Cart::class)->count())->toBe(3);
});

it('shows a stock error when an update asks for too much', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 2]);
    $this->post('/cart', ['product_id' => $mug->id]);

    $this->patch('/cart', ['quantities' => ['p'.$mug->id => 5]])
        ->assertRedirect('/cart')
        ->assertSessionHasErrors('quantities');
});

it('removes a line', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->post('/cart', ['product_id' => $mug->id]);

    $this->delete('/cart/p'.$mug->id)->assertRedirect('/cart');

    app()->forgetScopedInstances();
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('only accepts real line keys in the remove URL', function () {
    $this->delete('/cart/everything')->assertStatus(405);
});

it('shows shipping, tax and the total', function () {
    app(ShopSettings::class)->save(['tax_rate' => 2100, 'shipping_rate' => 495]);
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);

    $this->get('/cart')
        ->assertSeeInOrder(['Subtotal', '€44.00', 'Shipping', '€4.95', 'Tax (21%)', '€10.28', 'Total', '€59.23']);
});

it('shows free shipping from the threshold on', function () {
    app(ShopSettings::class)->save(['shipping_rate' => 495, 'free_shipping_over' => 4000]);
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);

    $this->get('/cart')->assertSeeInOrder(['Shipping', 'Free']);
});

it('never caches the cart page', function () {
    $this->get('/cart')->assertHeaderMissing('X-Verso-Cache');
});

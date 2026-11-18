<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Events\OrderPlaced;
use Verso\Plugins\Shop\Mail\OrderReceivedMail;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    app(ShopSettings::class)->save(['tax_rate' => 2100, 'shipping_rate' => 495, 'tax_shipping' => true]);
});

it('sends people with an empty cart back to the cart', function () {
    $this->get('/checkout')->assertRedirect('/cart');
    $this->post('/checkout', ShopFixtures::customer())->assertRedirect('/cart');

    expect(Order::query()->count())->toBe(0);
});

it('shows the checkout form with the order summary', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);

    $this->get('/checkout')
        ->assertOk()
        ->assertSee('Contact and shipping')
        ->assertSee('Bank transfer')
        ->assertDontSee('Card (Stripe)')
        ->assertSee('2 × Stoneware Mug')
        ->assertSee('Place order · €59.23');
});

it('places an order paid by bank transfer', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2199, ['sku' => 'MUG-STONE', 'track_stock' => true, 'stock' => 10]);
    $tote = ShopFixtures::product('Canvas Tote', 1900, ['track_stock' => true], [['Size', 'Large', 2400, 4]]);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 3]);
    $this->post('/cart', ['product_id' => $tote->id, 'variant_id' => $tote->variants->first()->id]);

    $response = $this->post('/checkout', ShopFixtures::customer());

    $order = Order::query()->sole();
    $response->assertRedirect($order->url());

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->currency)->toBe('EUR')
        ->and($order->customer_name)->toBe('Lucía Fernández')
        ->and($order->customer_email)->toBe('lucia.fernandez@example.es')
        ->and($order->city)->toBe('Valencia')
        ->and($order->country)->toBe('ES')
        ->and($order->payment_gateway)->toBe('bank_transfer')
        ->and($order->subtotal)->toBe(6597 + 2400)
        ->and($order->shipping_total)->toBe(495)
        ->and($order->tax_total)->toBe(1993)
        ->and($order->tax_rate)->toBe(2100)
        ->and($order->total)->toBe(8997 + 495 + 1993)
        ->and($order->stock_reduced)->toBeTrue()
        ->and($order->number())->toBe('ORD-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT));

    expect($order->items->map->only(['name', 'variant_label', 'sku', 'unit_price', 'quantity', 'line_total'])->all())->toBe([
        ['name' => 'Stoneware Mug', 'variant_label' => null, 'sku' => 'MUG-STONE', 'unit_price' => 2199, 'quantity' => 3, 'line_total' => 6597],
        ['name' => 'Canvas Tote', 'variant_label' => 'Size: Large', 'sku' => 'CANVAS-TOTE-LARGE', 'unit_price' => 2400, 'quantity' => 1, 'line_total' => 2400],
    ]);

    expect($mug->fresh()->stock)->toBe(7)
        ->and($tote->variants->first()->fresh()->stock)->toBe(3)
        ->and($order->statusChanges)->toHaveCount(1)
        ->and($order->statusChanges->first()->to_status)->toBe(OrderStatus::Pending);

    app()->forgetScopedInstances();
    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('emails the customer and the shop owner', function () {
    app(ShopSettings::class)->save(['notify_email' => 'orders@harbourpottery.example']);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer());

    Mail::assertSent(OrderReceivedMail::class, fn (OrderReceivedMail $mail) => $mail->hasTo('lucia.fernandez@example.es') && ! $mail->forShop);
    Mail::assertSent(OrderReceivedMail::class, fn (OrderReceivedMail $mail) => $mail->hasTo('orders@harbourpottery.example') && $mail->forShop);
});

it('does not email the shop when no address is set', function () {
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer());

    Mail::assertSentCount(1);
});

it('dispatches an event for the new order', function () {
    Event::fake([OrderPlaced::class]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer());

    Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->items->count() === 1);
});

it('checks the customer details', function (string $field, mixed $value) {
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->from('/checkout')
        ->post('/checkout', ShopFixtures::customer([$field => $value]))
        ->assertRedirect('/checkout')
        ->assertSessionHasErrors($field);

    expect(Order::query()->count())->toBe(0);
})->with([
    'missing name' => ['customer_name', ''],
    'bad email' => ['customer_email', 'lucia at example'],
    'missing address' => ['address_line1', ''],
    'missing city' => ['city', ''],
    'missing postal code' => ['postal_code', ''],
    'long country' => ['country', 'Spain'],
    'digits in country' => ['country', 'E5'],
    'unknown gateway' => ['payment_gateway', 'cash_on_delivery'],
    'turned off gateway' => ['payment_gateway', 'stripe'],
]);

it('cleans up the customer details', function () {
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer([
        'customer_email' => '  Lucia.Fernandez@Example.ES ',
        'country' => 'es',
        'customer_phone' => '',
        'address_line2' => '',
    ]));

    $order = Order::query()->sole();

    expect($order->customer_email)->toBe('lucia.fernandez@example.es')
        ->and($order->country)->toBe('ES')
        ->and($order->customer_phone)->toBeNull()
        ->and($order->address_line2)->toBeNull();
});

it('refuses a gateway that is turned off', function () {
    app(ShopSettings::class)->save(['bank_transfer_enabled' => false]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->get('/checkout')->assertSee('The shop cannot take payments right now.');
    $this->post('/checkout', ShopFixtures::customer())->assertSessionHasErrors('payment_gateway');
});

it('stops when another customer bought the last item first', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 2]);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);
    $mug->update(['stock' => 1]);

    $this->post('/checkout', ShopFixtures::customer())
        ->assertRedirect('/cart')
        ->assertSessionHasErrors(['quantities' => 'Only 1 of "Stoneware Mug" left in stock.']);

    expect(Order::query()->count())->toBe(0)
        ->and($mug->fresh()->stock)->toBe(1);
    Mail::assertNothingSent();
});

it('rolls back the stock of earlier lines when a later line fails', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 5]);
    $vase = ShopFixtures::product('Ceramic Vase', 4500, ['track_stock' => true, 'stock' => 1]);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);
    $this->post('/cart', ['product_id' => $vase->id]);
    $vase->update(['stock' => 0]);

    $this->post('/checkout', ShopFixtures::customer())->assertSessionHasErrors('quantities');

    expect($mug->fresh()->stock)->toBe(5);
});

it('limits how often the checkout can be sent', function () {
    config(['shop.checkout_throttle' => 2]);

    $this->post('/checkout', ShopFixtures::customer());
    $this->post('/checkout', ShopFixtures::customer());
    $this->post('/checkout', ShopFixtures::customer())->assertTooManyRequests();
});

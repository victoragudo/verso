<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\Gateways\StripeCheckoutGateway;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Payments\Stripe\StripeClient;
use Verso\Plugins\Shop\Support\ShopSettings;

const STRIPE_SESSIONS = 'https://api.stripe.com/v1/checkout/sessions';

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    app(ShopSettings::class)->save([
        'tax_rate' => 2100,
        'shipping_rate' => 495,
        'stripe_enabled' => true,
        'stripe_secret_key' => 'sk_test_51HarbourPottery',
        'stripe_webhook_secret' => 'whsec_9fK2harbour',
    ]);
});

function fakeStripeSession(string $id = 'cs_test_a1B2c3D4'): void
{
    Http::fake([STRIPE_SESSIONS => Http::response([
        'id' => $id,
        'object' => 'checkout.session',
        'url' => 'https://checkout.stripe.com/c/pay/'.$id,
    ])]);
}

it('is only available with both keys and the switch on', function () {
    $gateway = app(StripeCheckoutGateway::class);

    expect($gateway->isAvailable())->toBeTrue();

    app(ShopSettings::class)->save(['stripe_webhook_secret' => '']);
    expect($gateway->isAvailable())->toBeFalse();

    app(ShopSettings::class)->save(['stripe_webhook_secret' => 'whsec_9fK2harbour', 'stripe_enabled' => false]);
    expect($gateway->isAvailable())->toBeFalse();
});

it('is offered at the checkout', function () {
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->get('/checkout')->assertSee('Card (Stripe)')->assertSee('Pay by card on a secure Stripe page.');
});

it('sends the customer to Stripe Checkout', function () {
    fakeStripeSession();
    $mug = ShopFixtures::product('Stoneware Mug', 2199);
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Large', 2400, 4]]);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 3]);
    $this->post('/cart', ['product_id' => $tote->id, 'variant_id' => $tote->variants->first()->id]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_a1B2c3D4');

    $order = Order::query()->sole();

    expect($order->payment_gateway)->toBe('stripe')
        ->and($order->payment_reference)->toBe('cs_test_a1B2c3D4')
        ->and($order->status)->toBe(OrderStatus::Pending);

    Http::assertSent(function (Request $request) use ($order): bool {
        $data = $request->data();

        return $request->url() === STRIPE_SESSIONS
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer sk_test_51HarbourPottery')
            && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded')
            && $data['mode'] === 'payment'
            && $data['client_reference_id'] === (string) $order->id
            && $data['customer_email'] === 'lucia.fernandez@example.es'
            && $data['success_url'] === $order->url()
            && $data['cancel_url'] === $order->url()
            && $data['metadata']['order_number'] === $order->number()
            && $data['line_items'] === [
                ['quantity' => 3, 'price_data' => ['currency' => 'eur', 'unit_amount' => 2199, 'product_data' => ['name' => 'Stoneware Mug']]],
                ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => 2400, 'product_data' => ['name' => 'Canvas Tote (Size: Large)']]],
                ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => 495, 'product_data' => ['name' => 'Shipping']]],
                ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => 1993, 'product_data' => ['name' => 'Tax']]],
            ];
    });
});

it('charges exactly the order total at Stripe', function () {
    fakeStripeSession();
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 1333)->id, 'quantity' => 7]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));

    $order = Order::query()->sole();
    $charged = 0;

    Http::assertSent(function (Request $request) use (&$charged): bool {
        foreach ($request->data()['line_items'] as $item) {
            $charged += $item['quantity'] * $item['price_data']['unit_amount'];
        }

        return true;
    });

    expect($charged)->toBe($order->total);
});

it('leaves out shipping and tax lines when they are zero', function () {
    fakeStripeSession();
    app(ShopSettings::class)->save(['tax_rate' => 0, 'shipping_rate' => 0]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));

    Http::assertSent(fn (Request $request): bool => count($request->data()['line_items']) === 1);
});

it('cancels the order and gives the stock back when Stripe refuses', function () {
    Http::fake([STRIPE_SESSIONS => Http::response(['error' => ['message' => 'Invalid API Key provided']], 401)]);
    $mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 4]);
    $this->post('/cart', ['product_id' => $mug->id, 'quantity' => 2]);

    $response = $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));

    $order = Order::query()->sole();
    $response->assertRedirect($order->url())
        ->assertSessionHas('shop.error', 'We could not start the payment. Your order was cancelled, please try again.');

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($mug->fresh()->stock)->toBe(4)
        ->and($order->statusChanges->last()->note)->toBe('Stripe refused the payment request: Invalid API Key provided');
});

it('handles Stripe being down', function () {
    Http::fake([STRIPE_SESSIONS => fn () => throw new ConnectionException('Connection timed out')]);
    config(['shop.stripe.retries' => 0]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));

    expect(Order::query()->sole()->status)->toBe(OrderStatus::Cancelled);
});

it('fails when Stripe gives no checkout page', function () {
    Http::fake([STRIPE_SESSIONS => Http::response(['id' => 'cs_test_a1B2c3D4'])]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));

    expect(Order::query()->sole()->status)->toBe(OrderStatus::Cancelled);
});

it('uses the Stripe API URL from the config', function () {
    config(['shop.stripe.api_url' => 'https://stripe-mock.internal:12111/']);
    app()->forgetInstance(StripeClient::class);
    app()->forgetInstance(StripeCheckoutGateway::class);
    app()->forgetInstance(PaymentGateways::class);
    Http::fake(['https://stripe-mock.internal:12111/v1/checkout/sessions' => Http::response(['id' => 'cs_mock_1', 'url' => 'https://checkout.example/cs_mock_1'])]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);

    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']))->assertRedirect('https://checkout.example/cs_mock_1');
});

it('lets the customer pay again for a pending order', function () {
    Http::fake([STRIPE_SESSIONS => Http::sequence()
        ->push(['id' => 'cs_test_first', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_first'])
        ->push(['id' => 'cs_test_second', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_second'])]);
    $this->post('/cart', ['product_id' => ShopFixtures::product('Stoneware Mug', 2200)->id]);
    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));
    $order = Order::query()->sole();

    $this->get($order->url())->assertSee('Your payment is not finished yet.')->assertSee('Pay now');

    $this->post(route('theme.shop.order.pay', ['token' => $order->token]))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_second');

    expect($order->fresh()->payment_reference)->toBe('cs_test_second');
});

it('does not offer a new payment for bank transfers or paid orders', function () {
    $order = ShopFixtures::order([[ShopFixtures::product('Stoneware Mug', 2200), 1]]);

    $this->get($order->url())->assertDontSee('Pay now');
    $this->post(route('theme.shop.order.pay', ['token' => $order->token]))->assertRedirect($order->url());

    Http::assertNothingSent();
});

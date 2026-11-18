<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Mail\OrderStatusMail;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\Stripe\StripeSignature;
use Verso\Plugins\Shop\Support\ShopSettings;

const WEBHOOK_SECRET = 'whsec_9fK2harbour';

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    app(ShopSettings::class)->save([
        'stripe_enabled' => true,
        'stripe_secret_key' => 'sk_test_51HarbourPottery',
        'stripe_webhook_secret' => WEBHOOK_SECRET,
    ]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_harbour42', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_harbour42'])]);

    $this->mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 5]);
    $this->post('/cart', ['product_id' => $this->mug->id, 'quantity' => 2]);
    $this->post('/checkout', ShopFixtures::customer(['payment_gateway' => 'stripe']));
    $this->order = Order::query()->sole();
});

// Send a signed Stripe event about a checkout session to the webhook.
function sendStripeEvent(string $type, array $session, ?string $secret = WEBHOOK_SECRET, ?int $time = null): TestResponse
{
    $payload = json_encode(['id' => 'evt_1QxHarbour', 'object' => 'event', 'type' => $type, 'data' => ['object' => $session]]);

    return test()->call('POST', '/shop/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => StripeSignature::header($payload, (string) $secret, $time),
    ], $payload);
}

function stripeSession(Order $order, array $overrides = []): array
{
    return [
        'id' => 'cs_test_harbour42',
        'object' => 'checkout.session',
        'client_reference_id' => (string) $order->id,
        'payment_status' => 'paid',
        'amount_total' => $order->total,
        'currency' => 'eur',
        ...$overrides,
    ];
}

it('marks the order as paid when the checkout is completed', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order))
        ->assertOk()
        ->assertJson(['result' => 'paid']);

    $order = $this->order->fresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->statusChanges->last()->note)->toBe('Paid with Stripe Checkout.');

    Mail::assertSent(OrderStatusMail::class);
});

it('handles the same event twice only once', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order));
    sendStripeEvent('checkout.session.completed', stripeSession($this->order))->assertJson(['result' => 'already_handled']);

    expect($this->order->fresh()->statusChanges)->toHaveCount(2);
});

it('waits for delayed payment methods', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order, ['payment_status' => 'unpaid']))
        ->assertJson(['result' => 'waiting']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);

    sendStripeEvent('checkout.session.async_payment_succeeded', stripeSession($this->order))->assertJson(['result' => 'paid']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('cancels the order and frees the stock when the session expires', function () {
    expect($this->mug->fresh()->stock)->toBe(3);

    sendStripeEvent('checkout.session.expired', stripeSession($this->order, ['payment_status' => 'unpaid']))
        ->assertJson(['result' => 'cancelled']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->mug->fresh()->stock)->toBe(5);
});

it('cancels the order when a delayed payment fails', function () {
    sendStripeEvent('checkout.session.async_payment_failed', stripeSession($this->order))->assertJson(['result' => 'cancelled']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('does not cancel an order that is already paid', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order));

    sendStripeEvent('checkout.session.expired', stripeSession($this->order))->assertJson(['result' => 'already_handled']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('refuses a paid amount that does not match the order', function (array $overrides) {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order, $overrides))
        ->assertOk()
        ->assertJson(['result' => 'amount_mismatch']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);
})->with([
    'lower amount' => [['amount_total' => 100]],
    'other currency' => [['currency' => 'usd']],
    'missing amount' => [['amount_total' => null]],
]);

it('ignores sessions of other orders', function (array $overrides) {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order, $overrides))->assertJson(['result' => 'unknown_order']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);
})->with([
    'other session id' => [['id' => 'cs_test_someone_else']],
    'other order id' => [['client_reference_id' => '999']],
    'no order id' => [['client_reference_id' => null]],
]);

it('ignores events it does not use', function () {
    sendStripeEvent('customer.created', ['id' => 'cus_HarbourPottery'])->assertOk()->assertJson(['result' => 'ignored']);
});

it('refuses events without a valid signature', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order), 'whsec_attacker')
        ->assertStatus(400)
        ->assertJson(['error' => 'Invalid signature.']);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('refuses replayed old events', function () {
    sendStripeEvent('checkout.session.completed', stripeSession($this->order), time: time() - 3600)->assertStatus(400);
});

it('refuses events when no webhook secret is set', function () {
    app(ShopSettings::class)->save(['stripe_webhook_secret' => '']);

    sendStripeEvent('checkout.session.completed', stripeSession($this->order))->assertStatus(400);
});

it('refuses a body that is not JSON', function () {
    $payload = 'not json at all';

    $this->call('POST', '/shop/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => StripeSignature::header($payload, WEBHOOK_SECRET),
    ], $payload)->assertStatus(400)->assertJson(['error' => 'Invalid payload.']);
});

it('needs no CSRF token or session', function () {
    $route = app('router')->getRoutes()->getByName('shop.stripe.webhook');

    expect($route->gatherMiddleware())->not->toContain('web');
});

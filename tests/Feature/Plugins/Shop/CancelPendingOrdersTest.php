<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Actions\CancelStalePendingOrders;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Console\CancelPendingOrdersCommand;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Jobs\CancelPendingOrders;
use Verso\Plugins\Shop\Models\Order;

beforeEach(function () {
    ShopFixtures::boot();
    config([
        'shop.pending_orders.cancel_after_minutes' => 1440,
        'shop.pending_orders.gateway_minutes' => ['bank_transfer' => 10080],
    ]);
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
    $this->kettle = ShopFixtures::product('Enamel Kettle', 4500, ['track_stock' => true, 'stock' => 6]);
});

// An order placed now with the given gateway saved on it.
function pendingOrder(object $test, int $quantity, string $gateway): Order
{
    $order = ShopFixtures::order([[$test->kettle, $quantity]]);
    Order::query()->whereKey($order->id)->update(['payment_gateway' => $gateway]);

    return $order->refresh();
}

it('cancels stripe orders older than the default wait and puts the stock back', function () {
    $order = pendingOrder($this, 2, 'stripe');

    expect($this->kettle->fresh()->stock)->toBe(4);

    $this->travel(1441)->minutes();

    expect(app(CancelStalePendingOrders::class)->handle())->toBe(1);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->stock_reduced)->toBeFalse()
        ->and($this->kettle->fresh()->stock)->toBe(6)
        ->and($order->statusChanges->last()?->note)->toBe(CancelStalePendingOrders::NOTE);
});

it('keeps orders that are still inside the wait', function () {
    $order = pendingOrder($this, 1, 'stripe');

    $this->travel(1439)->minutes();

    expect(app(CancelStalePendingOrders::class)->handle())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('gives bank transfers their own longer wait', function () {
    $transfer = pendingOrder($this, 1, 'bank_transfer');
    $card = pendingOrder($this, 1, 'stripe');

    $this->travel(2)->days();
    app(CancelStalePendingOrders::class)->handle();

    expect($transfer->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($card->fresh()->status)->toBe(OrderStatus::Cancelled);

    $this->travel(6)->days();
    app(CancelStalePendingOrders::class)->handle();

    expect($transfer->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->kettle->fresh()->stock)->toBe(6);
});

it('never touches paid orders', function () {
    $order = pendingOrder($this, 1, 'stripe');
    app(ChangeOrderStatus::class)->handle($order, OrderStatus::Paid);

    $this->travel(3)->days();

    expect(app(CancelStalePendingOrders::class)->handle())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->kettle->fresh()->stock)->toBe(5);
});

it('can be turned off with a zero wait', function () {
    config(['shop.pending_orders.cancel_after_minutes' => 0, 'shop.pending_orders.gateway_minutes' => []]);
    $order = pendingOrder($this, 1, 'stripe');

    $this->travel(30)->days();

    $cancel = app(CancelStalePendingOrders::class);

    expect($cancel->isEnabled())->toBeFalse()
        ->and($cancel->handle())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('can keep one gateway forever with a zero wait', function () {
    config(['shop.pending_orders.gateway_minutes' => ['bank_transfer' => 0]]);
    $transfer = pendingOrder($this, 1, 'bank_transfer');
    $card = pendingOrder($this, 1, 'stripe');

    $this->travel(30)->days();
    app(CancelStalePendingOrders::class)->handle();

    expect($transfer->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($card->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('reads the wait of a gateway', function () {
    $cancel = app(CancelStalePendingOrders::class);

    expect($cancel->minutesFor('bank_transfer'))->toBe(10080)
        ->and($cancel->minutesFor('stripe'))->toBe(1440);
});

it('works through many orders in chunks', function () {
    config(['shop.pending_orders.chunk' => 2]);
    $this->kettle->update(['stock' => 20]);

    foreach (range(1, 5) as $ignored) {
        pendingOrder($this, 1, 'stripe');
    }

    $this->travel(2)->days();

    expect(app(CancelStalePendingOrders::class)->handle())->toBe(5)
        ->and($this->kettle->fresh()->stock)->toBe(20);
});

it('runs from the job and the artisan command', function () {
    $first = pendingOrder($this, 1, 'stripe');
    $this->travel(2)->days();

    (new CancelPendingOrders)->handle(app(CancelStalePendingOrders::class));

    expect($first->fresh()->status)->toBe(OrderStatus::Cancelled);

    app(Kernel::class)->registerCommand(app(CancelPendingOrdersCommand::class));
    pendingOrder($this, 1, 'stripe');
    $this->travel(2)->days();

    $this->artisan('shop:cancel-pending')
        ->expectsOutput('1 pending orders cancelled.')
        ->assertSuccessful();
});

it('is scheduled with the cron expression from config', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'shop:cancel-pending');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});

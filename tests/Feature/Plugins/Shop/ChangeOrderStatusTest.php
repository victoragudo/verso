<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Events\OrderStatusChanged;
use Verso\Plugins\Shop\Exceptions\InvalidStatusChange;
use Verso\Plugins\Shop\Mail\OrderStatusMail;

beforeEach(function () {
    ShopFixtures::boot();
    Mail::fake();
    $this->change = app(ChangeOrderStatus::class);
    $this->mug = ShopFixtures::product('Stoneware Mug', 2200, ['track_stock' => true, 'stock' => 10]);
    $this->tote = ShopFixtures::product('Canvas Tote', 1900, ['track_stock' => true], [['Size', 'Large', null, 5]]);
    $this->order = ShopFixtures::order([[$this->mug, 3], [$this->tote, 2, $this->tote->variants->first()]]);
});

it('takes items out of stock when the order is placed', function () {
    expect($this->mug->fresh()->stock)->toBe(7)
        ->and($this->tote->variants->first()->fresh()->stock)->toBe(3);
});

it('marks an order as paid and saves the time', function () {
    $this->freezeTime();

    $this->change->handle($this->order, OrderStatus::Paid);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->order->fresh()->paid_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($this->mug->fresh()->stock)->toBe(7);
});

it('puts items back in stock when an order is cancelled', function () {
    $this->change->handle($this->order, OrderStatus::Cancelled);

    expect($this->mug->fresh()->stock)->toBe(10)
        ->and($this->tote->variants->first()->fresh()->stock)->toBe(5)
        ->and($this->order->fresh()->stock_reduced)->toBeFalse();
});

it('puts items back in stock when a shipped order is refunded', function () {
    $this->change->handle($this->order, OrderStatus::Paid);
    $this->change->handle($this->order, OrderStatus::Shipped);
    $this->change->handle($this->order, OrderStatus::Refunded);

    expect($this->mug->fresh()->stock)->toBe(10)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('never puts stock back twice', function () {
    $this->change->handle($this->order, OrderStatus::Paid);
    $this->change->handle($this->order, OrderStatus::Cancelled);

    expect(fn () => $this->change->handle($this->order, OrderStatus::Refunded))->toThrow(InvalidStatusChange::class);
    expect($this->mug->fresh()->stock)->toBe(10);
});

it('skips items whose product was deleted', function () {
    $this->tote->entry->forceDelete();

    $this->change->handle($this->order, OrderStatus::Cancelled);

    expect($this->mug->fresh()->stock)->toBe(10)
        ->and($this->order->fresh()->items)->toHaveCount(2);
});

it('refuses changes that make no sense', function () {
    expect(fn () => $this->change->handle($this->order, OrderStatus::Shipped))
        ->toThrow(InvalidStatusChange::class, 'An order that is "Pending payment" cannot become "Shipped".');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('keeps a history with the user and a note', function () {
    $admin = User::factory()->admin()->create(['name' => 'Marta Ibáñez']);

    $this->change->handle($this->order, OrderStatus::Paid, $admin, 'Transfer arrived on Monday.');

    $history = $this->order->fresh()->statusChanges()->with('user')->get();

    expect($history)->toHaveCount(2)
        ->and($history->last()->from_status)->toBe(OrderStatus::Pending)
        ->and($history->last()->to_status)->toBe(OrderStatus::Paid)
        ->and($history->last()->user?->name)->toBe('Marta Ibáñez')
        ->and($history->last()->note)->toBe('Transfer arrived on Monday.');
});

it('emails the customer about the new status', function () {
    $this->change->handle($this->order, OrderStatus::Paid);
    $this->change->handle($this->order, OrderStatus::Shipped);

    Mail::assertSent(OrderStatusMail::class, 2);
    Mail::assertSent(OrderStatusMail::class, fn (OrderStatusMail $mail) => $mail->hasTo('lucia.fernandez@example.es')
        && $mail->previousStatus === OrderStatus::Paid
        && $mail->message() === 'Good news: your order is on its way.');
});

it('dispatches an event with both statuses', function () {
    Event::fake([OrderStatusChanged::class]);

    $this->change->handle($this->order, OrderStatus::Cancelled);

    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->from === OrderStatus::Pending
        && $event->to === OrderStatus::Cancelled);
});

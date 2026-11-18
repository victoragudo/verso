<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Enums\OrderStatus;

beforeEach(fn () => ShopFixtures::autoload());

it('allows only sensible status changes', function (string $status, array $next) {
    $from = OrderStatus::from($status);
    $allowed = array_map(OrderStatus::from(...), $next);

    expect($from->next())->toBe($allowed);

    foreach (OrderStatus::cases() as $to) {
        expect($from->canMoveTo($to))->toBe(in_array($to, $allowed, true));
    }
})->with([
    'pending' => ['pending', ['paid', 'cancelled']],
    'paid' => ['paid', ['shipped', 'completed', 'cancelled', 'refunded']],
    'shipped' => ['shipped', ['completed', 'refunded']],
    'completed' => ['completed', ['refunded']],
    'cancelled' => ['cancelled', []],
    'refunded' => ['refunded', []],
]);

it('puts stock back only when an order is cancelled or refunded', function () {
    $releasing = array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->releasesStock()));

    expect($releasing)->toBe([OrderStatus::Cancelled, OrderStatus::Refunded]);
});

it('knows which statuses are paid and final', function () {
    expect(OrderStatus::Paid->isPaid())->toBeTrue()
        ->and(OrderStatus::Shipped->isPaid())->toBeTrue()
        ->and(OrderStatus::Completed->isPaid())->toBeTrue()
        ->and(OrderStatus::Pending->isPaid())->toBeFalse()
        ->and(OrderStatus::Refunded->isPaid())->toBeFalse()
        ->and(OrderStatus::Cancelled->isFinal())->toBeTrue()
        ->and(OrderStatus::Completed->isFinal())->toBeFalse();
});

it('has a label and a badge tone for every status', function () {
    expect(OrderStatus::Pending->label())->toBe('Pending payment')
        ->and(OrderStatus::Pending->tone())->toBe('warning')
        ->and(OrderStatus::Completed->tone())->toBe('success')
        ->and(OrderStatus::Refunded->tone())->toBe('danger');

    foreach (OrderStatus::cases() as $status) {
        expect($status->label())->not->toBe('')
            ->and($status->tone())->toBeIn(['success', 'warning', 'danger', 'neutral', 'info']);
    }
});

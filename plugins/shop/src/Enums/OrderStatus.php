<?php

namespace Verso\Plugins\Shop\Enums;

// The life of an order, from the checkout to the end.
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('shop::Pending payment'),
            self::Paid => __('shop::Paid'),
            self::Shipped => __('shop::Shipped'),
            self::Completed => __('shop::Completed'),
            self::Cancelled => __('shop::Cancelled'),
            self::Refunded => __('shop::Refunded'),
        };
    }

    /**
     * The statuses an order can move to from this one.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Completed, self::Cancelled, self::Refunded],
            self::Shipped => [self::Completed, self::Refunded],
            self::Completed => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canMoveTo(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    // Moving to this status puts the items back in stock.
    public function releasesStock(): bool
    {
        return $this === self::Cancelled || $this === self::Refunded;
    }

    // True when the customer has paid for the order.
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Shipped, self::Completed], true);
    }

    // No more changes are possible.
    public function isFinal(): bool
    {
        return $this->next() === [];
    }

    // Badge color in the admin.
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid, self::Shipped => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
            self::Refunded => 'danger',
        };
    }
}

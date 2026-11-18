<?php

namespace Verso\Plugins\Shop\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Exceptions\InvalidStatusChange;
use Verso\Plugins\Shop\Models\Order;

// Cancels pending orders that were not paid in time, so their items go back in stock.
class CancelStalePendingOrders
{
    // Note saved in the order history.
    public const NOTE = 'Cancelled automatically: the order was not paid in time.';

    /**
     * @param  int  $defaultMinutes  minutes a pending order may wait (0 turns the clean up off)
     * @param  array<string, int>  $gatewayMinutes  waits for single gateways, like a longer one for bank transfers
     */
    public function __construct(
        protected ChangeOrderStatus $changeStatus,
        protected int $defaultMinutes,
        protected array $gatewayMinutes = [],
        protected int $chunk = 100,
    ) {}

    // Returns how many orders were cancelled.
    public function handle(?CarbonImmutable $now = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $now ??= CarbonImmutable::now();
        $cancelled = 0;

        $this->staleOrders($now)->chunkById(max(1, $this->chunk), function ($orders) use (&$cancelled): void {
            foreach ($orders as $order) {
                $cancelled += $this->cancel($order) ? 1 : 0;
            }
        });

        return $cancelled;
    }

    // Minutes an order of this gateway may stay pending. Zero or less means never cancel.
    public function minutesFor(string $gateway): int
    {
        return $this->gatewayMinutes[$gateway] ?? $this->defaultMinutes;
    }

    // True when at least one gateway has a wait, so there is something to check.
    public function isEnabled(): bool
    {
        return $this->defaultMinutes > 0 || array_filter($this->gatewayMinutes, fn (int $minutes): bool => $minutes > 0) !== [];
    }

    /**
     * Pending orders older than the wait of their gateway.
     *
     * @return Builder<Order>
     */
    public function staleOrders(CarbonImmutable $now): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where(function (Builder $query) use ($now): void {
                foreach ($this->gatewayMinutes as $gateway => $minutes) {
                    if ($minutes > 0) {
                        $query->orWhere(fn (Builder $inner) => $inner
                            ->where('payment_gateway', $gateway)
                            ->where('created_at', '<', $now->subMinutes($minutes)));
                    }
                }

                if ($this->defaultMinutes > 0) {
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->whereNotIn('payment_gateway', array_keys($this->gatewayMinutes))
                        ->where('created_at', '<', $now->subMinutes($this->defaultMinutes)));
                }
            });
    }

    // An order paid in the meantime (a late webhook) is skipped.
    protected function cancel(Order $order): bool
    {
        try {
            $this->changeStatus->handle($order->refresh(), OrderStatus::Cancelled, null, self::NOTE);

            return true;
        } catch (InvalidStatusChange) {
            return false;
        } catch (Throwable $exception) {
            Log::warning("The shop could not cancel the pending order {$order->number()}.", ['exception' => $exception]);

            return false;
        }
    }
}

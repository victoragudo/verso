<?php

namespace Verso\Plugins\Shop\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Events\OrderStatusChanged;
use Verso\Plugins\Shop\Exceptions\InvalidStatusChange;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Models\OrderItem;
use Verso\Plugins\Shop\Support\StockKeeper;

// Moves an order to another status, puts items back in stock when needed and keeps a history.
class ChangeOrderStatus
{
    public function __construct(protected StockKeeper $stock) {}

    public function handle(Order $order, OrderStatus $to, ?User $user = null, ?string $note = null): Order
    {
        $from = $order->status;

        if (! $from->canMoveTo($to)) {
            throw InvalidStatusChange::between($from, $to);
        }

        DB::transaction(function () use ($order, $from, $to, $user, $note): void {
            if ($to->releasesStock() && $order->stock_reduced) {
                $this->restoreStock($order);
                $order->stock_reduced = false;
            }

            if ($to === OrderStatus::Paid && $order->paid_at === null) {
                $order->paid_at = now();
            }

            $order->status = $to;
            $order->save();

            $order->statusChanges()->create([
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $user?->id,
                'note' => $note,
            ]);
        });

        OrderStatusChanged::dispatch($order, $from, $to);

        return $order;
    }

    // Items of deleted products or variants cannot go back in stock.
    protected function restoreStock(Order $order): void
    {
        $order->loadMissing('items.product.entry', 'items.variant');

        $order->items->each(function (OrderItem $item): void {
            if ($item->product === null || ($item->variant_id !== null && $item->variant === null)) {
                return;
            }

            $this->stock->restore($item->product, $item->variant, $item->quantity);
        });
    }
}

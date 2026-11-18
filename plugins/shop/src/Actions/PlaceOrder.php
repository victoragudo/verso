<?php

namespace Verso\Plugins\Shop\Actions;

use Illuminate\Support\Facades\DB;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Cart\CartLine;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Events\OrderPlaced;
use Verso\Plugins\Shop\Exceptions\EmptyCart;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\PaymentGateway;
use Verso\Plugins\Shop\Support\ShopSettings;
use Verso\Plugins\Shop\Support\StockKeeper;

/**
 * Turns the cart into a pending order: copies the lines, takes the items out of stock and empties the cart.
 *
 * @phpstan-type CustomerData array{customer_name: string, customer_email: string, customer_phone?: string|null, address_line1: string, address_line2?: string|null, city: string, postal_code: string, region?: string|null, country: string, notes?: string|null}
 */
class PlaceOrder
{
    public function __construct(
        protected ShopSettings $settings,
        protected StockKeeper $stock,
    ) {}

    /**
     * @param  CustomerData  $customer
     */
    public function handle(Cart $cart, array $customer, PaymentGateway $gateway): Order
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            throw new EmptyCart;
        }

        $order = DB::transaction(function () use ($cart, $lines, $customer, $gateway): Order {
            $order = new Order([
                ...$customer,
                'currency' => $this->settings->currency(),
                'payment_gateway' => $gateway->key(),
            ]);
            $order->status = OrderStatus::Pending;
            $order->applyTotals($cart->totals());
            $order->stock_reduced = true;
            $order->save();

            $lines->each(function (CartLine $line) use ($order): void {
                $this->stock->reduce($line->product, $line->variant, $line->quantity);

                $order->items()->create([
                    'product_id' => $line->product->id,
                    'variant_id' => $line->variant?->id,
                    'name' => $line->name(),
                    'variant_label' => $line->variantLabel(),
                    'sku' => $line->product->skuFor($line->variant),
                    'unit_price' => $line->unitPrice(),
                    'quantity' => $line->quantity,
                    'line_total' => $line->total(),
                ]);
            });

            $order->statusChanges()->create(['from_status' => null, 'to_status' => OrderStatus::Pending]);

            return $order;
        });

        $cart->clear();

        OrderPlaced::dispatch($order->load('items'));

        return $order;
    }
}

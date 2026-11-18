<?php

namespace Verso\Plugins\Shop\Payments\Stripe;

use Illuminate\Support\Facades\Log;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\Gateways\StripeCheckoutGateway;

// Applies a checked Stripe event to the matching order.
class StripeWebhook
{
    // Events that mean the money arrived.
    public const PAID_EVENTS = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    // Events that mean the customer will not pay this session.
    public const FAILED_EVENTS = ['checkout.session.expired', 'checkout.session.async_payment_failed'];

    public function __construct(protected ChangeOrderStatus $changeStatus) {}

    /**
     * Returns a short word about what happened, like "paid" or "ignored".
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): string
    {
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $session = $event['data']['object'] ?? null;

        if (! is_array($session) || ! in_array($type, [...self::PAID_EVENTS, ...self::FAILED_EVENTS], true)) {
            return 'ignored';
        }

        $order = $this->findOrder($session);

        if ($order === null) {
            return 'unknown_order';
        }

        if (in_array($type, self::FAILED_EVENTS, true)) {
            return $this->cancel($order);
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            return 'waiting';
        }

        return $this->markPaid($order, $session);
    }

    /**
     * The order the session was made for. The session id must match the one saved on the order.
     *
     * @param  array<mixed>  $session
     */
    protected function findOrder(array $session): ?Order
    {
        $orderId = $session['client_reference_id'] ?? null;
        $sessionId = $session['id'] ?? null;

        if (! is_numeric($orderId) || ! is_string($sessionId)) {
            return null;
        }

        return Order::query()
            ->whereKey((int) $orderId)
            ->where('payment_gateway', StripeCheckoutGateway::KEY)
            ->where('payment_reference', $sessionId)
            ->first();
    }

    /** @param array<mixed> $session */
    protected function markPaid(Order $order, array $session): string
    {
        if ($order->status !== OrderStatus::Pending) {
            return 'already_handled';
        }

        $amount = $session['amount_total'] ?? null;
        $currency = is_string($session['currency'] ?? null) ? strtoupper($session['currency']) : null;

        if ((int) $amount !== $order->total || $currency !== $order->currency) {
            Log::warning("Stripe paid amount does not match order {$order->number()}.", [
                'expected' => [$order->total, $order->currency],
                'received' => [$amount, $currency],
            ]);

            return 'amount_mismatch';
        }

        $this->changeStatus->handle($order, OrderStatus::Paid, note: 'Paid with Stripe Checkout.');

        return 'paid';
    }

    protected function cancel(Order $order): string
    {
        if ($order->status !== OrderStatus::Pending) {
            return 'already_handled';
        }

        $this->changeStatus->handle($order, OrderStatus::Cancelled, note: 'The Stripe payment was not finished.');

        return 'cancelled';
    }
}

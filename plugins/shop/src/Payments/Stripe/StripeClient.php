<?php

namespace Verso\Plugins\Shop\Payments\Stripe;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Verso\Plugins\Shop\Exceptions\PaymentFailed;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Models\OrderItem;

// Talks to the Stripe REST API with Laravel Http. No Stripe SDK is needed.
class StripeClient
{
    public function __construct(
        protected string $apiUrl,
        protected int $timeout,
        protected int $retries,
        protected int $retryDelay,
    ) {}

    /**
     * Create a Checkout Session for an order. Returns the session id and the payment page URL.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Order $order, string $secretKey, string $returnUrl): array
    {
        $response = $this->request($secretKey, fn (PendingRequest $http) => $http->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl,
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->customer_email,
            'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->number()],
            'payment_intent_data' => ['metadata' => ['order_id' => (string) $order->id]],
            'line_items' => $this->lineItems($order),
        ]));

        $id = $response['id'] ?? null;
        $url = $response['url'] ?? null;

        if (! is_string($id) || ! is_string($url) || $id === '' || $url === '') {
            throw new PaymentFailed('Stripe did not return a checkout page.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * One Stripe line item per order line, plus shipping and tax, so Stripe charges the order total.
     *
     * @return list<array<string, mixed>>
     */
    public function lineItems(Order $order): array
    {
        $currency = strtolower($order->currency);
        $item = fn (string $name, int $amount, int $quantity): array => [
            'quantity' => $quantity,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amount,
                'product_data' => ['name' => $name],
            ],
        ];

        $items = array_values($order->items
            ->map(fn (OrderItem $line): array => $item(
                $line->variant_label ? $line->name.' ('.$line->variant_label.')' : $line->name,
                $line->unit_price,
                $line->quantity,
            ))
            ->all());

        if ($order->shipping_total > 0) {
            $items[] = $item(__('shop::Shipping'), $order->shipping_total, 1);
        }

        if ($order->tax_total > 0) {
            $items[] = $item(__('shop::Tax'), $order->tax_total, 1);
        }

        return $items;
    }

    /**
     * Send one form request to Stripe and return the JSON body.
     *
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    protected function request(string $secretKey, callable $send): array
    {
        $http = Http::baseUrl(rtrim($this->apiUrl, '/'))
            ->asForm()
            ->acceptJson()
            ->withToken($secretKey)
            ->timeout(max(1, $this->timeout))
            ->retry(max(1, $this->retries + 1), max(0, $this->retryDelay), fn ($exception): bool => $exception instanceof ConnectionException, throw: false);

        try {
            $response = $send($http);
        } catch (ConnectionException $exception) {
            throw new PaymentFailed('Stripe could not be reached. Please try again.', previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error.message');

            throw new PaymentFailed(
                'Stripe refused the payment request'.(is_string($message) ? ': '.$message : '.'),
                previous: $response->toException() ?? new RequestException($response),
            );
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}

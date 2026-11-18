<?php

namespace Verso\Plugins\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Exceptions\PaymentFailed;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\Gateways\StripeCheckoutGateway;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Support\Storefront;

// The private order page shown after the checkout (the "thank you" page).
class OrderController extends Controller
{
    public function __construct(protected PaymentGateways $gateways) {}

    public function show(string $token, ThemeResponse $theme, Storefront $storefront): Response
    {
        $order = $this->find($token);
        $gateway = $this->gateways->find($order->payment_gateway);

        $response = $theme->view('shop.thank-you', [
            'order' => $order->load('items'),
            'storefront' => $storefront,
            'paymentLabel' => $this->gateways->label($order->payment_gateway),
            'instructions' => $gateway?->instructions($order) ?? '',
            'canPay' => $this->canPayAgain($order),
        ]);

        // The page shows personal details, so it must never be cached or indexed.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    // Start a new Stripe payment for a pending order, for example after the customer went back.
    public function pay(string $token): RedirectResponse
    {
        $order = $this->find($token);
        $gateway = $this->gateways->available($order->payment_gateway);

        if ($gateway === null || ! $this->canPayAgain($order)) {
            return redirect()->to($order->url());
        }

        try {
            $start = $gateway->start($order);
        } catch (PaymentFailed) {
            return redirect()->to($order->url())->with('shop.error', __('shop::We could not start the payment. Please try again in a moment.'));
        }

        return $start->redirectUrl !== null ? redirect()->away($start->redirectUrl) : redirect()->to($order->url());
    }

    protected function find(string $token): Order
    {
        return Order::query()->where('token', $token)->firstOrFail();
    }

    protected function canPayAgain(Order $order): bool
    {
        return $order->status === OrderStatus::Pending
            && $order->payment_gateway === StripeCheckoutGateway::KEY
            && $this->gateways->available(StripeCheckoutGateway::KEY) !== null;
    }
}

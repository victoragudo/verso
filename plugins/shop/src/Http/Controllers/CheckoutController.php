<?php

namespace Verso\Plugins\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Actions\PlaceOrder;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Exceptions\EmptyCart;
use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Exceptions\PaymentFailed;
use Verso\Plugins\Shop\Http\Requests\CheckoutRequest;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Support\Storefront;

// The checkout page: customer details, shipping address and payment.
class CheckoutController extends Controller
{
    public function __construct(
        protected Cart $cart,
        protected PaymentGateways $gateways,
    ) {}

    public function show(ThemeResponse $theme, Storefront $storefront): Response|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return to_route('theme.shop.cart');
        }

        return $theme->view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals(),
            'gateways' => $this->gateways->enabled(),
            'storefront' => $storefront,
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder, ChangeOrderStatus $changeStatus): RedirectResponse
    {
        $gateway = $this->gateways->available((string) $request->validated('payment_gateway'));

        if ($gateway === null) {
            return back()->withErrors(['payment_gateway' => __('shop::Please choose a payment method.')])->withInput();
        }

        try {
            $order = $placeOrder->handle($this->cart, $request->customer(), $gateway);
        } catch (EmptyCart) {
            return to_route('theme.shop.cart');
        } catch (OutOfStock $exception) {
            return to_route('theme.shop.cart')->withErrors(['quantities' => $exception->getMessage()]);
        }

        try {
            $start = $gateway->start($order);
        } catch (PaymentFailed $exception) {
            Log::warning("Payment for order {$order->number()} could not start.", ['exception' => $exception]);
            $changeStatus->handle($order, OrderStatus::Cancelled, note: $exception->getMessage());

            return to_route('theme.shop.order', ['token' => $order->token])
                ->with('shop.error', __('shop::We could not start the payment. Your order was cancelled, please try again.'));
        }

        return $start->redirectUrl !== null
            ? redirect()->away($start->redirectUrl)
            : redirect()->to($order->url());
    }
}

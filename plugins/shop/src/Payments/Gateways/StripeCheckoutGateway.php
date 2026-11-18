<?php

namespace Verso\Plugins\Shop\Payments\Gateways;

use Verso\Plugins\Shop\Exceptions\PaymentFailed;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Payments\PaymentGateway;
use Verso\Plugins\Shop\Payments\PaymentStart;
use Verso\Plugins\Shop\Payments\Stripe\StripeClient;
use Verso\Plugins\Shop\Support\ShopSettings;

// Card payments on the Stripe Checkout page. A signed webhook marks the order as paid.
class StripeCheckoutGateway implements PaymentGateway
{
    public const KEY = 'stripe';

    public function __construct(
        protected ShopSettings $settings,
        protected StripeClient $stripe,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('shop::Card (Stripe)');
    }

    public function description(): string
    {
        return __('shop::Pay by card on a secure Stripe page.');
    }

    // Needs both keys: the secret key to create payments and the webhook secret to trust Stripe's answer.
    public function isAvailable(): bool
    {
        return $this->settings->stripeEnabled()
            && $this->settings->stripeSecretKey() !== null
            && $this->settings->stripeWebhookSecret() !== null;
    }

    public function start(Order $order): PaymentStart
    {
        $secret = $this->settings->stripeSecretKey() ?? throw new PaymentFailed('Stripe is not set up.');

        $session = $this->stripe->createCheckoutSession($order->loadMissing('items'), $secret, $order->url());

        $order->payment_reference = $session['id'];
        $order->save();

        return PaymentStart::redirect($session['url']);
    }

    public function instructions(Order $order): string
    {
        return '';
    }
}

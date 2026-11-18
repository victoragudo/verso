<?php

namespace Verso\Plugins\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Shop\Http\Requests\ShopSettingsRequest;
use Verso\Plugins\Shop\Support\Money;
use Verso\Plugins\Shop\Support\ShopSettings;

// Admin page for currency, tax, shipping, emails and payment methods.
class SettingsController extends Controller
{
    public function edit(ShopSettings $settings, Money $money): Response
    {
        Gate::authorize(Capability::ManageShop->value);

        $currency = $settings->currency();
        $freeOver = $settings->freeShippingOver();

        return Inertia::render('admin/plugins/shop/settings', [
            'settings' => [
                'currency' => $currency,
                'tax_rate' => rtrim(rtrim(number_format($settings->taxRate() / 100, 2, '.', ''), '0'), '.'),
                'tax_shipping' => $settings->taxShipping(),
                'shipping_rate' => $money->toDecimal($settings->shippingRate(), $currency),
                'free_shipping_over' => $freeOver === null ? '' : $money->toDecimal($freeOver, $currency),
                'notify_email' => $settings->notifyEmail() ?? '',
                'bank_transfer_enabled' => $settings->bankTransferEnabled(),
                'bank_transfer_instructions' => $settings->bankTransferInstructions(),
                'stripe_enabled' => $settings->stripeEnabled(),
            ],
            'stripe' => [
                'hasSecretKey' => $settings->stripeSecretKey() !== null,
                'hasWebhookSecret' => $settings->stripeWebhookSecret() !== null,
                'webhookUrl' => route('shop.stripe.webhook'),
                'events' => ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'],
            ],
            'currencies' => array_values((array) config('shop.currencies')),
            'updateUrl' => route('admin.shop.settings.update'),
        ]);
    }

    public function update(ShopSettingsRequest $request, ShopSettings $settings): RedirectResponse
    {
        $settings->save($request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop::Shop settings saved.')]);

        return to_route('admin.shop.settings.edit');
    }
}

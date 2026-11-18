<?php

namespace Verso\Plugins\Shop\Http\Requests;

use App\Verso\Core\Enums\Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Verso\Plugins\Shop\Support\Money;
use Verso\Plugins\Shop\Support\ShopSettings;

// Checks the shop settings form.
class ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Capability::ManageShop->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', Rule::in((array) config('shop.currencies'))],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'tax_shipping' => ['required', 'boolean'],
            'shipping_rate' => ['required', 'string', 'regex:'.ProductRequest::AMOUNT],
            'free_shipping_over' => ['nullable', 'string', 'regex:'.ProductRequest::AMOUNT],
            'notify_email' => ['nullable', 'string', 'email', 'max:190'],
            'bank_transfer_enabled' => ['required', 'boolean'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:2000'],
            'stripe_enabled' => ['required', 'boolean'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_[A-Za-z0-9]+$/'],
            'remove_stripe_keys' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'shipping_rate.regex' => __('shop::Use a number with a dot, like 4.95.'),
            'free_shipping_over.regex' => __('shop::Use a number with a dot, like 60.'),
            'stripe_secret_key.regex' => __('shop::Stripe secret keys start with sk_test_ or sk_live_.'),
            'stripe_webhook_secret.regex' => __('shop::Stripe webhook secrets start with whsec_.'),
        ];
    }

    /**
     * Stripe can only be turned on when both keys are known.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('stripe_enabled')) {
                return;
            }

            $settings = app(ShopSettings::class);
            $removing = $this->boolean('remove_stripe_keys');

            if (! $this->filled('stripe_secret_key') && ($removing || $settings->stripeSecretKey() === null)) {
                $validator->errors()->add('stripe_secret_key', __('shop::Add the Stripe secret key to turn on card payments.'));
            }

            if (! $this->filled('stripe_webhook_secret') && ($removing || $settings->stripeWebhookSecret() === null)) {
                $validator->errors()->add('stripe_webhook_secret', __('shop::Add the webhook signing secret to turn on card payments.'));
            }
        }];
    }

    /**
     * Values for ShopSettings::save(). Amounts are in minor units, the tax rate in basis points.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $money = app(Money::class);
        $currency = (string) $this->validated('currency');
        $removing = $this->boolean('remove_stripe_keys');

        return [
            'currency' => $currency,
            'tax_rate' => (int) round((float) $this->validated('tax_rate') * 100),
            'tax_shipping' => $this->boolean('tax_shipping'),
            'shipping_rate' => $money->toMinor((string) $this->validated('shipping_rate'), $currency),
            'free_shipping_over' => $this->filled('free_shipping_over') ? $money->toMinor((string) $this->validated('free_shipping_over'), $currency) : null,
            'notify_email' => $this->filled('notify_email') ? trim((string) $this->validated('notify_email')) : '',
            'bank_transfer_enabled' => $this->boolean('bank_transfer_enabled'),
            'bank_transfer_instructions' => trim((string) $this->validated('bank_transfer_instructions')),
            'stripe_enabled' => $this->boolean('stripe_enabled'),
            'stripe_secret_key' => $this->filled('stripe_secret_key') ? (string) $this->validated('stripe_secret_key') : ($removing ? '' : null),
            'stripe_webhook_secret' => $this->filled('stripe_webhook_secret') ? (string) $this->validated('stripe_webhook_secret') : ($removing ? '' : null),
        ];
    }
}

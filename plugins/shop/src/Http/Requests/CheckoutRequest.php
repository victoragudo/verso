<?php

namespace Verso\Plugins\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Verso\Plugins\Shop\Payments\PaymentGateway;
use Verso\Plugins\Shop\Payments\PaymentGateways;

// Checks the customer details, the shipping address and the payment choice.
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $gateways = array_map(fn (PaymentGateway $gateway): string => $gateway->key(), app(PaymentGateways::class)->enabled());

        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'string', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'address_line1' => ['required', 'string', 'max:190'],
            'address_line2' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_gateway' => ['required', 'string', Rule::in($gateways)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_gateway.in' => __('shop::Please choose a payment method.'),
            'country.size' => __('shop::Use the two letter country code, like ES or DE.'),
        ];
    }

    /**
     * The customer fields for PlaceOrder.
     *
     * @return array{customer_name: string, customer_email: string, customer_phone: string|null, address_line1: string, address_line2: string|null, city: string, postal_code: string, region: string|null, country: string, notes: string|null}
     */
    public function customer(): array
    {
        $optional = fn (string $key): ?string => $this->filled($key) ? trim((string) $this->validated($key)) : null;

        return [
            'customer_name' => trim((string) $this->validated('customer_name')),
            'customer_email' => strtolower(trim((string) $this->validated('customer_email'))),
            'customer_phone' => $optional('customer_phone'),
            'address_line1' => trim((string) $this->validated('address_line1')),
            'address_line2' => $optional('address_line2'),
            'city' => trim((string) $this->validated('city')),
            'postal_code' => trim((string) $this->validated('postal_code')),
            'region' => $optional('region'),
            'country' => strtoupper((string) $this->validated('country')),
            'notes' => $optional('notes'),
        ];
    }
}

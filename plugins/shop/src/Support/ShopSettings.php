<?php

namespace Verso\Plugins\Shop\Support;

use App\Verso\Core\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

// Typed access to the shop settings. Unsaved values come from config/shop.php.
class ShopSettings
{
    // Prefix of every shop key in the settings table.
    public const PREFIX = 'shop.';

    // Keys stored encrypted.
    public const SECRETS = ['stripe_secret_key', 'stripe_webhook_secret'];

    public function __construct(protected Settings $settings) {}

    public function currency(): string
    {
        return strtoupper($this->string('currency', (string) config('shop.defaults.currency')));
    }

    // Tax rate in basis points: 2100 means 21%.
    public function taxRate(): int
    {
        $stored = $this->settings->get(self::PREFIX.'tax_rate');

        return is_numeric($stored)
            ? max(0, (int) $stored)
            : max(0, (int) round((float) config('shop.defaults.tax_rate') * 100));
    }

    // True when tax is also added on the shipping price.
    public function taxShipping(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'tax_shipping', (bool) config('shop.defaults.tax_shipping'));
    }

    // Flat shipping price for an order, in minor units.
    public function shippingRate(): int
    {
        return max(0, (int) $this->settings->get(self::PREFIX.'shipping_rate', (int) config('shop.defaults.shipping_rate')));
    }

    // Orders with this subtotal or more ship for free. Null means never.
    public function freeShippingOver(): ?int
    {
        $value = $this->settings->has(self::PREFIX.'free_shipping_over')
            ? $this->settings->get(self::PREFIX.'free_shipping_over')
            : config('shop.defaults.free_shipping_over');

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    // Address that gets a copy of every new order. Empty means nobody.
    public function notifyEmail(): ?string
    {
        $email = $this->string('notify_email', (string) config('shop.defaults.notify_email'));

        return $email === '' ? null : $email;
    }

    public function bankTransferEnabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'bank_transfer_enabled', (bool) config('shop.defaults.bank_transfer_enabled'));
    }

    public function bankTransferInstructions(): string
    {
        return $this->string('bank_transfer_instructions', (string) config('shop.defaults.bank_transfer_instructions'));
    }

    public function stripeEnabled(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'stripe_enabled', (bool) config('shop.defaults.stripe_enabled'));
    }

    public function stripeSecretKey(): ?string
    {
        return $this->secret('stripe_secret_key', config('shop.stripe.secret_key'));
    }

    public function stripeWebhookSecret(): ?string
    {
        return $this->secret('stripe_webhook_secret', config('shop.stripe.webhook_secret'));
    }

    // The totals of a subtotal with the current shipping and tax settings.
    public function totals(int $subtotal): Totals
    {
        return Totals::calculate(
            subtotal: $subtotal,
            shippingRate: $this->shippingRate(),
            taxRate: $this->taxRate(),
            taxShipping: $this->taxShipping(),
            freeShippingOver: $this->freeShippingOver(),
        );
    }

    /**
     * Save settings by short key, like ['currency' => 'EUR']. Secrets are encrypted, and null secrets are kept.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $stored = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                if ($value === null) {
                    continue;
                }

                $value = $value === '' ? null : Crypt::encryptString((string) $value);
            }

            $stored[self::PREFIX.$key] = $value;
        }

        $this->settings->setMany($stored);
    }

    protected function string(string $key, string $default): string
    {
        $value = $this->settings->get(self::PREFIX.$key);

        return is_string($value) ? $value : $default;
    }

    // A decrypted secret, or the env fallback when nothing is saved.
    protected function secret(string $key, mixed $fallback): ?string
    {
        $value = $this->settings->get(self::PREFIX.$key);

        if (! is_string($value) || $value === '') {
            return is_string($fallback) && $fallback !== '' ? $fallback : null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}

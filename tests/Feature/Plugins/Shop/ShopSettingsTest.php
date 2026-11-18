<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\PluginManager;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(function () {
    ShopFixtures::autoload();
    $manager = app(PluginManager::class);
    $manager->enable('shop');
    $manager->register($manager->get('shop'));
    $this->settings = app(ShopSettings::class);
});

it('reads defaults from the plugin config', function () {
    config([
        'shop.defaults.currency' => 'gbp',
        'shop.defaults.tax_rate' => 7.25,
        'shop.defaults.shipping_rate' => 450,
        'shop.defaults.free_shipping_over' => 5000,
        'shop.defaults.notify_email' => 'orders@harbourpottery.example',
        'shop.defaults.bank_transfer_enabled' => false,
        'shop.defaults.stripe_enabled' => true,
    ]);

    expect($this->settings->currency())->toBe('GBP')
        ->and($this->settings->taxRate())->toBe(725)
        ->and($this->settings->shippingRate())->toBe(450)
        ->and($this->settings->freeShippingOver())->toBe(5000)
        ->and($this->settings->notifyEmail())->toBe('orders@harbourpottery.example')
        ->and($this->settings->bankTransferEnabled())->toBeFalse()
        ->and($this->settings->stripeEnabled())->toBeTrue();
});

it('prefers saved values over the config', function () {
    $this->settings->save([
        'currency' => 'USD',
        'tax_rate' => 2100,
        'tax_shipping' => false,
        'shipping_rate' => 0,
        'free_shipping_over' => null,
        'notify_email' => '',
    ]);

    expect($this->settings->currency())->toBe('USD')
        ->and($this->settings->taxRate())->toBe(2100)
        ->and($this->settings->taxShipping())->toBeFalse()
        ->and($this->settings->shippingRate())->toBe(0)
        ->and($this->settings->freeShippingOver())->toBeNull()
        ->and($this->settings->notifyEmail())->toBeNull();
});

it('stores Stripe keys encrypted', function () {
    $this->settings->save(['stripe_secret_key' => 'sk_test_51HarbourPottery', 'stripe_webhook_secret' => 'whsec_9fK2harbour']);

    $stored = app(Settings::class)->get('shop.stripe_secret_key');

    expect($stored)->not->toContain('sk_test')
        ->and(Crypt::decryptString($stored))->toBe('sk_test_51HarbourPottery')
        ->and($this->settings->stripeSecretKey())->toBe('sk_test_51HarbourPottery')
        ->and($this->settings->stripeWebhookSecret())->toBe('whsec_9fK2harbour');
});

it('keeps saved keys when null is given and clears them with an empty string', function () {
    $this->settings->save(['stripe_secret_key' => 'sk_test_51HarbourPottery']);
    $this->settings->save(['stripe_secret_key' => null, 'currency' => 'EUR']);

    expect($this->settings->stripeSecretKey())->toBe('sk_test_51HarbourPottery');

    $this->settings->save(['stripe_secret_key' => '']);

    expect($this->settings->stripeSecretKey())->toBeNull();
});

it('falls back to keys from the environment config', function () {
    config(['shop.stripe.secret_key' => 'sk_live_FromServerEnv', 'shop.stripe.webhook_secret' => 'whsec_FromServerEnv']);

    expect($this->settings->stripeSecretKey())->toBe('sk_live_FromServerEnv')
        ->and($this->settings->stripeWebhookSecret())->toBe('whsec_FromServerEnv');
});

it('ignores keys that cannot be decrypted', function () {
    app(Settings::class)->set('shop.stripe_secret_key', 'not-encrypted-at-all');

    expect($this->settings->stripeSecretKey())->toBeNull();
});

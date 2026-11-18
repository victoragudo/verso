<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Support\ShopSettings;

beforeEach(function () {
    ShopFixtures::boot();
    $this->admin = User::factory()->admin()->create();
});

function shopSettingsPayload(array $overrides = []): array
{
    return [
        'currency' => 'EUR',
        'tax_rate' => '21',
        'tax_shipping' => true,
        'shipping_rate' => '4.95',
        'free_shipping_over' => '60',
        'notify_email' => 'orders@harbourpottery.example',
        'bank_transfer_enabled' => true,
        'bank_transfer_instructions' => 'IBAN ES91 2100 0418 4502 0005 1332',
        'stripe_enabled' => false,
        'stripe_secret_key' => '',
        'stripe_webhook_secret' => '',
        'remove_stripe_keys' => false,
        ...$overrides,
    ];
}

it('shows the shop settings', function () {
    app(ShopSettings::class)->save(['tax_rate' => 725, 'shipping_rate' => 390, 'free_shipping_over' => 5000, 'stripe_secret_key' => 'sk_test_51HarbourPottery']);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/shop/settings')
            ->where('settings.currency', 'EUR')
            ->where('settings.tax_rate', '7.25')
            ->where('settings.shipping_rate', '3.90')
            ->where('settings.free_shipping_over', '50.00')
            ->where('stripe.hasSecretKey', true)
            ->where('stripe.hasWebhookSecret', false)
            ->where('stripe.webhookUrl', url('/shop/webhooks/stripe'))
            ->missing('settings.stripe_secret_key')
            ->where('currencies', config('shop.currencies'))
        );
});

it('never sends saved keys to the browser', function () {
    app(ShopSettings::class)->save(['stripe_secret_key' => 'sk_test_51HarbourPottery', 'stripe_webhook_secret' => 'whsec_9fK2harbour']);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.settings.edit'))
        ->assertDontSee('sk_test_51HarbourPottery')
        ->assertDontSee('whsec_9fK2harbour');
});

it('saves the shop settings in minor units and basis points', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.shop.settings.update'), shopSettingsPayload(['tax_rate' => '7.5']))
        ->assertRedirect(route('admin.shop.settings.edit'));

    $settings = app(ShopSettings::class);

    expect($settings->taxRate())->toBe(750)
        ->and($settings->shippingRate())->toBe(495)
        ->and($settings->freeShippingOver())->toBe(6000)
        ->and($settings->notifyEmail())->toBe('orders@harbourpottery.example')
        ->and($settings->bankTransferInstructions())->toBe('IBAN ES91 2100 0418 4502 0005 1332');
});

it('turns free shipping off when the field is empty', function () {
    $this->actingAs($this->admin)->put(route('admin.shop.settings.update'), shopSettingsPayload(['free_shipping_over' => '']));

    expect(app(ShopSettings::class)->freeShippingOver())->toBeNull();
});

it('uses whole units for currencies without cents', function () {
    $this->actingAs($this->admin)->put(route('admin.shop.settings.update'), shopSettingsPayload(['currency' => 'JPY', 'shipping_rate' => '800']));

    expect(app(ShopSettings::class)->shippingRate())->toBe(800);
});

it('turns on Stripe with new keys and keeps them on later saves', function () {
    $this->actingAs($this->admin)->put(route('admin.shop.settings.update'), shopSettingsPayload([
        'stripe_enabled' => true,
        'stripe_secret_key' => 'sk_live_51HarbourPottery',
        'stripe_webhook_secret' => 'whsec_9fK2harbour',
    ]))->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->put(route('admin.shop.settings.update'), shopSettingsPayload(['stripe_enabled' => true]))
        ->assertSessionHasNoErrors();

    $settings = app(ShopSettings::class);

    expect($settings->stripeEnabled())->toBeTrue()
        ->and($settings->stripeSecretKey())->toBe('sk_live_51HarbourPottery')
        ->and($settings->stripeWebhookSecret())->toBe('whsec_9fK2harbour')
        ->and(app(Settings::class)->get('shop.stripe_secret_key'))->not->toContain('sk_live');
});

it('needs both keys before Stripe can be turned on', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.shop.settings.update'), shopSettingsPayload(['stripe_enabled' => true, 'stripe_secret_key' => 'sk_test_51HarbourPottery']))
        ->assertSessionHasErrors(['stripe_webhook_secret' => 'Add the webhook signing secret to turn on card payments.']);

    expect(app(ShopSettings::class)->stripeEnabled())->toBeFalse();
});

it('removes the saved keys', function () {
    app(ShopSettings::class)->save(['stripe_secret_key' => 'sk_test_51HarbourPottery', 'stripe_webhook_secret' => 'whsec_9fK2harbour']);

    $this->actingAs($this->admin)->put(route('admin.shop.settings.update'), shopSettingsPayload(['remove_stripe_keys' => true]));

    expect(app(ShopSettings::class)->stripeSecretKey())->toBeNull()
        ->and(app(ShopSettings::class)->stripeWebhookSecret())->toBeNull();
});

it('cannot keep Stripe on while removing its keys', function () {
    app(ShopSettings::class)->save(['stripe_secret_key' => 'sk_test_51HarbourPottery', 'stripe_webhook_secret' => 'whsec_9fK2harbour']);

    $this->actingAs($this->admin)
        ->put(route('admin.shop.settings.update'), shopSettingsPayload(['stripe_enabled' => true, 'remove_stripe_keys' => true]))
        ->assertSessionHasErrors(['stripe_secret_key', 'stripe_webhook_secret']);
});

it('checks the settings form', function (array $payload, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.shop.settings.update'), shopSettingsPayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'unknown currency' => [['currency' => 'XBT'], 'currency'],
    'tax over 100' => [['tax_rate' => '120'], 'tax_rate'],
    'tax with three decimals' => [['tax_rate' => '7.125'], 'tax_rate'],
    'negative tax' => [['tax_rate' => '-1'], 'tax_rate'],
    'shipping with comma' => [['shipping_rate' => '4,95'], 'shipping_rate'],
    'bad notify email' => [['notify_email' => 'orders at example'], 'notify_email'],
    'publishable key instead of secret' => [['stripe_secret_key' => 'pk_test_51HarbourPottery'], 'stripe_secret_key'],
    'bad webhook secret' => [['stripe_webhook_secret' => 'secret123'], 'stripe_webhook_secret'],
]);

it('is only for people who manage settings', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.shop.settings.edit'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.shop.settings.update'), shopSettingsPayload())->assertForbidden();
});

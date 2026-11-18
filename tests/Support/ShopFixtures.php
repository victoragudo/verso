<?php

namespace Tests\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Theme\ThemeServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Verso\Plugins\Shop\Actions\PlaceOrder;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;
use Verso\Plugins\Shop\Payments\PaymentGateway;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Support\ShopSettings;

// Turns on the Shop plugin inside a test and makes realistic products.
class ShopFixtures
{
    // Load the plugin classes without turning the plugin on.
    public static function autoload(): void
    {
        $manager = PluginFixtures::use(base_path('plugins'));
        $manager->autoload($manager->get('shop'));
    }

    // Enable the plugin, run its migrations and add its routes like a real boot would.
    public static function boot(): void
    {
        $manager = PluginFixtures::use(base_path('plugins'));
        $manager->enable('shop');
        $manager->register($manager->get('shop'));

        Artisan::call('migrate', ['--path' => base_path('plugins/shop/database/migrations'), '--realpath' => true]);

        // Public theme routes were added before the product type existed, so add them again.
        Route::middleware(ThemeServiceProvider::PUBLIC_MIDDLEWARE)->group(base_path('routes/verso/theme-public.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        app(ShopSettings::class)->save([
            'currency' => 'EUR',
            'tax_rate' => 0,
            'shipping_rate' => 0,
            'free_shipping_over' => null,
            'bank_transfer_enabled' => true,
        ]);
    }

    /**
     * A published product with shop data. Variants are [option_name, option_value, price|null, stock].
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{0: string, 1: string, 2: int|null, 3: int}>  $variants
     */
    public static function product(string $title, int $price, array $data = [], array $variants = [], bool $published = true): Product
    {
        $factory = Entry::factory()->state(['type' => Product::TYPE, 'title' => $title, 'slug' => Str::slug($title)]);
        $entry = ($published ? $factory->published() : $factory)->create();

        $product = Product::query()->create(['entry_id' => $entry->id, 'price' => $price, ...$data]);

        foreach ($variants as $position => [$name, $value, $variantPrice, $stock]) {
            $product->variants()->create([
                'option_name' => $name,
                'option_value' => $value,
                'price' => $variantPrice,
                'stock' => $stock,
                'position' => $position,
                'sku' => strtoupper(Str::slug($title.' '.$value)),
            ]);
        }

        return $product->load(['entry', 'variants']);
    }

    /**
     * Place an order through the cart, like the checkout does. Lines are [product, quantity, variant|null].
     *
     * @param  list<array{0: Product, 1: int, 2?: ProductVariant|null}>  $lines
     * @param  array<string, mixed>  $customer
     */
    public static function order(array $lines, string $gateway = 'bank_transfer', array $customer = []): Order
    {
        app()->forgetScopedInstances();
        $cart = app(Cart::class);

        foreach ($lines as $line) {
            $cart->add($line[0], $line[2] ?? null, $line[1]);
        }

        $details = self::customer($customer);
        unset($details['payment_gateway']);

        /** @var PaymentGateway $paymentGateway */
        $paymentGateway = app(PaymentGateways::class)->find($gateway);

        return app(PlaceOrder::class)->handle($cart, $details, $paymentGateway);
    }

    /**
     * Valid checkout form data for a customer in Valencia.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function customer(array $overrides = []): array
    {
        return [
            'customer_name' => 'Lucía Fernández',
            'customer_email' => 'lucia.fernandez@example.es',
            'customer_phone' => '+34 612 345 678',
            'address_line1' => 'Carrer de la Pau 14',
            'address_line2' => '3º B',
            'city' => 'Valencia',
            'postal_code' => '46002',
            'region' => 'Comunitat Valenciana',
            'country' => 'ES',
            'notes' => 'Please leave the parcel with the porter.',
            'payment_gateway' => 'bank_transfer',
            ...$overrides,
        ];
    }
}

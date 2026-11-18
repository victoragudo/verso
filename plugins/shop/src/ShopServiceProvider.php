<?php

namespace Verso\Plugins\Shop;

use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Taxonomy;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\OptionalRoles;
use App\Verso\Easy\Sections\SectionContent;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionTemplate;
use App\Verso\Plugins\Filters\AdminEntryColumnsFilter;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;
use App\Verso\Plugins\Filters\PrivatePathsFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\Unit;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\ThemeManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Verso\Plugins\Shop\Actions\CancelStalePendingOrders;
use Verso\Plugins\Shop\Actions\ChangeOrderStatus;
use Verso\Plugins\Shop\Blocks\ProductBlock;
use Verso\Plugins\Shop\Blocks\ProductGridBlock;
use Verso\Plugins\Shop\Cart\Cart;
use Verso\Plugins\Shop\Console\CancelPendingOrdersCommand;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Events\OrderPlaced;
use Verso\Plugins\Shop\Events\OrderStatusChanged;
use Verso\Plugins\Shop\Http\Controllers\StripeWebhookController;
use Verso\Plugins\Shop\Jobs\CancelPendingOrders;
use Verso\Plugins\Shop\Listeners\SendOrderEmails;
use Verso\Plugins\Shop\Models\Order;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Payments\Gateways\BankTransferGateway;
use Verso\Plugins\Shop\Payments\Gateways\StripeCheckoutGateway;
use Verso\Plugins\Shop\Payments\PaymentGateways;
use Verso\Plugins\Shop\Payments\Stripe\StripeClient;
use Verso\Plugins\Shop\Payments\Stripe\StripeSignature;
use Verso\Plugins\Shop\Support\Money;
use Verso\Plugins\Shop\Support\ShopSettings;
use Verso\Plugins\Shop\View\ProductCategoryComposer;
use Verso\Plugins\Shop\View\ProductPageComposer;
use Verso\Plugins\Shop\View\ShopStylesComposer;

// Boots the Shop plugin: the product type, blocks, cart, checkout, payments and admin pages.
class ShopServiceProvider extends PluginServiceProvider
{
    // Middleware of the public shop pages. They are never page cached, because they show the cart.
    public const PUBLIC_MIDDLEWARE = ['web', 'verso.redirects', 'verso.theme', 'verso.adminbar', 'verso.footer'];

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/shop.php'), 'shop');
        $this->registerSystemSettings('Shop', 'shop', ...$this->systemSettings());

        $this->app->singleton(ShopSettings::class);
        $this->app->singleton(Money::class, fn (Application $app): Money => new Money(
            zeroDecimalCurrencies: array_values(array_map(strval(...), (array) config('shop.zero_decimal_currencies'))),
            locale: str_replace('_', '-', (string) ($app->make(Settings::class)->get('site.locale') ?: 'en')),
        ));
        $this->app->scoped(Cart::class, fn (Application $app): Cart => new Cart(
            session: $app->make('session.store'),
            settings: $app->make(ShopSettings::class),
            sessionKey: (string) config('shop.cart_session_key'),
        ));
        $this->app->singleton(StripeClient::class, fn (): StripeClient => new StripeClient(
            apiUrl: (string) config('shop.stripe.api_url'),
            timeout: (int) config('shop.stripe.timeout'),
            retries: (int) config('shop.stripe.retries'),
            retryDelay: (int) config('shop.stripe.retry_delay'),
        ));
        $this->app->singleton(StripeSignature::class, fn (): StripeSignature => new StripeSignature(
            tolerance: (int) config('shop.stripe.webhook_tolerance'),
        ));
        $this->app->singleton(ShopStylesComposer::class, fn (): ShopStylesComposer => new ShopStylesComposer(
            $this->path('resources/css/shop.css'),
        ));
        $this->app->bind(CancelStalePendingOrders::class, fn (Application $app): CancelStalePendingOrders => new CancelStalePendingOrders(
            changeStatus: $app->make(ChangeOrderStatus::class),
            defaultMinutes: (int) config('shop.pending_orders.cancel_after_minutes'),
            gatewayMinutes: array_map(intval(...), array_filter(
                (array) config('shop.pending_orders.gateway_minutes'),
                fn (mixed $minutes, mixed $gateway): bool => is_string($gateway) && is_numeric($minutes),
                ARRAY_FILTER_USE_BOTH,
            )),
            chunk: (int) config('shop.pending_orders.chunk'),
        ));
        $this->app->singleton(PaymentGateways::class, function (Application $app): PaymentGateways {
            $gateways = new PaymentGateways;
            $gateways->register($app->make(BankTransferGateway::class));
            $gateways->register($app->make(StripeCheckoutGateway::class));

            return $gateways;
        });
    }

    public function boot(): void
    {
        $this->registerContentType(new ContentType(
            name: Product::TYPE,
            label: 'Product',
            pluralLabel: 'Products',
            hasArchive: false,
            routeBase: trim((string) config('shop.routes.product_base'), '/'),
            icon: 'product',
            menuOrder: 25,
            taxonomies: [Product::CATEGORY_TAXONOMY],
        ));
        $this->registerTaxonomy(new Taxonomy(
            name: Product::CATEGORY_TAXONOMY,
            label: 'Product category',
            pluralLabel: 'Product categories',
            hierarchical: true,
            contentTypes: [Product::TYPE],
            routeBase: trim((string) config('shop.routes.category_base'), '/'),
            icon: 'categories',
            menuOrder: 30,
        ));

        $this->registerListColumns();
        $this->reserveSlugs();
        $this->loadPluginMigrations();
        $this->loadPluginViews('shop');
        $this->loadThemeViews();

        $this->registerBlock($this->app->make(ProductBlock::class));
        $this->registerBlock($this->app->make(ProductGridBlock::class));
        $this->registerSection(new SectionTemplate(
            key: 'shop-products',
            label: 'Products',
            description: 'A grid of the newest products from your shop.',
            builder: fn (SectionContent $content, SectionKit $kit): array => [$kit->section([
                $kit->heading($content->heading),
                $content->text !== '' ? $kit->paragraph($content->text) : null,
                $kit->block(ProductGridBlock::NAME, ['limit' => 6, 'columns' => '3', 'sort' => 'newest', 'category' => ProductGridBlock::ALL_CATEGORIES]),
            ], label: $content->heading)],
            icon: 'shop',
            category: 'sales',
            keywords: ['products', 'shop', 'store'],
            plugin: 'shop',
            defaults: new SectionContent(heading: 'Our products'),
        ));

        // Price or stock changes reach the pages that embed the product, by slug or by id.
        Product::saved(fn (Product $product) => $this->app->make(EmbedInvalidator::class)->changed(
            ProductBlock::NAME,
            (string) $product->entry?->slug,
            (string) $product->entry_id,
        ));

        $this->registerRoutes();
        $this->registerPrivatePaths();
        $this->registerAdmin();
        $this->registerEvents();
        $this->registerSchedule();

        View::composer(ThemeManager::NAMESPACE.'::entry-'.Product::TYPE, ProductPageComposer::class);
        View::composer(ThemeManager::NAMESPACE.'::taxonomy-'.Product::CATEGORY_TAXONOMY, ProductCategoryComposer::class);
        View::composer(ThemeManager::NAMESPACE.'::layouts.app', ShopStylesComposer::class);
    }

    protected function registerRoutes(): void
    {
        RateLimiter::for('shop-checkout', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('shop.checkout_throttle')))
            ->by($request->ip()));

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(self::PUBLIC_MIDDLEWARE)->group($this->path('routes/public.php'));

        // Stripe calls this without a session or CSRF token. The signature proves the sender.
        Route::post(trim((string) config('shop.routes.stripe_webhook'), '/'), StripeWebhookController::class)
            ->name('shop.stripe.webhook');

        $this->loadAdminRoutes('routes/admin.php');
    }

    // The cart, checkout, order pages and the webhook belong to one visitor, so stats leave them out.
    protected function registerPrivatePaths(): void
    {
        $this->addFilter(PrivatePathsFilter::class, fn (array $patterns): array => [
            ...$patterns,
            ...array_map(
                fn (string $key): string => '/'.trim((string) config("shop.routes.{$key}"), '/'),
                ['cart', 'checkout', 'stripe_webhook'],
            ),
        ]);
    }

    // Price and stock columns in the admin list of products. The product rows load in one query.
    protected function registerListColumns(): void
    {
        Entry::resolveRelationUsing('shopProduct', fn (Entry $entry): HasOne => $entry->hasOne(Product::class));

        $this->addFilter(AdminEntryColumnsFilter::class, fn (array $columns, ContentType $type): array => $type->name !== Product::TYPE ? $columns : [
            new ListColumn('price', __('shop::Price'), 'money', 'always', ['shopProduct.variants']),
            new ListColumn('stock', __('shop::Stock'), 'number', 'md', ['shopProduct.variants']),
            new ListColumn('sku', __('shop::SKU'), 'text', '2xl', ['shopProduct.variants']),
            ...$columns,
        ]);

        $this->addFilter(AdminEntryRowFilter::class, function (array $row, Entry $entry): array {
            if ($entry->type !== Product::TYPE || ! $entry->relationLoaded('shopProduct')) {
                return $row;
            }

            $product = $entry->getRelation('shopProduct');
            $money = $this->app->make(Money::class);
            $currency = $this->app->make(ShopSettings::class)->currency();

            $row['columns'] = [
                ...(array) ($row['columns'] ?? []),
                'price' => $product instanceof Product ? $this->priceLabel($product, $money, $currency) : null,
                'stock' => $product instanceof Product ? $product->availableStock() : null,
                'sku' => $product instanceof Product ? $product->sku : null,
            ];

            return $row;
        });
    }

    // "€12.00", or "€12.00 to €18.00" when variants have other prices.
    protected function priceLabel(Product $product, Money $money, string $currency): string
    {
        [$low, $high] = $product->priceRange();

        return $low === $high
            ? $money->format($low, $currency)
            : __('shop:::low to :high', ['low' => $money->format($low, $currency), 'high' => $money->format($high, $currency)]);
    }

    protected function registerAdmin(): void
    {
        // Admins always have shop.manage. The shop manager role gives it to people who only run the shop.
        $this->app->make(OptionalRoles::class)->enable(Role::ShopManager);

        $this->app->booted(fn () => $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'shop.orders_to_ship',
            label: 'Orders to ship',
            count: fn (): int => Order::query()->where('status', OrderStatus::Paid->value)->count(),
            order: 15,
            capability: Capability::ManageShop,
            route: 'admin.shop.orders.index',
            parameters: ['status' => OrderStatus::Paid->value],
            icon: 'orders',
            plugin: 'shop',
        )));
    }

    protected function registerEvents(): void
    {
        $this->listen(OrderPlaced::class, [SendOrderEmails::class, 'placed']);
        $this->listen(OrderStatusChanged::class, [SendOrderEmails::class, 'statusChanged']);

        // Stock is shown on cached product pages, so they are refreshed when it changes.
        $flush = fn () => $this->app->make(PageCache::class)->flush();
        $this->listen(OrderPlaced::class, $flush);
        $this->listen(OrderStatusChanged::class, function (OrderStatusChanged $event) use ($flush): void {
            if ($event->to->releasesStock()) {
                $flush();
            }
        });
    }

    // Unpaid orders are cancelled on a schedule, so their stock is not held forever.
    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new CancelPendingOrders)
                ->cron((string) config('shop.pending_orders.schedule'))
                ->name('shop:cancel-pending')
                ->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([CancelPendingOrdersCommand::class]);
        }
    }

    // Pages cannot use the shop, cart or checkout paths.
    protected function reserveSlugs(): void
    {
        $paths = array_map(
            fn (string $key): string => trim(explode('/', trim((string) config("shop.routes.{$key}"), '/'))[0]),
            ['product_base', 'shop', 'cart', 'checkout', 'category_base'],
        );

        config(['verso.content.reserved_slugs' => array_values(array_unique([
            ...(array) config('verso.content.reserved_slugs'),
            ...array_filter($paths),
        ]))]);
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('shop.per_page', $group, 'Products per page', 'Products on one page of the shop.', unit: Unit::Items, min: 1, max: 200, env: 'SHOP_PER_PAGE'),
            new ConfigParameter('shop.grid_max', $group, 'Most products in a grid block', 'Most products one product grid block can show.', unit: Unit::Items, min: 1, max: 200, env: 'SHOP_GRID_MAX'),
            new ConfigParameter('shop.max_quantity', $group, 'Highest quantity per cart line', 'Highest quantity of one product in the cart.', unit: Unit::Items, min: 1, max: 10_000, env: 'SHOP_MAX_QUANTITY'),
            new ConfigParameter('shop.checkout_throttle', $group, 'Checkout requests per minute', 'Checkout requests one visitor can send each minute.', unit: Unit::Requests, min: 1, max: 1000, env: 'SHOP_CHECKOUT_THROTTLE'),
            new ConfigParameter('shop.pending_orders.cancel_after_minutes', $group, 'Cancel unpaid orders after', 'Unpaid orders are cancelled after this time and their stock goes back on sale. 0 never cancels.', unit: Unit::Minutes, min: 0, max: 525_600, env: 'SHOP_CANCEL_PENDING_AFTER'),
            new ConfigParameter('shop.stripe.timeout', $group, 'Stripe time limit', 'How long Verso waits for Stripe.', unit: Unit::Seconds, min: 1, max: 120, env: 'SHOP_STRIPE_TIMEOUT'),
            new ConfigParameter('shop.stripe.retries', $group, 'Stripe retries', 'Extra tries when Stripe cannot be reached.', unit: Unit::Items, min: 0, max: 10, env: 'SHOP_STRIPE_RETRIES'),
            new ConfigParameter('shop.stripe.webhook_tolerance', $group, 'Oldest Stripe webhook', 'Oldest webhook signature accepted.', unit: Unit::Seconds, min: 30, max: 3600, env: 'SHOP_STRIPE_WEBHOOK_TOLERANCE'),
        ];
    }
}

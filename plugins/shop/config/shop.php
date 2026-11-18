<?php

return [

    // Public URL parts. Products live under "/{product_base}/{slug}".
    'routes' => [
        'product_base' => env('SHOP_PRODUCT_BASE', 'shop'),
        'shop' => env('SHOP_PATH', 'shop'),
        'cart' => env('SHOP_CART_PATH', 'cart'),
        'checkout' => env('SHOP_CHECKOUT_PATH', 'checkout'),
        'category_base' => env('SHOP_CATEGORY_BASE', 'product-category'),
        'stripe_webhook' => env('SHOP_STRIPE_WEBHOOK_PATH', 'shop/webhooks/stripe'),
    ],

    // Products per page on the shop page.
    'per_page' => (int) env('SHOP_PER_PAGE', 12),

    // Most products a product grid block can show.
    'grid_max' => (int) env('SHOP_GRID_MAX', 24),

    // Session key that holds the cart.
    'cart_session_key' => env('SHOP_CART_SESSION_KEY', 'shop.cart'),

    // Highest quantity of one product line in the cart.
    'max_quantity' => (int) env('SHOP_MAX_QUANTITY', 99),

    // Checkout requests a visitor can send per minute.
    'checkout_throttle' => (int) env('SHOP_CHECKOUT_THROTTLE', 10),

    // Text put in front of the order id, like "ORD-00042".
    'order_prefix' => env('SHOP_ORDER_PREFIX', 'ORD-'),

    // Digits of the order id in the order number (zeros are added on the left).
    'order_digits' => (int) env('SHOP_ORDER_DIGITS', 5),

    // Unpaid orders are cancelled after this wait and their stock goes back on sale.
    'pending_orders' => [
        // Minutes an order may stay pending. 0 turns the automatic cancel off.
        'cancel_after_minutes' => (int) env('SHOP_CANCEL_PENDING_AFTER', 1440),

        // Longer or shorter waits for single gateways, in minutes. Bank transfers take days.
        'gateway_minutes' => [
            'bank_transfer' => (int) env('SHOP_CANCEL_BANK_TRANSFER_AFTER', 10080),
        ],

        // How often the check runs (a cron expression).
        'schedule' => env('SHOP_CANCEL_PENDING_SCHEDULE', '*/15 * * * *'),

        // Orders loaded at once while cancelling.
        'chunk' => (int) env('SHOP_CANCEL_PENDING_CHUNK', 100),
    ],

    // Currencies the admin can pick from.
    'currencies' => array_values(array_filter(explode(',', (string) env('SHOP_CURRENCIES', 'EUR,USD,GBP,CHF,CAD,AUD,SEK,NOK,DKK,PLN,MXN,BRL,JPY')))),

    // Currencies without minor units (amounts are stored as whole units).
    'zero_decimal_currencies' => ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'],

    // Values used until the shop settings are saved in the admin.
    'defaults' => [
        'currency' => env('SHOP_CURRENCY', 'EUR'),
        'tax_rate' => (float) env('SHOP_TAX_RATE', 0),
        'tax_shipping' => (bool) env('SHOP_TAX_SHIPPING', true),
        'shipping_rate' => (int) env('SHOP_SHIPPING_RATE', 0),
        'free_shipping_over' => env('SHOP_FREE_SHIPPING_OVER') !== null ? (int) env('SHOP_FREE_SHIPPING_OVER') : null,
        'notify_email' => env('SHOP_NOTIFY_EMAIL'),
        'bank_transfer_enabled' => (bool) env('SHOP_BANK_TRANSFER_ENABLED', true),
        'bank_transfer_instructions' => env('SHOP_BANK_TRANSFER_INSTRUCTIONS', 'Please send the total to our bank account and use the order number as the reference.'),
        'stripe_enabled' => (bool) env('SHOP_STRIPE_ENABLED', false),
    ],

    'stripe' => [
        // Keys used when none are saved in the shop settings.
        'secret_key' => env('SHOP_STRIPE_SECRET_KEY'),
        'webhook_secret' => env('SHOP_STRIPE_WEBHOOK_SECRET'),

        // Base URL of the Stripe REST API.
        'api_url' => env('SHOP_STRIPE_API_URL', 'https://api.stripe.com'),

        // Seconds to wait for Stripe before giving up.
        'timeout' => (int) env('SHOP_STRIPE_TIMEOUT', 15),

        // Extra tries when Stripe cannot be reached.
        'retries' => (int) env('SHOP_STRIPE_RETRIES', 2),

        // Milliseconds to wait between tries.
        'retry_delay' => (int) env('SHOP_STRIPE_RETRY_DELAY', 300),

        // Oldest webhook signature accepted, in seconds.
        'webhook_tolerance' => (int) env('SHOP_STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

];

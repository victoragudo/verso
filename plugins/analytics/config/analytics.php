<?php

return [

    // Turn the beacon on public pages on or off.
    'enabled' => (bool) env('ANALYTICS_ENABLED', true),

    // Path that receives the beacon. It must not clash with a page slug.
    'endpoint' => env('ANALYTICS_ENDPOINT', '_verso/collect'),

    // Beacon requests allowed per minute for one IP address.
    'rate_limit' => (int) env('ANALYTICS_RATE_LIMIT', 120),

    // Capability needed to see the stats and change the analytics settings.
    'capability' => env('ANALYTICS_CAPABILITY', 'analytics.view'),

    // Longest page path that is recorded, in characters.
    'max_path_length' => (int) env('ANALYTICS_MAX_PATH_LENGTH', 255),

    // Screen widths (in CSS pixels) where the tablet and desktop buckets start.
    'breakpoints' => [
        'tablet' => (int) env('ANALYTICS_TABLET_WIDTH', 768),
        'desktop' => (int) env('ANALYTICS_DESKTOP_WIDTH', 1024),
    ],

    // Default values of the settings shown on the analytics page.
    'defaults' => [
        'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 30),
        // Logged-in users with one of these roles get no beacon (comma separated role names).
        'ignore_roles' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('ANALYTICS_IGNORE_ROLES', 'admin'))))),
    ],

    // Smallest and largest number of days raw events can be kept.
    'retention_limits' => [
        'min' => (int) env('ANALYTICS_RETENTION_MIN', 1),
        'max' => (int) env('ANALYTICS_RETENTION_MAX', 365),
    ],

    // Date ranges (in days) offered on the analytics page, and the one shown first.
    'ranges' => [7, 30, 90],
    'default_range' => (int) env('ANALYTICS_DEFAULT_RANGE', 30),

    // Rows in the top pages and top referrers lists.
    'top_limit' => (int) env('ANALYTICS_TOP_LIMIT', 10),

    // Time of day (site time zone) when yesterday's events are rolled up.
    'rollup_at' => env('ANALYTICS_ROLLUP_AT', '00:15'),

    // Rows written per insert when rolling up a day.
    'rollup_chunk' => (int) env('ANALYTICS_ROLLUP_CHUNK', 500),

    // Private paths that are never counted. "/admin" also covers everything under it, "*" is a wildcard.
    // Plugins add their own with the PrivatePathsFilter. ANALYTICS_EXCLUDE_PATHS adds more (comma separated).
    'exclude_paths' => [
        '/admin', '/api', '/install', '/preview', '/*/preview', '/account', '/settings', '/dashboard',
        '/login', '/logout', '/register', '/forgot-password', '/reset-password', '/two-factor-challenge',
        '/email', '/verify-email', '/confirm-password', '/user', '/invitation', '/_verso',
        ...array_filter(array_map(trim(...), explode(',', (string) env('ANALYTICS_EXCLUDE_PATHS', '')))),
    ],

    // Pages opened with one of these query keys are private links, so they are never counted.
    'sensitive_query_keys' => array_filter(array_map(trim(...), explode(',', (string) env(
        'ANALYTICS_SENSITIVE_QUERY_KEYS',
        'token,signature,expires,key,code,email,password,secret,session,auth,reset,order,payment_intent,session_id',
    )))),

    // A path part this long made of letters and digits (with both) is treated as a secret token.
    'token_min_length' => (int) env('ANALYTICS_TOKEN_MIN_LENGTH', 20),

    // Visits from user agents that contain one of these words are ignored (case does not matter).
    'bots' => [
        'bot', 'crawl', 'spider', 'slurp', 'archiver', 'headless', 'lighthouse', 'pagespeed',
        'preview', 'monitor', 'uptime', 'pingdom', 'facebookexternalhit', 'embedly', 'whatsapp',
        'curl', 'wget', 'python', 'java/', 'go-http-client', 'okhttp', 'axios', 'node-fetch',
        'httpclient', 'scrapy', 'phantomjs', 'selenium', 'playwright', 'puppeteer',
    ],

];

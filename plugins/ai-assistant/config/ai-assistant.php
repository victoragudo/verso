<?php

return [

    'anthropic' => [
        // Messages API endpoint.
        'url' => env('AI_ASSISTANT_ANTHROPIC_URL', 'https://api.anthropic.com/v1/messages'),

        // Value of the anthropic-version header.
        'version' => env('AI_ASSISTANT_ANTHROPIC_VERSION', '2026-10-21'),

        // Model used when the settings page has no model yet.
        'default_model' => env('AI_ASSISTANT_DEFAULT_MODEL', 'claude-sonnet-5-5'),

        // How hard the model thinks (low, medium, high). Empty uses the model default.
        'effort' => env('AI_ASSISTANT_EFFORT', 'low'),

        // Server side fallback when the model declines a request. Empty turns it off.
        'fallbacks' => env('AI_ASSISTANT_FALLBACKS', 'default'),

        // Beta header that turns on server side fallbacks.
        'fallbacks_beta' => env('AI_ASSISTANT_FALLBACKS_BETA', 'server-side-fallback-2026-07-01'),

        // Models that accept server side fallbacks. Other models are called without them.
        'fallback_models' => array_values(array_filter(explode(',', (string) env('AI_ASSISTANT_FALLBACK_MODELS', 'claude-sonnet-5-5,claude-opus-5-5,claude-opus-5,claude-fable-5-1,claude-fable-5-2')))),
    ],

    'http' => [
        // Seconds to wait for an answer from the provider.
        'timeout' => (int) env('AI_ASSISTANT_TIMEOUT', 60),

        // Seconds to wait for the connection to open.
        'connect_timeout' => (int) env('AI_ASSISTANT_CONNECT_TIMEOUT', 10),

        // Extra tries after a failed connection, a 429 or a 5xx answer.
        'retries' => (int) env('AI_ASSISTANT_RETRIES', 2),

        // Milliseconds to wait between tries.
        'retry_delay' => (int) env('AI_ASSISTANT_RETRY_DELAY', 500),
    ],

    'defaults' => [
        // Most tokens one answer can use, when the settings page has no value yet.
        'max_tokens' => (int) env('AI_ASSISTANT_MAX_TOKENS', 4000),

        // Requests one user can make in each rate limit window, when the settings page has no value yet.
        'rate_limit' => (int) env('AI_ASSISTANT_RATE_LIMIT', 30),
    ],

    'limits' => [
        // Seconds in one rate limit window.
        'rate_limit_window' => (int) env('AI_ASSISTANT_RATE_LIMIT_WINDOW', 3600),

        // Highest max tokens value the settings page accepts.
        'max_tokens_ceiling' => (int) env('AI_ASSISTANT_MAX_TOKENS_CEILING', 64000),

        // Longest text (in characters) sent for one action.
        'max_input_chars' => (int) env('AI_ASSISTANT_MAX_INPUT_CHARS', 60000),

        // Biggest image (in bytes) sent for alt text.
        'max_image_bytes' => (int) env('AI_ASSISTANT_MAX_IMAGE_BYTES', 5 * 1024 * 1024),

        // Most text pieces sent in one translation.
        'max_translation_segments' => (int) env('AI_ASSISTANT_MAX_TRANSLATION_SEGMENTS', 500),

        // Most text pieces of one section written by the page editor helper.
        'max_section_pieces' => (int) env('AI_ASSISTANT_MAX_SECTION_PIECES', 60),

        // Longest business description (in characters) the helper keeps.
        'max_business_chars' => (int) env('AI_ASSISTANT_MAX_BUSINESS_CHARS', 1000),

        // Most pictures of one section described in one go by the page editor helper.
        'max_section_images' => (int) env('AI_ASSISTANT_MAX_SECTION_IMAGES', 12),
    ],

    'targets' => [
        // Words the model aims for in an excerpt.
        'excerpt_words' => (int) env('AI_ASSISTANT_EXCERPT_WORDS', 40),

        // Characters the model aims for in the SEO title and meta description.
        'seo_title_chars' => (int) env('AI_ASSISTANT_SEO_TITLE_CHARS', 60),
        'seo_description_chars' => (int) env('AI_ASSISTANT_SEO_DESCRIPTION_CHARS', 155),

        // Characters the model aims for in image alt text.
        'alt_chars' => (int) env('AI_ASSISTANT_ALT_CHARS', 125),

        // How many headlines the helper suggests, and their longest length in characters.
        'headline_count' => (int) env('AI_ASSISTANT_HEADLINE_COUNT', 5),
        'headline_chars' => (int) env('AI_ASSISTANT_HEADLINE_CHARS', 80),
    ],

    'translation' => [
        // Block types whose content is translated.
        'content_types' => ['paragraph', 'heading', 'list', 'quote'],

        // Block attributes that hold text to translate.
        'attrs' => ['alt', 'caption', 'label', 'citation'],
    ],

    'section_text' => [
        // Block types whose content the page editor helper writes.
        'content_types' => ['paragraph', 'heading', 'list', 'quote'],

        // Block attributes it writes. Image descriptions have their own action.
        'attrs' => ['label', 'caption', 'citation'],
    ],

    // Image types the vision API accepts.
    'image_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

    // Languages offered in the translate menu (code => name).
    'locales' => [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'ca' => 'Catalan',
        'pl' => 'Polish',
        'sv' => 'Swedish',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
    ],

    // Days shown on the usage page.
    'usage_days' => (int) env('AI_ASSISTANT_USAGE_DAYS', 30),

];

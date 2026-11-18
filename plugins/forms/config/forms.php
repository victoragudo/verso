<?php

// Defaults of the Forms plugin. The admin can change many of them on the forms settings page.
return [

    // Capability needed to build forms and read submissions.
    // Capability to build forms, and capability to read the messages (they hold personal data).
    'capability' => env('FORMS_CAPABILITY', 'forms.manage'),
    'messages_capability' => env('FORMS_MESSAGES_CAPABILITY', 'forms.messages'),

    // Rows shown on one admin page.
    'per_page' => (int) env('FORMS_PER_PAGE', 25),

    // Most fields one form may have.
    'max_fields' => (int) env('FORMS_MAX_FIELDS', 40),

    // Most options one select, radio or checkbox field may have.
    'max_options' => (int) env('FORMS_MAX_OPTIONS', 50),

    // Longest text accepted by a text field and by a textarea when the form does not set a limit.
    'text_max' => (int) env('FORMS_TEXT_MAX', 255),
    'textarea_max' => (int) env('FORMS_TEXTAREA_MAX', 5000),

    // File uploads: the largest size a form may allow (KB), the default size, and the file types a form may allow.
    'upload_max_kb' => (int) env('FORMS_UPLOAD_MAX_KB', 10240),
    'upload_default_kb' => (int) env('FORMS_UPLOAD_DEFAULT_KB', 2048),
    'upload_types' => array_values(array_filter(explode(',', (string) env('FORMS_UPLOAD_TYPES', 'pdf,jpg,jpeg,png,webp,gif,txt,csv,doc,docx,odt,xls,xlsx,zip')))),

    // Disk and folder for uploaded files. Use a private disk: files are only served to the admin.
    'disk' => env('FORMS_DISK', 'local'),
    'folder' => env('FORMS_FOLDER', 'forms'),

    // Days a submission is kept. 0 keeps submissions forever. The admin can change it.
    'retention_days' => (int) env('FORMS_RETENTION_DAYS', 365),

    // When old submissions are deleted (a cron expression).
    'prune_schedule' => env('FORMS_PRUNE_SCHEDULE', '15 3 * * *'),

    // Queue for notification emails. Empty uses the default queue.
    'queue' => env('FORMS_QUEUE', ''),

    // Spam protection without third parties.
    'spam' => [
        // Hidden field that only bots fill in.
        'honeypot_field' => env('FORMS_HONEYPOT_FIELD', 'company_website'),

        // A form sent faster than this (seconds after the visitor started typing) is treated as a bot.
        'min_seconds' => (int) env('FORMS_MIN_SECONDS', 3),

        // Messages one visitor (by IP hash) can send per window, and the window in minutes.
        'rate_limit' => (int) env('FORMS_RATE_LIMIT', 5),
        'rate_limit_minutes' => (int) env('FORMS_RATE_LIMIT_MINUTES', 10),

        // A message with this many links or more is marked as spam. 0 turns the check off.
        'max_links' => (int) env('FORMS_MAX_LINKS', 3),

        // Words that mark a message as spam (comma separated). The admin can change the list.
        'blocked_words' => env('FORMS_BLOCKED_WORDS', 'viagra,casino bonus,crypto giveaway,seo backlinks'),

        // Keep messages caught by the honeypot or the time trap as spam (true) or drop them (false).
        'keep_bot_messages' => (bool) env('FORMS_KEEP_BOT_MESSAGES', false),
    ],

    // Optional captcha services. Keys are saved on the settings page.
    'captcha' => [
        'timeout' => (int) env('FORMS_CAPTCHA_TIMEOUT', 5),
        'turnstile' => [
            'script' => env('FORMS_TURNSTILE_SCRIPT', 'https://challenges.cloudflare.com/turnstile/v0/api.js'),
            'verify_url' => env('FORMS_TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
            'response_field' => 'cf-turnstile-response',
            'widget_class' => 'cf-turnstile',
        ],
        'hcaptcha' => [
            'script' => env('FORMS_HCAPTCHA_SCRIPT', 'https://js.hcaptcha.com/1/api.js'),
            'verify_url' => env('FORMS_HCAPTCHA_VERIFY_URL', 'https://api.hcaptcha.com/siteverify'),
            'response_field' => 'h-captcha-response',
            'widget_class' => 'h-captcha',
        ],
    ],

];

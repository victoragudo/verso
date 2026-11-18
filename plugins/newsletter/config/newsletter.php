<?php

// Defaults of the Newsletter plugin. The admin can change most of them on the newsletter settings page.
return [

    // Emails sent by one queued job.
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 50),

    // Seconds between two batches, to stay under the limits of the mail provider.
    'batch_delay' => (int) env('NEWSLETTER_BATCH_DELAY', 60),

    // Sender of campaigns. Empty uses the mail.from config.
    'from_address' => env('NEWSLETTER_FROM_ADDRESS', ''),
    'from_name' => env('NEWSLETTER_FROM_NAME', ''),

    // Open and click tracking for new campaigns. Off by default to respect privacy.
    'track_opens' => (bool) env('NEWSLETTER_TRACK_OPENS', false),
    'track_clicks' => (bool) env('NEWSLETTER_TRACK_CLICKS', false),

    // Hours a confirmation link stays valid.
    'confirm_hours' => (int) env('NEWSLETTER_CONFIRM_HOURS', 72),

    // Signups allowed per minute from one IP address.
    'signup_rate_limit' => (int) env('NEWSLETTER_SIGNUP_RATE_LIMIT', 5),

    // Rows shown on one admin page.
    'per_page' => (int) env('NEWSLETTER_PER_PAGE', 25),

    // Largest CSV file accepted by the import, in kilobytes.
    'import_max_kb' => (int) env('NEWSLETTER_IMPORT_MAX_KB', 5120),

    // Queue used for sending campaigns. Empty uses the default queue.
    'queue' => env('NEWSLETTER_QUEUE', ''),

    // How often a heartbeat job is put on the queue (a cron expression). A worker that runs it proves campaigns can be sent.
    'heartbeat_schedule' => env('NEWSLETTER_HEARTBEAT_SCHEDULE', '*/5 * * * *'),

    // Minutes without a heartbeat before the admin shows a "no queue worker" notice.
    'heartbeat_stale_minutes' => (int) env('NEWSLETTER_HEARTBEAT_STALE_MINUTES', 15),

];

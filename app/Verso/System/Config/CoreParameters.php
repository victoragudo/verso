<?php

namespace App\Verso\System\Config;

use App\Verso\System\Config\ApplyAction as Apply;
use App\Verso\System\Config\ParameterType as Type;

// The settings of the Verso core shown in System settings. Plugins add theirs from their providers.
class CoreParameters
{
    public const UPLOADS = 'uploads';

    public const PERFORMANCE = 'performance';

    public const SECURITY = 'security';

    public const EMAIL = 'email';

    public const QUEUES = 'queues';

    public const API = 'api';

    // Biggest size any upload setting may take, in kilobytes (4 GB).
    protected const MAX_KB = 4_194_304;

    protected const MONTH_SECONDS = 2_592_000;

    protected const MAIL_SERVICES = [
        'smtp' => ['label' => 'SMTP server'],
        'sendmail' => ['label' => 'Sendmail'],
        'log' => ['label' => 'Log only'],
    ];

    public function register(ConfigRegistry $registry): void
    {
        foreach ($this->groups() as $group) {
            $registry->addGroup($group);
        }

        $registry->add(...$this->uploads(), ...$this->performance(), ...$this->security(), ...$this->email(), ...$this->queues(), ...$this->api());
    }

    /** @return list<ConfigGroup> */
    public function groups(): array
    {
        return [
            new ConfigGroup(self::UPLOADS, 'Uploads and media', 'File sizes, file types and image processing.', 'media', 10),
            new ConfigGroup(self::PERFORMANCE, 'Performance and cache', 'How long pages and blocks stay cached, and room for heavy tasks.', 'dashboard', 20),
            new ConfigGroup(self::SECURITY, 'Security and limits', 'Spam protection, content limits and links that expire.', 'shield', 30),
            new ConfigGroup(self::EMAIL, 'Email', 'How Verso sends email.', 'newsletter', 40),
            new ConfigGroup(self::QUEUES, 'Queues and jobs', 'Time limits and retries of background work like imports and updates.', 'clock', 50),
            new ConfigGroup(self::API, 'API', 'Limits of the public JSON API.', 'api', 60),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function uploads(): array
    {
        $group = self::UPLOADS;
        $uploads = ServerLimit::uploads();

        return [
            new ConfigParameter('verso.media.max_upload_kb', $group, 'Largest media upload', 'Biggest file people can add to the media library.', unit: Unit::Kilobytes, min: 1, max: self::MAX_KB, env: 'VERSO_MEDIA_MAX_UPLOAD_KB', limits: $uploads),
            new ConfigParameter('verso.media.allowed_mimes', $group, 'Allowed media file types', 'MIME types people may upload, separated by commas, like image/jpeg, application/pdf.', Type::TextList, env: 'VERSO_MEDIA_ALLOWED_MIMES'),
            new ConfigParameter('verso.media.per_page', $group, 'Items per page in the media library', 'How many files the library and the picker load at once.', unit: Unit::Items, min: 10, max: 200, env: 'VERSO_MEDIA_PER_PAGE'),
            new ConfigParameter('verso.users.profile.avatar_max_kb', $group, 'Largest profile photo', 'Biggest photo people can upload on their profile. The media upload limit still applies.', unit: Unit::Kilobytes, min: 16, max: self::MAX_KB, env: 'VERSO_PROFILE_AVATAR_MAX_KB', limits: $uploads),
            new ConfigParameter('verso.media.caption_max_length', $group, 'Longest caption', 'Longest caption a file can have.', unit: Unit::Characters, min: 100, max: 20000, env: 'VERSO_MEDIA_CAPTION_MAX_LENGTH'),
            new ConfigParameter('verso.media.images.max_dimension', $group, 'Largest resized image side', 'Largest width or height an image URL may ask for. Bigger images need more memory.', unit: Unit::Pixels, min: 320, max: 10000, env: 'VERSO_IMAGE_MAX_DIMENSION', limits: [ServerLimit::MemoryLimit]),
            new ConfigParameter('verso.media.images.max_image_size', $group, 'Largest image area', 'Largest width times height Glide will create. 16777216 is 4096 x 4096.', unit: Unit::Pixels, min: 1_000_000, max: 100_000_000, env: 'VERSO_IMAGE_MAX_SIZE', limits: [ServerLimit::MemoryLimit]),
            new ConfigParameter('verso.media.images.default_quality', $group, 'Image quality', 'Quality of resized images when the URL gives none. Lower is smaller and faster.', unit: Unit::Percent, min: 10, max: 100, env: 'VERSO_IMAGE_QUALITY'),
            new ConfigParameter('verso.media.images.default_format', $group, 'Image format', 'Format of resized images when the URL gives none.', Type::Choice, options: ['webp' => 'WebP', 'avif' => 'AVIF', 'jpg' => 'JPEG', 'png' => 'PNG'], env: 'VERSO_IMAGE_FORMAT'),
            new ConfigParameter('verso.media.images.driver', $group, 'Image library', 'PHP extension that resizes images. Imagick must be installed to use it.', Type::Choice, options: ['gd' => 'GD', 'imagick' => 'Imagick'], env: 'VERSO_IMAGE_DRIVER'),
            new ConfigParameter('verso.media.images.thumbnail_size', $group, 'Thumbnail size', 'Size of the square previews in the media library.', unit: Unit::Pixels, min: 100, max: 1200, env: 'VERSO_IMAGE_THUMBNAIL_SIZE'),
            new ConfigParameter('verso.media.images.browser_cache_seconds', $group, 'Browser cache for images', 'How long browsers keep a resized image.', unit: Unit::Seconds, min: 0, max: 31_536_000, env: 'VERSO_IMAGE_BROWSER_CACHE'),
            new ConfigParameter('verso.importer.max_upload_kb', $group, 'Largest WordPress export file', 'Biggest export file the importer accepts.', unit: Unit::Kilobytes, min: 1, max: self::MAX_KB, env: 'VERSO_IMPORT_MAX_UPLOAD_KB', limits: $uploads),
            new ConfigParameter('verso.importer.max_download_bytes', $group, 'Largest imported attachment', 'Biggest image or file the importer downloads from the old site.', unit: Unit::Bytes, min: 1_048_576, max: 2_147_483_648, env: 'VERSO_IMPORT_MAX_DOWNLOAD_BYTES'),
            new ConfigParameter('verso.extensions.max_upload_kb', $group, 'Largest plugin or theme zip', 'Biggest zip the Extensions page accepts.', unit: Unit::Kilobytes, min: 64, max: 1_048_576, env: 'VERSO_EXTENSIONS_MAX_UPLOAD_KB', limits: $uploads),
            new ConfigParameter('verso.extensions.max_unpacked_kb', $group, 'Largest unpacked plugin or theme', 'Size of all files once unpacked. It stops zip bombs.', unit: Unit::Kilobytes, min: 1024, max: self::MAX_KB, env: 'VERSO_EXTENSIONS_MAX_UNPACKED_KB'),
            new ConfigParameter('verso.extensions.max_files', $group, 'Most files in a plugin or theme', 'Most files one package may hold.', unit: Unit::Items, min: 10, max: 100_000, env: 'VERSO_EXTENSIONS_MAX_FILES'),
            new ConfigParameter('verso.fields.import_max_kb', $group, 'Largest custom fields file', 'Biggest JSON file the custom fields import accepts.', unit: Unit::Kilobytes, min: 16, max: 102_400, env: 'VERSO_FIELDS_IMPORT_MAX_KB', limits: $uploads),
            new ConfigParameter('verso.site_parts.import_max_kb', $group, 'Largest Site Editor file', 'Biggest customization file the Site Editor imports.', unit: Unit::Kilobytes, min: 16, max: 102_400, env: 'VERSO_SITE_EDITOR_IMPORT_MAX_KB', limits: $uploads),
            new ConfigParameter('verso.system.server.upload_overhead_kb', $group, 'Room for other form fields', 'Part of PHP post_max_size kept for the other fields sent with a file.', unit: Unit::Kilobytes, min: 16, max: 10_240, env: 'VERSO_UPLOAD_OVERHEAD_KB'),
            new ConfigParameter('verso.system.server.web_server_body_mb', $group, 'Web server body limit', 'The nginx client_max_body_size or Apache LimitRequestBody of this site, if you know it. PHP cannot read it. 0 means unknown.', unit: Unit::Megabytes, min: 0, max: 102_400, env: 'VERSO_WEB_SERVER_BODY_MB'),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function performance(): array
    {
        $group = self::PERFORMANCE;

        return [
            new ConfigParameter('verso.page_cache.enabled', $group, 'Page cache', 'Keep full pages for visitors who are not signed in. The fastest option.', Type::Boolean, env: 'VERSO_PAGE_CACHE', apply: [Apply::FlushPageCache]),
            new ConfigParameter('verso.page_cache.ttl', $group, 'Page cache lifetime', 'How long a cached page is kept. Pages are also cleared when content changes.', unit: Unit::Seconds, min: 0, max: self::MONTH_SECONDS, env: 'VERSO_PAGE_CACHE_TTL', apply: [Apply::FlushPageCache]),
            new ConfigParameter('verso.block_cache.enabled', $group, 'Block cache', 'Keep the HTML of core blocks for each entry.', Type::Boolean, env: 'VERSO_BLOCK_CACHE', apply: [Apply::FlushBlockCache]),
            new ConfigParameter('verso.block_cache.ttl', $group, 'Block cache lifetime', 'How long the HTML of one entry is kept.', unit: Unit::Seconds, min: 60, max: self::MONTH_SECONDS, env: 'VERSO_BLOCK_CACHE_TTL', apply: [Apply::FlushBlockCache]),
            new ConfigParameter('verso.menus.cache_ttl', $group, 'Menu cache lifetime', 'How long built menus are kept. 0 turns the menu cache off.', unit: Unit::Seconds, min: 0, max: self::MONTH_SECONDS, env: 'VERSO_MENU_CACHE_TTL'),
            new ConfigParameter('verso.sitemap.cache_ttl', $group, 'Sitemap cache lifetime', 'How long a generated sitemap is kept.', unit: Unit::Seconds, min: 0, max: self::MONTH_SECONDS, env: 'VERSO_SITEMAP_CACHE_TTL'),
            new ConfigParameter('verso.sitemap.max_urls', $group, 'Addresses per sitemap file', 'Bigger lists are split in pages. The sitemap protocol allows 50000.', unit: Unit::Items, min: 100, max: 50_000, env: 'VERSO_SITEMAP_MAX_URLS'),
            new ConfigParameter('verso.themes.feed_items', $group, 'Items in the RSS feed', 'Most entries listed in the feed.', unit: Unit::Items, min: 1, max: 200, env: 'VERSO_FEED_ITEMS'),
            new ConfigParameter('verso.admin.per_page', $group, 'Rows per page in the admin', 'Rows in admin lists like entries and users.', unit: Unit::Items, min: 5, max: 200, env: 'VERSO_ADMIN_PER_PAGE'),
            new ConfigParameter('verso.system.heavy_tasks.memory_mb', $group, 'Memory for heavy tasks', 'Imports, image work, updates and exports may raise PHP memory_limit up to this value, when PHP allows it.', unit: Unit::Megabytes, min: 64, max: 16_384, env: 'VERSO_HEAVY_TASK_MEMORY_MB', limits: [ServerLimit::MemoryLimit]),
            new ConfigParameter('verso.system.heavy_tasks.time_limit', $group, 'Time for heavy tasks', 'Heavy tasks may run this long, when PHP allows a longer time limit.', unit: Unit::Seconds, min: 30, max: 86_400, env: 'VERSO_HEAVY_TASK_TIME_LIMIT', limits: [ServerLimit::MaxExecutionTime]),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function security(): array
    {
        $group = self::SECURITY;

        return [
            new ConfigParameter('verso.comments.throttle_attempts', $group, 'Comments per visitor', 'Comments one visitor can send in each window.', unit: Unit::Requests, min: 1, max: 1000, env: 'VERSO_COMMENTS_THROTTLE_ATTEMPTS'),
            new ConfigParameter('verso.comments.throttle_minutes', $group, 'Comment window', 'Length of the window for the limit above.', unit: Unit::Minutes, min: 1, max: 1440, env: 'VERSO_COMMENTS_THROTTLE_MINUTES'),
            new ConfigParameter('verso.comments.min_seconds', $group, 'Fastest human comment', 'A comment form sent faster than this is treated as a bot.', unit: Unit::Seconds, min: 0, max: 600, env: 'VERSO_COMMENTS_MIN_SECONDS'),
            new ConfigParameter('verso.comments.max_seconds', $group, 'Oldest comment form', 'A form older than this must be loaded again.', unit: Unit::Seconds, min: 60, max: self::MONTH_SECONDS, env: 'VERSO_COMMENTS_MAX_SECONDS'),
            new ConfigParameter('verso.comments.spam_links', $group, 'Links that mark a comment as spam', 'A comment with this many links or more goes straight to spam.', unit: Unit::Items, min: 1, max: 100, env: 'VERSO_COMMENTS_SPAM_LINKS'),
            new ConfigParameter('verso.comments.max_length', $group, 'Longest comment', 'Longest comment a visitor can send.', unit: Unit::Characters, min: 100, max: 100_000, env: 'VERSO_COMMENTS_MAX_LENGTH'),
            new ConfigParameter('verso.users.profile.bio_max', $group, 'Longest profile bio', 'Longest bio people can write on their profile.', unit: Unit::Characters, min: 50, max: 20_000, env: 'VERSO_PROFILE_BIO_MAX'),
            new ConfigParameter('verso.users.profile.social_links_max', $group, 'Social links per profile', 'Most social profile links one person can add.', unit: Unit::Items, min: 1, max: 50, env: 'VERSO_PROFILE_SOCIAL_LINKS_MAX'),
            new ConfigParameter('verso.users.invitation_expires_hours', $group, 'Invitation links expire after', 'How long an invitation link to join the site stays valid.', unit: Unit::Hours, min: 1, max: 2160, env: 'VERSO_INVITATION_EXPIRES_HOURS'),
            new ConfigParameter('verso.editor.max_blocks', $group, 'Most blocks in one entry', 'Counts nested blocks too.', unit: Unit::Items, min: 100, max: 20_000, env: 'VERSO_EDITOR_MAX_BLOCKS'),
            new ConfigParameter('verso.editor.max_depth', $group, 'Deepest block nesting', 'How deep blocks can sit inside each other.', unit: Unit::Items, min: 1, max: 10, env: 'VERSO_EDITOR_MAX_DEPTH'),
            new ConfigParameter('verso.editor.custom_css_max', $group, 'Longest custom CSS of a block', 'Longest custom CSS one block can hold.', unit: Unit::Characters, min: 100, max: 100_000, env: 'VERSO_EDITOR_CUSTOM_CSS_MAX'),
            new ConfigParameter('verso.builder.custom_code.css_max', $group, 'Longest site or page CSS', 'Longest custom CSS for the site or one page.', unit: Unit::Characters, min: 1000, max: 1_000_000, env: 'VERSO_CUSTOM_CSS_MAX'),
            new ConfigParameter('verso.builder.custom_code.js_max', $group, 'Longest site or page JavaScript', 'Longest custom JavaScript for the site or one page.', unit: Unit::Characters, min: 1000, max: 1_000_000, env: 'VERSO_CUSTOM_JS_MAX'),
            new ConfigParameter('verso.themes.search_max_length', $group, 'Longest search text', 'Longest text accepted by the site search.', unit: Unit::Characters, min: 10, max: 1000, env: 'VERSO_SEARCH_MAX_LENGTH'),
            new ConfigParameter('verso.extensions.staging_ttl_minutes', $group, 'Uploaded package waits', 'How long an uploaded plugin or theme waits for your consent before it is deleted.', unit: Unit::Minutes, min: 5, max: 1440, env: 'VERSO_EXTENSIONS_STAGING_TTL'),
            new ConfigParameter('verso.extensions.verification.sandbox.timeout', $group, 'Plugin and theme check time limit', 'How long the safety check of a plugin or theme may run.', unit: Unit::Seconds, min: 10, max: 600, env: 'VERSO_VERIFY_SANDBOX_TIMEOUT'),
            new ConfigParameter('verso.extensions.verification.themes.screenshot_max_kb', $group, 'Largest theme screenshot', 'Biggest screenshot file a theme may ship. Bigger ones are reported by the theme check.', unit: Unit::Kilobytes, min: 16, max: 20_480, env: 'VERSO_VERIFY_THEME_SCREENSHOT_MAX_KB'),
            new ConfigParameter('verso.extensions.verification.themes.screenshot_max_width', $group, 'Widest theme screenshot', 'Widest screenshot a theme may ship.', unit: Unit::Pixels, min: 320, max: 10_000, env: 'VERSO_VERIFY_THEME_SCREENSHOT_MAX_WIDTH'),
            new ConfigParameter('verso.extensions.verification.themes.screenshot_max_height', $group, 'Tallest theme screenshot', 'Tallest screenshot a theme may ship.', unit: Unit::Pixels, min: 240, max: 10_000, env: 'VERSO_VERIFY_THEME_SCREENSHOT_MAX_HEIGHT'),
            new ConfigParameter('verso.extensions.verification.themes.preview_posts', $group, 'Demo posts in the theme preview', 'Posts written to the temporary database before a new theme renders its home, a post and an archive.', unit: Unit::Items, min: 1, max: 50, env: 'VERSO_VERIFY_THEME_PREVIEW_POSTS'),
            new ConfigParameter('verso.extensions.verification.themes.preview_max_kb', $group, 'Preview HTML kept per page', 'HTML of each rendered preview page kept with the theme report. 0 keeps none.', unit: Unit::Kilobytes, min: 0, max: 2048, env: 'VERSO_VERIFY_THEME_PREVIEW_MAX_KB'),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function email(): array
    {
        $group = self::EMAIL;
        $restart = [Apply::RestartQueue];

        return [
            new ConfigParameter('mail.default', $group, 'Mail service', 'How email leaves the server. "Log only" writes email to the log file and sends nothing.', Type::Choice, options: $this->optionLabels(self::MAIL_SERVICES), env: 'MAIL_MAILER', apply: $restart),
            new ConfigParameter('mail.from.address', $group, 'Sender address', 'Address that email comes from.', Type::Text, min: 3, max: 190, env: 'MAIL_FROM_ADDRESS', apply: $restart, format: ConfigParameter::FORMAT_EMAIL),
            new ConfigParameter('mail.from.name', $group, 'Sender name', 'Name people see next to the sender address.', Type::Text, min: 1, max: 120, env: 'MAIL_FROM_NAME', apply: $restart),
            new ConfigParameter('mail.mailers.smtp.host', $group, 'SMTP host', 'Name or IP address of the SMTP server.', Type::Text, min: 1, max: 190, env: 'MAIL_HOST', apply: $restart, format: ConfigParameter::FORMAT_HOST),
            new ConfigParameter('mail.mailers.smtp.port', $group, 'SMTP port', 'Usually 587 (TLS) or 465 (SSL).', unit: Unit::None, min: 1, max: 65_535, env: 'MAIL_PORT', apply: $restart),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function queues(): array
    {
        $group = self::QUEUES;
        $restart = [Apply::RestartQueue];

        return [
            new ConfigParameter('queue.connections.database.retry_after', $group, 'Retry a stuck job after', 'A job still running after this time is given to another worker. Keep it longer than the longest job.', unit: Unit::Seconds, min: 30, max: 86_400, env: 'DB_QUEUE_RETRY_AFTER', apply: $restart),
            new ConfigParameter('verso.importer.job_timeout', $group, 'Import time limit', 'How long one WordPress import may run.', unit: Unit::Seconds, min: 60, max: 86_400, env: 'VERSO_IMPORT_JOB_TIMEOUT', apply: $restart, limits: [ServerLimit::MaxExecutionTime]),
            new ConfigParameter('verso.importer.download_timeout', $group, 'Import download time limit', 'How long the importer waits for one file from the old site.', unit: Unit::Seconds, min: 1, max: 600, env: 'VERSO_IMPORT_DOWNLOAD_TIMEOUT', apply: $restart),
            new ConfigParameter('verso.importer.download_retries', $group, 'Import download retries', 'Extra tries when a download fails.', unit: Unit::Items, min: 0, max: 10, env: 'VERSO_IMPORT_DOWNLOAD_RETRIES', apply: $restart),
            new ConfigParameter('verso.importer.progress_every', $group, 'Import progress step', 'Save import progress every this many items.', unit: Unit::Items, min: 1, max: 1000, env: 'VERSO_IMPORT_PROGRESS_EVERY', apply: $restart),
            new ConfigParameter('verso.updates.download_timeout', $group, 'Update download time limit', 'How long the updater waits for a release package.', unit: Unit::Seconds, min: 10, max: 3600, env: 'VERSO_UPDATES_DOWNLOAD_TIMEOUT'),
            new ConfigParameter('verso.updates.max_download_kb', $group, 'Largest update package', 'Biggest release package the updater downloads.', unit: Unit::Kilobytes, min: 10_240, max: 2_097_152, env: 'VERSO_UPDATES_MAX_DOWNLOAD_KB'),
            new ConfigParameter('verso.updates.command_timeout', $group, 'Update step time limit', 'How long each update step (migrate, cache clear) may run.', unit: Unit::Seconds, min: 30, max: 7200, env: 'VERSO_UPDATES_COMMAND_TIMEOUT'),
            new ConfigParameter('verso.updates.database_timeout', $group, 'Database backup time limit', 'How long a database backup or restore may run during an update.', unit: Unit::Seconds, min: 30, max: 7200, env: 'VERSO_UPDATES_DATABASE_TIMEOUT'),
            new ConfigParameter('verso.updates.backups_kept', $group, 'Update backups kept', 'Backups of earlier versions kept on disk.', unit: Unit::Items, min: 1, max: 20, env: 'VERSO_UPDATES_BACKUPS_KEPT'),
            new ConfigParameter('verso.extensions.backups_kept', $group, 'Plugin and theme backups kept', 'Older versions kept for each plugin or theme.', unit: Unit::Items, min: 0, max: 20, env: 'VERSO_EXTENSIONS_BACKUPS_KEPT'),
        ];
    }

    /** @return list<ConfigParameter> */
    protected function api(): array
    {
        $group = self::API;

        return [
            new ConfigParameter('verso.api.rate_limit', $group, 'Requests per minute', 'Requests one client (by IP address) may send each minute.', unit: Unit::Requests, min: 1, max: 100_000, env: 'VERSO_API_RATE_LIMIT'),
            new ConfigParameter('verso.api.per_page', $group, 'Items per page', 'Items per page when the client does not ask for a number.', unit: Unit::Items, min: 1, max: 500, env: 'VERSO_API_PER_PAGE'),
            new ConfigParameter('verso.api.max_per_page', $group, 'Most items per page', 'Most items per page a client can ask for.', unit: Unit::Items, min: 1, max: 500, env: 'VERSO_API_MAX_PER_PAGE'),
        ];
    }

    /**
     * @param  array<string, array{label: string}>  $options
     * @return array<string, string>
     */
    protected function optionLabels(array $options): array
    {
        return array_map(fn (array $option): string => $option['label'], $options);
    }
}

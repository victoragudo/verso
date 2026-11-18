# Configuration

This file lists every operational parameter of Verso (core and bundled plugins), says who may change it and how, and explains the PHP and web server limits that sit under some of them. The admin side is **System settings** (`/admin/system/settings`) and **Server health** (`/admin/system/health`). Both need the `manage_system` capability, which only the Admin role has.

## Summary

| Class | Meaning | Count |
|---|---|---|
| (a) | Safe to change from the admin at runtime. The next request uses the new value. | 100 |
| (b) | Changeable from the admin, but a cache must be cleared or queue workers restarted. Verso does it right after saving. | 13 |
| (c) | Changeable from the admin, but PHP or the web server can set a lower limit. The admin shows the effective value and how to raise the server limit. | 12 |
| (d) | Never editable from the admin (secrets, paths, binaries, installer values, debug and proxies). | 76 + Laravel values listed below |
| (e) | Kept in the config files and env vars only: structure, cache keys, cron times, URLs of services, option lists and developer tools. They rarely change and a wrong value can break routes or caches, so they are not in the admin. | 408 |

Total: 609 parameters: 603 read from `config/verso.php` and `plugins/*/config/*.php`, plus 6 mail and queue values (option lists such as font stacks, languages and permalink presets are not counted one by one).

The 125 parameters of classes (a), (b) and (c) are declared once in the configuration registry (`App\Verso\System\Config\CoreParameters` and each plugin provider) and appear in System settings.

## How a value is chosen

1. **An env var that is set wins.** When the env var of a parameter is present (in `.env` or in the real environment), the admin shows "Set by the server environment" and the field cannot be changed. This keeps container and hosting panel setups in control.
2. **A value saved in System settings** comes next. It is stored with the Settings service under `system.config.<config key>`, in the base unit of the parameter (for example kilobytes).
3. **The default from the config file** is used otherwise.

`ConfigOverlay` puts the saved values on top of `config()` while Laravel boots, after plugin providers are registered and before any provider boots, so every module reads the final value. It reads settings from the settings cache, so it adds no query to a warm request.

- **config:cache.** The first time a parameter is seen, its config value and whether its env var is set are kept under the config key `verso_system_baseline`. `php artisan config:cache` stores this baseline too, so after caching the defaults and env flags stay correct and admin values still apply on top. Running `config:cache` again is never needed after a change in the admin.
- **Bad saved values** (out of range, wrong type, unknown choice, for example after a release lowered a maximum) are ignored: the default is used and System settings shows the problem next to the field.
- **Plugins enabled later** are covered: the registry tells the overlay about new parameters and they are applied at once.
- **Audit log.** Every change, reset, PHP limit file write and permission fix is stored in `system_config_changes` (user, key, old and new value) and listed in System settings.

## PHP and web server limits (the study)

### Which ini values matter

| Directive | Default in PHP | Changeable | Where it can be set | Used by Verso for |
|---|---|---|---|---|
| `upload_max_filesize` | 2M | PHP_INI_PERDIR | php.ini, `.user.ini` (FPM/CGI), `.htaccess` `php_value` (Apache mod_php, LiteSpeed), FPM pool `php_value`/`php_admin_value` | Biggest single file (media, import, extensions, forms, CSV, JSON imports) |
| `post_max_size` | 8M | PHP_INI_PERDIR | same as above | Whole request body. Must be bigger than `upload_max_filesize`. 0 means no limit |
| `memory_limit` | 128M | PHP_INI_ALL | everywhere, also `ini_set()` | Image resizing (Glide), imports, updates, static export |
| `max_execution_time` | 30 (0 on CLI) | PHP_INI_ALL | everywhere, also `ini_set()` and `set_time_limit()` | Long requests: imports, updates, image work |
| `max_input_time` | -1 | PHP_INI_PERDIR | php.ini, `.user.ini`, `.htaccess`, FPM pool | Time to read a slow upload |
| `max_input_vars` | 1000 | PHP_INI_PERDIR | php.ini, `.user.ini`, `.htaccess`, FPM pool | Big forms (menus, field groups). The block editor sends JSON, so it is not affected |
| `max_file_uploads` | 20 | PHP_INI_PERDIR | php.ini, `.user.ini`, `.htaccess`, FPM pool | Files in one request |
| `file_uploads` | 1 | PHP_INI_SYSTEM | php.ini, FPM pool only | Uploads on or off |
| `user_ini.filename`, `user_ini.cache_ttl` | `.user.ini`, 300 | PHP_INI_SYSTEM | php.ini only | Name of the per folder file and how long PHP caches it |
| `upload_tmp_dir`, `opcache.*`, `realpath_cache_size`, `disable_functions` | | PHP_INI_SYSTEM | php.ini, FPM pool | Not changed by Verso |

Facts that shaped the design:

- **`ini_set()` only works for PHP_INI_ALL values.** That is `memory_limit` and `max_execution_time` (and `set_time_limit()`). Upload sizes can never be raised by PHP code at runtime, because PHP reads the request body before any code runs.
- **PHP_INI_PERDIR values** can be set per folder: `.user.ini` in `public/` for PHP-FPM and CGI/FastCGI SAPIs (`fpm-fcgi`, `cgi-fcgi`), or `php_value` lines in `public/.htaccess` for Apache with mod_php (`apache2handler`) and LiteSpeed. Under PHP-FPM, `php_value` in `.htaccess` breaks the site with a 500 error, so Verso picks the file from the SAPI.
- **`.user.ini` is cached** for `user_ini.cache_ttl` seconds (300 by default), so a change shows up only after that delay. The built-in server (`php artisan serve`) and the CLI never read `.user.ini`.
- **`php_admin_value` in an FPM pool cannot be overridden** by `.user.ini`, `.htaccess` or `ini_set()`. If a value does not change after "Apply", the host has locked it this way.
- **The current scope is read at runtime** with `ini_get_all(null, true)` (`access` bits), so the admin shows the real scope of this server, not only the documented one.
- **The web server has its own body limit**: nginx `client_max_body_size` (default 1m, error 413), Apache `LimitRequestBody` (default 1 GB since 2.4.54, unlimited before), Caddy `request_body max_size`, LiteSpeed "Max Request Body Size". PHP cannot read them, so System settings has a "Web server body limit" field for the value you know.

### The effective upload limit

For each upload setting the admin shows:

```
effective = min(Verso setting, upload_max_filesize, post_max_size - room for other fields, web server body limit when known)
```

PHP values of 0 or -1 mean "no limit" and are skipped. The room for other fields is `verso.system.server.upload_overhead_kb` (256 KB). The admin names the lowest one (the bottleneck) in a plain sentence, and warns while you type when the new value is above it.

### Raising limits

- **At runtime, only inside heavy tasks.** `RuntimeLimits::raiseForHeavyTask()` raises `memory_limit` up to `verso.system.heavy_tasks.memory_mb` (512) and the time limit up to `verso.system.heavy_tasks.time_limit` (600 s) when the value is PHP_INI_ALL on this server. It never lowers a limit and leaves -1 and 0 alone. It runs in the WordPress import job, in the image route on a cache miss (Glide), in the core update job and in `verso:export`.
- **Per folder, after consent.** "Apply" in System settings writes the values that are too low (`upload_max_filesize`, `post_max_size`, `memory_limit`, `max_input_time`) into a managed block of `public/.user.ini` (FPM/CGI) or `public/.htaccess` (`<IfModule mod_php.c>` with `php_value` lines). It only runs after you confirm, only when the file (or `public/`) is writable, keeps a copy of the old file in `storage/app/private/system/backups` and never touches lines outside the block. Only the directives above are allowed and values must look like `64M`, `600` or `-1`, so no other ini line can be injected.
- **Otherwise, snippets.** The admin shows ready to copy snippets for php.ini, `.user.ini`, a PHP-FPM pool (`php_admin_value[...]`), nginx (`client_max_body_size`), Apache (`LimitRequestBody`, plus `php_value` for mod_php) and Caddy, and marks the one that fits the detected SAPI and server software.

### Server permissions

The Server health page checks, without following links out of the project:

- `storage` and its sub folders, `bootstrap/cache` (must be writable), `plugins` and `themes` (writable only to install from the admin).
- `.env`: private (recommended `0640`); danger when others can read or write it.
- `public/storage` links to `storage/app/public`; danger when it exposes the whole `storage` folder.
- The web root must be `public/` (danger when it is the project folder or when `public/.env` exists).
- Debug mode on in production.
- World writable files and files PHP cannot change (wrong owner, often after `sudo php artisan ...`) in `storage`, `bootstrap/cache`, `plugins`, `themes` and `public`, up to `verso.system.health.scan_limit` files.

Recommended modes: folders `0755` (one user) or `0775` (shared group with the web server), files `0644` or `0664`, `.env` `0640`. "Fix" shows a dry run first and only does what the PHP user can do inside the project: chmod of paths it owns, create missing folders, `storage:link`. Everything else comes as an exact shell command (for example `sudo chown -R www-data:www-data storage`).

## (a) Safe to change from the admin

| Key | Env var | Default | Unit | Range | Group | What it does |
|---|---|---|---|---|---|---|
| `verso.editor.max_blocks` | `VERSO_EDITOR_MAX_BLOCKS` | 2000 | items | 100..20000 | security | Most blocks one entry can hold, counting nested blocks. |
| `verso.editor.max_depth` | `VERSO_EDITOR_MAX_DEPTH` | 8 | items | 1..10 | security | How deep blocks can be nested (columns inside an entry count as one level). |
| `verso.editor.custom_css_max` | `VERSO_EDITOR_CUSTOM_CSS_MAX` | 2000 | characters | 100..100000 | security | Longest custom CSS one block can hold, in characters. |
| `verso.builder.custom_code.css_max` | `VERSO_CUSTOM_CSS_MAX` | 50000 | characters | 1000..1000000 | security |  |
| `verso.builder.custom_code.js_max` | `VERSO_CUSTOM_JS_MAX` | 50000 | characters | 1000..1000000 | security |  |
| `verso.users.invitation_expires_hours` | `VERSO_INVITATION_EXPIRES_HOURS` | 72 | hours | 1..2160 | security | Hours an invitation link stays valid. |
| `verso.users.profile.bio_max` | `VERSO_PROFILE_BIO_MAX` | 1000 | characters | 50..20000 | security | Longest profile bio. |
| `verso.users.profile.social_links_max` | `VERSO_PROFILE_SOCIAL_LINKS_MAX` | 8 | items | 1..50 | security | Most social links on one profile. |
| `verso.users.profile.avatar_max_kb` | `VERSO_PROFILE_AVATAR_MAX_KB` | 4096 | KB | 16.. | uploads | Biggest profile photo upload. |
| `verso.admin.per_page` | `VERSO_ADMIN_PER_PAGE` | 20 | items | 5..200 | performance | Rows per page in admin lists. |
| `verso.comments.throttle_attempts` | `VERSO_COMMENTS_THROTTLE_ATTEMPTS` | 5 | requests | 1..1000 | security | Comments a visitor can send per window (see throttle_minutes). |
| `verso.comments.throttle_minutes` | `VERSO_COMMENTS_THROTTLE_MINUTES` | 1 | minutes | 1..1440 | security | Length of the throttle window, in minutes. |
| `verso.comments.min_seconds` | `VERSO_COMMENTS_MIN_SECONDS` | 3 | seconds | 0..600 | security | A form sent faster than this (in seconds) is treated as a bot. |
| `verso.comments.max_seconds` | `VERSO_COMMENTS_MAX_SECONDS` | 86400 | seconds | 60..2592000 | security | A form older than this (in seconds) must be loaded again. |
| `verso.comments.spam_links` | `VERSO_COMMENTS_SPAM_LINKS` | 3 | items | 1..100 | security | A comment with this many links or more goes straight to spam. |
| `verso.comments.max_length` | `VERSO_COMMENTS_MAX_LENGTH` | 5000 | characters | 100..100000 | security | Longest comment allowed, in characters. |
| `verso.media.allowed_mimes` | `` | image/jpeg,image/png,image/gif,image/… |  |  | uploads | File types people may upload. |
| `verso.media.caption_max_length` | `VERSO_MEDIA_CAPTION_MAX_LENGTH` | 2000 | characters | 100..20000 | uploads | Longest caption allowed, in characters. |
| `verso.media.per_page` | `VERSO_MEDIA_PER_PAGE` | 40 | items | 10..200 | uploads | Items per page in the media library and the picker. |
| `verso.media.images.driver` | `VERSO_IMAGE_DRIVER` | gd |  |  | uploads | Image library used by Glide: gd or imagick. |
| `verso.media.images.default_format` | `VERSO_IMAGE_FORMAT` | webp |  |  | uploads | Format and quality used when the URL does not give one. |
| `verso.media.images.default_quality` | `VERSO_IMAGE_QUALITY` | 80 | percent | 10..100 | uploads |  |
| `verso.media.images.thumbnail_size` | `VERSO_IMAGE_THUMBNAIL_SIZE` | 400 | pixels | 100..1200 | uploads | Size of the square thumbnails in the media library, in pixels. |
| `verso.media.images.browser_cache_seconds` | `VERSO_IMAGE_BROWSER_CACHE` | 31536000 | seconds | 0..31536000 | uploads | Seconds browsers may cache an image. |
| `verso.sitemap.cache_ttl` | `VERSO_SITEMAP_CACHE_TTL` | 86400 | seconds | 0..2592000 | performance | Seconds to keep a generated sitemap in the cache. |
| `verso.sitemap.max_urls` | `VERSO_SITEMAP_MAX_URLS` | 5000 | items | 100..50000 | performance | Most URLs in one sitemap file. Bigger types are split in pages. |
| `verso.menus.cache_ttl` | `VERSO_MENU_CACHE_TTL` | 86400 | seconds | 0..2592000 | performance | Seconds to keep built menu trees in the cache. 0 turns the menu cache off. |
| `verso.extensions.max_unpacked_kb` | `VERSO_EXTENSIONS_MAX_UNPACKED_KB` | 51200 | KB | 1024..4194304 | uploads | Biggest size of all files once unpacked, in kilobytes. This stops zip bombs. |
| `verso.extensions.max_files` | `VERSO_EXTENSIONS_MAX_FILES` | 2000 | items | 10..100000 | uploads | Most files one package may hold. |
| `verso.extensions.staging_ttl_minutes` | `VERSO_EXTENSIONS_STAGING_TTL` | 60 | minutes | 5..1440 | security | Minutes an uploaded package waits for consent before it is deleted. |
| `verso.extensions.backups_kept` | `VERSO_EXTENSIONS_BACKUPS_KEPT` | 3 | items | 0..20 | queues | Backups of older versions kept for each plugin or theme. |
| `verso.extensions.verification.sandbox.timeout` | `VERSO_VERIFY_SANDBOX_TIMEOUT` | 60 | seconds | 10..600 | security | Seconds the sandbox process may run before it is stopped. |
| `verso.extensions.verification.themes.screenshot_max_kb` | `VERSO_VERIFY_THEME_SCREENSHOT_MAX_KB` | 1024 | KB | 16..20480 | security | Biggest theme screenshot before the theme check reports it. |
| `verso.extensions.verification.themes.screenshot_max_width` | `VERSO_VERIFY_THEME_SCREENSHOT_MAX_WIDTH` | 2400 | pixels | 320..10000 | security | Widest theme screenshot before the theme check reports it. |
| `verso.extensions.verification.themes.screenshot_max_height` | `VERSO_VERIFY_THEME_SCREENSHOT_MAX_HEIGHT` | 1800 | pixels | 240..10000 | security | Tallest theme screenshot before the theme check reports it. |
| `verso.extensions.verification.themes.preview_posts` | `VERSO_VERIFY_THEME_PREVIEW_POSTS` | 6 | items | 1..50 | security | Demo posts written to the sandbox database before a theme renders. |
| `verso.extensions.verification.themes.preview_max_kb` | `VERSO_VERIFY_THEME_PREVIEW_MAX_KB` | 256 | KB | 0..2048 | security | HTML kept for each rendered preview page in the theme report. |
| `verso.updates.download_timeout` | `VERSO_UPDATES_DOWNLOAD_TIMEOUT` | 120 | seconds | 10..3600 | queues | Seconds to wait for a package download. |
| `verso.updates.max_download_kb` | `VERSO_UPDATES_MAX_DOWNLOAD_KB` | 153600 | KB | 10240..2097152 | queues | Biggest package the updater downloads, in kilobytes. |
| `verso.updates.backups_kept` | `VERSO_UPDATES_BACKUPS_KEPT` | 2 | items | 1..20 | queues | Backups of earlier versions kept on disk. |
| `verso.updates.command_timeout` | `VERSO_UPDATES_COMMAND_TIMEOUT` | 600 | seconds | 30..7200 | queues | Seconds each artisan step (migrate, cache clear, maintenance) may take. |
| `verso.updates.database_timeout` | `VERSO_UPDATES_DATABASE_TIMEOUT` | 600 | seconds | 30..7200 | queues | Seconds a database dump or restore may take. |
| `verso.api.rate_limit` | `VERSO_API_RATE_LIMIT` | 60 | requests | 1..100000 | api | Requests per minute allowed for one client (by IP address). |
| `verso.api.per_page` | `VERSO_API_PER_PAGE` | 10 | items | 1..500 | api | Items per page when the client does not ask for a number. |
| `verso.api.max_per_page` | `VERSO_API_MAX_PER_PAGE` | 100 | items | 1..500 | api | Most items per page a client can ask for. |
| `verso.importer.max_download_bytes` | `VERSO_IMPORT_MAX_DOWNLOAD_BYTES` | 26214400 | bytes | 1048576..2147483648 | uploads | Largest file to download, in bytes. |
| `verso.themes.feed_items` | `VERSO_FEED_ITEMS` | 20 | items | 1..200 | performance | Most entries listed in the RSS feed. |
| `verso.themes.search_max_length` | `VERSO_SEARCH_MAX_LENGTH` | 100 | characters | 10..1000 | security |  |
| `verso.system.server.upload_overhead_kb` | `VERSO_UPLOAD_OVERHEAD_KB` | 256 | KB | 16..10240 | uploads | Part of PHP post_max_size kept for the other form fields sent with a file, in kilobytes. |
| `verso.system.server.web_server_body_mb` | `VERSO_WEB_SERVER_BODY_MB` | 0 | MB | 0..102400 | uploads | The web server body limit (nginx client_max_body_size, Apache LimitRequestBody) in megabytes. 0 means unknown. |
| `forms.per_page` | `FORMS_PER_PAGE` | 25 | items | 5..200 | plugin-forms | Rows shown on one admin page. |
| `forms.max_fields` | `FORMS_MAX_FIELDS` | 40 | items | 1..200 | plugin-forms | Most fields one form may have. |
| `forms.textarea_max` | `FORMS_TEXTAREA_MAX` | 5000 | characters | 100..100000 | plugin-forms |  |
| `forms.upload_default_kb` | `FORMS_UPLOAD_DEFAULT_KB` | 2048 | KB | 16..4194304 | plugin-forms |  |
| `forms.spam.rate_limit` | `FORMS_RATE_LIMIT` | 5 | requests | 1..1000 | plugin-forms | Messages one visitor (by IP hash) can send per window, and the window in minutes. |
| `forms.spam.rate_limit_minutes` | `FORMS_RATE_LIMIT_MINUTES` | 10 | minutes | 1..1440 | plugin-forms |  |
| `forms.captcha.timeout` | `FORMS_CAPTCHA_TIMEOUT` | 5 | seconds | 1..60 | plugin-forms | Optional captcha services. Keys are saved on the settings page. |
| `newsletter.confirm_hours` | `NEWSLETTER_CONFIRM_HOURS` | 72 | hours | 1..720 | plugin-newsletter | Hours a confirmation link stays valid. |
| `newsletter.signup_rate_limit` | `NEWSLETTER_SIGNUP_RATE_LIMIT` | 5 | requests | 1..1000 | plugin-newsletter | Signups allowed per minute from one IP address. |
| `newsletter.per_page` | `NEWSLETTER_PER_PAGE` | 25 | items | 5..200 | plugin-newsletter | Rows shown on one admin page. |
| `newsletter.heartbeat_stale_minutes` | `NEWSLETTER_HEARTBEAT_STALE_MINUTES` | 15 | minutes | 5..1440 | plugin-newsletter | Minutes without a heartbeat before the admin shows a "no queue worker" notice. |
| `shop.per_page` | `SHOP_PER_PAGE` | 12 | items | 1..200 | plugin-shop | Products per page on the shop page. |
| `shop.grid_max` | `SHOP_GRID_MAX` | 24 | items | 1..200 | plugin-shop | Most products a product grid block can show. |
| `shop.max_quantity` | `SHOP_MAX_QUANTITY` | 99 | items | 1..10000 | plugin-shop | Highest quantity of one product line in the cart. |
| `shop.checkout_throttle` | `SHOP_CHECKOUT_THROTTLE` | 10 | requests | 1..1000 | plugin-shop | Checkout requests a visitor can send per minute. |
| `shop.pending_orders.cancel_after_minutes` | `SHOP_CANCEL_PENDING_AFTER` | 1440 | minutes | 0..525600 | plugin-shop | Minutes an order may stay pending. 0 turns the automatic cancel off. |
| `shop.stripe.timeout` | `SHOP_STRIPE_TIMEOUT` | 15 | seconds | 1..120 | plugin-shop | Seconds to wait for Stripe before giving up. |
| `shop.stripe.retries` | `SHOP_STRIPE_RETRIES` | 2 | items | 0..10 | plugin-shop | Extra tries when Stripe cannot be reached. |
| `shop.stripe.webhook_tolerance` | `SHOP_STRIPE_WEBHOOK_TOLERANCE` | 300 | seconds | 30..3600 | plugin-shop | Oldest webhook signature accepted, in seconds. |
| `analytics.rate_limit` | `ANALYTICS_RATE_LIMIT` | 120 | requests | 1..10000 | plugin-analytics | Beacon requests allowed per minute for one IP address. |
| `analytics.max_path_length` | `ANALYTICS_MAX_PATH_LENGTH` | 255 | characters | 50..2000 | plugin-analytics | Longest page path that is recorded, in characters. |
| `analytics.top_limit` | `ANALYTICS_TOP_LIMIT` | 10 | items | 3..100 | plugin-analytics | Rows in the top pages and top referrers lists. |
| `analytics.rollup_chunk` | `ANALYTICS_ROLLUP_CHUNK` | 500 | items | 50..10000 | plugin-analytics | Rows written per insert when rolling up a day. |
| `ai-assistant.http.timeout` | `AI_ASSISTANT_TIMEOUT` | 60 | seconds | 5..600 | plugin-ai-assistant | Seconds to wait for an answer from the provider. |
| `ai-assistant.http.connect_timeout` | `AI_ASSISTANT_CONNECT_TIMEOUT` | 10 | seconds | 1..60 | plugin-ai-assistant | Seconds to wait for the connection to open. |
| `ai-assistant.http.retries` | `AI_ASSISTANT_RETRIES` | 2 | items | 0..10 | plugin-ai-assistant | Extra tries after a failed connection, a 429 or a 5xx answer. |
| `ai-assistant.http.retry_delay` | `AI_ASSISTANT_RETRY_DELAY` | 500 | ms | 0..60000 | plugin-ai-assistant | Milliseconds to wait between tries. |
| `ai-assistant.limits.rate_limit_window` | `AI_ASSISTANT_RATE_LIMIT_WINDOW` | 3600 | seconds | 60..86400 | plugin-ai-assistant | Seconds in one rate limit window. |
| `ai-assistant.limits.max_input_chars` | `AI_ASSISTANT_MAX_INPUT_CHARS` | 60000 | characters | 1000..1000000 | plugin-ai-assistant | Longest text (in characters) sent for one action. |
| `ai-assistant.limits.max_image_bytes` | `AI_ASSISTANT_MAX_IMAGE_BYTES` | 5242880 | bytes | 102400..20971520 | plugin-ai-assistant | Biggest image (in bytes) sent for alt text. |
| `memberships.per_page` | `MEMBERSHIPS_PER_PAGE` | 25 | items | 5..200 | plugin-memberships | Members shown on one admin page. |
| `reading-time.words_per_minute` | `READING_TIME_WORDS_PER_MINUTE` | 200 | items | 50..1000 | plugin-reading-time | Average reading speed used to count minutes. |
| `verso.seo.analysis.title_min` | `VERSO_SEO_TITLE_MIN` | 30 | characters | 5..200 | seo | Shortest good SEO title (editor checks and overview). |
| `verso.seo.analysis.title_max` | `VERSO_SEO_TITLE_MAX` | 60 | characters | 20..300 | seo | Longest good SEO title. |
| `verso.seo.analysis.description_min` | `VERSO_SEO_DESCRIPTION_MIN` | 120 | characters | 10..300 | seo | Shortest good meta description. |
| `verso.seo.analysis.description_max` | `VERSO_SEO_DESCRIPTION_MAX` | 160 | characters | 50..500 | seo | Longest good meta description. |
| `verso.seo.analysis.min_words` | `VERSO_SEO_MIN_WORDS` | 300 | items | 0..5000 | seo | Words for a full article. |
| `verso.seo.analysis.keyphrase_density_min` | `VERSO_SEO_KEYPHRASE_DENSITY_MIN` | 0.5 | percent | 0..10 | seo | Least use of the focus keyphrase. |
| `verso.seo.analysis.keyphrase_density_max` | `VERSO_SEO_KEYPHRASE_DENSITY_MAX` | 3 | percent | 0.5..20 | seo | Most use of the focus keyphrase. |
| `verso.seo.analysis.sentence_max_words` | `VERSO_SEO_SENTENCE_MAX_WORDS` | 20 | items | 8..80 | seo | Words in a long sentence. |
| `verso.seo.analysis.paragraph_max_words` | `VERSO_SEO_PARAGRAPH_MAX_WORDS` | 150 | items | 30..1000 | seo | Words in a long paragraph. |
| `verso.seo.analysis.good_score` | `VERSO_SEO_GOOD_SCORE` | 70 | percent | 1..100 | seo | Score for a green light. |
| `verso.seo.analysis.ok_score` | `VERSO_SEO_OK_SCORE` | 40 | percent | 0..99 | seo | Score for an amber light. |
| `verso.seo.audit.cache_ttl` | `VERSO_SEO_AUDIT_CACHE_TTL` | 600 | seconds | 0..86400 | seo | How long the SEO overview counts are kept. Saving content clears them. |
| `verso.seo.crawl.batch_size` | `VERSO_SEO_CRAWL_BATCH_SIZE` | 25 | items | 1..500 | seo | Published pages the link check reads in one job. |
| `verso.seo.crawl.slow_ms` | `VERSO_SEO_SLOW_PAGE_MS` | 300 | ms | 10..60000 | seo | Content build time over which a page is listed as slow. |
| `verso.seo.not_found.enabled` | `VERSO_404_MONITOR` | true | | | seo | Count addresses that answered 404. |
| `verso.seo.not_found.retention_days` | `VERSO_404_RETENTION_DAYS` | 30 | days | 1..365 | seo | Days a missing address is kept after its last visit. |
| `verso.seo.not_found.max_rows` | `VERSO_404_MAX_ROWS` | 5000 | items | 10..100000 | seo | Most missing addresses kept. |
| `verso.seo.not_found.per_minute` | `VERSO_404_PER_MINUTE` | 120 | requests | 1..10000 | seo | Most 404 hits counted per minute for the whole site. |

## (b) Changeable, with an automatic cache clear or queue restart

"Apply" lists what Verso does after saving: `flush page cache` bumps the page cache version, `flush block cache` empties the block HTML cache, `restart queue` runs `queue:restart` so long running workers boot again with the new config (workers started by cron with `--stop-when-empty` need nothing).

| Key | Env var | Default | Unit | Range | Group | What it does |
|---|---|---|---|---|---|---|
| `mail.default` | `MAIL_MAILER` | log | | | email | Mail service: SMTP server, Sendmail or log only. Apply: restart queue. |
| `mail.from.address` | `MAIL_FROM_ADDRESS` | hello@example.com | | 3..190 | email | Address that email comes from (checked as an email). Apply: restart queue. |
| `mail.from.name` | `MAIL_FROM_NAME` | APP_NAME | | 1..120 | email | Name next to the sender address. Apply: restart queue. |
| `mail.mailers.smtp.host` | `MAIL_HOST` | 127.0.0.1 | | 1..190 | email | SMTP server name or IP address. Apply: restart queue. |
| `mail.mailers.smtp.port` | `MAIL_PORT` | 2525 | | 1..65535 | email | SMTP port. Apply: restart queue. |
| `queue.connections.database.retry_after` | `DB_QUEUE_RETRY_AFTER` | 90 | seconds | 30..86400 | queues | A job still running after this time is given to another worker. Apply: restart queue. |
| `verso.importer.download_timeout` | `VERSO_IMPORT_DOWNLOAD_TIMEOUT` | 30 | seconds | 1..600 | queues | Seconds to wait for one download, how many times to retry and the pause between tries (ms). Apply: restart queue. |
| `verso.importer.download_retries` | `VERSO_IMPORT_DOWNLOAD_RETRIES` | 2 | items | 0..10 | queues |  Apply: restart queue. |
| `verso.importer.progress_every` | `VERSO_IMPORT_PROGRESS_EVERY` | 25 | items | 1..1000 | queues | Save progress to the import record every N items. Apply: restart queue. |
| `verso.block_cache.enabled` | `VERSO_BLOCK_CACHE` | true |  |  | performance | Keep the HTML of core blocks per entry. Entries with plugin blocks are always rendered fresh. Apply: flush block cache. |
| `verso.block_cache.ttl` | `VERSO_BLOCK_CACHE_TTL` | 86400 | seconds | 60..2592000 | performance | Seconds to keep the HTML of one entry. Apply: flush block cache. |
| `verso.page_cache.enabled` | `VERSO_PAGE_CACHE` | true |  |  | performance | Turn the full page cache for guests on or off. Apply: flush page cache. |
| `verso.page_cache.ttl` | `VERSO_PAGE_CACHE_TTL` | 3600 | seconds | 0..2592000 | performance | Seconds to keep a cached page. Apply: flush page cache. |

## (c) Bounded by PHP or the web server

| Key | Env var | Default | Unit | Range | Group | What it does |
|---|---|---|---|---|---|---|
| `verso.media.max_upload_kb` | `VERSO_MEDIA_MAX_UPLOAD_KB` | 10240 | KB | 1..4194304 | uploads | Biggest upload allowed, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `verso.media.images.max_image_size` | `VERSO_IMAGE_MAX_SIZE` | 16777216 | pixels | 1000000..100000000 | uploads | Largest image size Glide will create, in pixels (width x height). Bounded by: memory_limit. |
| `verso.media.images.max_dimension` | `VERSO_IMAGE_MAX_DIMENSION` | 3840 | pixels | 320..10000 | uploads | Largest width or height a URL may ask for. Bounded by: memory_limit. |
| `verso.site_parts.import_max_kb` | `VERSO_SITE_EDITOR_IMPORT_MAX_KB` | 2048 | KB | 16..102400 | uploads | Largest customization file the Site Editor imports, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `verso.extensions.max_upload_kb` | `VERSO_EXTENSIONS_MAX_UPLOAD_KB` | 10240 | KB | 64..1048576 | uploads | Biggest plugin or theme zip the admin accepts, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `verso.importer.max_upload_kb` | `VERSO_IMPORT_MAX_UPLOAD_KB` | 204800 | KB | 1..4194304 | uploads | Largest export file the admin page accepts, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `verso.importer.job_timeout` | `VERSO_IMPORT_JOB_TIMEOUT` | 3600 | seconds | 60..86400 | queues |  Apply: restart queue. Bounded by: max_execution_time. |
| `verso.system.heavy_tasks.memory_mb` | `VERSO_HEAVY_TASK_MEMORY_MB` | 512 | MB | 64..16384 | performance | Heavy tasks (imports, image work, updates, exports) may raise PHP limits up to these values, when PHP allows it. Bounded by: memory_limit. |
| `verso.system.heavy_tasks.time_limit` | `VERSO_HEAVY_TASK_TIME_LIMIT` | 600 | seconds | 30..86400 | performance |  Bounded by: max_execution_time. |
| `verso.fields.import_max_kb` | `VERSO_FIELDS_IMPORT_MAX_KB` | 512 | KB | 16..102400 | uploads | Biggest JSON file accepted by the import, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `forms.upload_max_kb` | `FORMS_UPLOAD_MAX_KB` | 10240 | KB | 16..4194304 | plugin-forms | File uploads: the largest size a form may allow (KB), the default size, and the file types a form may allow. Bounded by: upload_max_filesize, post_max_size, web_server_body. |
| `newsletter.import_max_kb` | `NEWSLETTER_IMPORT_MAX_KB` | 5120 | KB | 16..1048576 | plugin-newsletter | Largest CSV file accepted by the import, in kilobytes. Bounded by: upload_max_filesize, post_max_size, web_server_body. |

## (d) Never editable from the admin

Why: a wrong value would lock you out, leak secrets, let an attacker who steals an admin session take over the server, or point Verso at files outside the project.

- **Laravel values**: `APP_KEY` (changing it breaks sessions, encrypted settings like API keys and signed URLs), `APP_ENV` and `APP_DEBUG` (debug in production shows secrets; the Server health page warns about it), `APP_URL`, database credentials `DB_*` after install (a bad value takes the site down with no way back from the admin), `TRUSTED_PROXIES` and trusted hosts (wrong values let visitors fake their IP and bypass rate limits), `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`, `FILESYSTEM_DISK` and S3 keys, `MAIL_PASSWORD` and `MAIL_USERNAME` (secrets stay in `.env`), `LOG_*`.
- **Verso values**: folders and paths, external binaries (an admin could point them to any program), the update signing key, plugin and Stripe secrets in config (plugins keep their own encrypted secret fields on their settings pages), the admin URL prefix, and the installer values (only used before the site exists).

| Key | Env var | Default | Unit | What it does |
|---|---|---|---|---|
| `verso.version` |  | 0.1.0 |  | Verso version, read from the VERSION file at the root. Plugins and updates use it. |
| `verso.admin.prefix` | `VERSO_ADMIN_PREFIX` | admin |  | URL prefix for the admin area. |
| `verso.plugins.paths` | `VERSO_PLUGINS_PATHS` | <base>/plugins |  | Folders scanned for plugins. Each plugin is a sub folder with a manifest file. |
| `verso.plugins.manifest` | `VERSO_PLUGINS_MANIFEST` | verso.json |  | Name of the manifest file inside each plugin folder. |
| `verso.plugins.composer_installed` | `VERSO_PLUGINS_COMPOSER_INSTALLED` | <base>/vendor/composer/installed.json |  | Composer file that lists installed packages. Packages with extra.verso in their composer.json are plugins. Empty turns it off. |
| `verso.plugins.migrations_path` | `VERSO_PLUGINS_MIGRATIONS_PATH` | database/migrations |  | Folder inside a plugin that holds its database migrations. They run when the plugin is enabled. |
| `verso.plugins.config_path` | `VERSO_PLUGINS_CONFIG_PATH` | config |  | Folder inside a plugin that holds its config files. "config/shop.php" becomes the "shop" config key. |
| `verso.extensions.storage_path` | `VERSO_EXTENSIONS_STORAGE_PATH` | <base>/storage/app/private/extensions |  | Folder for uploaded packages waiting for consent, backups and temporary files. |
| `verso.extensions.verification.sandbox.php_binary` | `VERSO_VERIFY_PHP_BINARY` |  |  | PHP binary for the sandbox process. Empty finds the PHP command line binary. |
| `verso.updates.public_key` | `VERSO_UPDATES_PUBLIC_KEY` | official release key |  | Base64 Ed25519 public key. Every package must have a valid signature from it. Empty turns the check off. |
| `verso.updates.install_path` | `VERSO_UPDATES_INSTALL_PATH` | <base> |  | Folder the updater replaces. Tests point it to a temporary folder. |
| `verso.updates.storage_path` | `VERSO_UPDATES_STORAGE_PATH` | <base>/storage/app/private/updates |  | Folder for downloads, backups and the update lock file. |
| `verso.updates.php_binary` | `VERSO_UPDATES_PHP_BINARY` |  |  | PHP command line program used for artisan steps. Empty finds it on its own. |
| `verso.updates.binaries.mysqldump` | `VERSO_UPDATES_MYSQLDUMP` | mysqldump |  | Programs used to back up MySQL and PostgreSQL databases. |
| `verso.updates.binaries.mysql` | `VERSO_UPDATES_MYSQL` | mysql |  |  |
| `verso.updates.binaries.pg_dump` | `VERSO_UPDATES_PG_DUMP` | pg_dump |  |  |
| `verso.updates.binaries.psql` | `VERSO_UPDATES_PSQL` | psql |  |  |
| `verso.updates.health_check.paths` |  | /,/login |  | Paths that must answer without an error after an update. |
| `verso.install.lock_file` | `VERSO_INSTALL_LOCK_FILE` | <base>/storage/app/verso-installed.lock |  | File written when the site is installed. Its presence locks the installer. |
| `verso.install.state_file` | `VERSO_INSTALL_STATE_FILE` | <base>/storage/app/verso-install-prog… |  | File that keeps the progress of a running install, so a failed step can be retried. |
| `verso.install.env_file` | `VERSO_INSTALL_ENV_FILE` |  |  | The .env file the installer writes. Empty means the Laravel default (base_path('.env')). |
| `verso.install.env_example` | `VERSO_INSTALL_ENV_EXAMPLE` | <base>/.env.example |  | File copied to .env when it is missing. |
| `verso.install.env_permissions` | `VERSO_INSTALL_ENV_PERMISSIONS` | 0640 (octal) |  | Permissions for the written .env file (octal). It holds secrets. |
| `verso.install.redirect` | `VERSO_INSTALL_REDIRECT` | true |  | Send every web request to /install while Verso is not installed. |
| `verso.install.safe_drivers.session` |  | file |  | Drivers forced while the installer runs, because the database may not exist yet. |
| `verso.install.safe_drivers.cache` |  | file |  |  |
| `verso.install.safe_drivers.queue` |  | sync |  |  |
| `verso.install.php_version` | `VERSO_INSTALL_PHP_VERSION` | 8.4.0 |  | Lowest PHP version Verso runs on. |
| `verso.install.extensions` |  | pdo,mbstring,openssl,tokenizer,xml,ct… |  | PHP extensions Verso needs. |
| `verso.install.image_extensions` |  | gd,imagick |  | At least one of these image extensions must be loaded. |
| `verso.install.memory_limit` | `VERSO_INSTALL_MEMORY_LIMIT` | 128M | PHP size | Lowest PHP memory_limit the installer accepts. |
| `verso.install.writable` |  | <base>/storage,<base>/bootstrap/cache… |  | Folders the web server must be able to write to. |
| `verso.install.connect_timeout` | `VERSO_INSTALL_CONNECT_TIMEOUT` | 5 | seconds | Seconds to wait when the installer tests a connection (database, Redis, SMTP). |
| `verso.install.test_attempts_per_minute` | `VERSO_INSTALL_TEST_ATTEMPTS` | 10 | items | Tries per minute for the test buttons (database, mail, Redis, storage), per IP address. |
| `verso.install.password_min_length` | `VERSO_INSTALL_PASSWORD_MIN` | 10 | characters | Shortest admin password the installer accepts. |
| `verso.install.login_link_minutes` | `VERSO_INSTALL_LOGIN_LINK_MINUTES` | 30 | minutes | Minutes the "go to your dashboard" sign-in link stays valid after install. |
| `verso.install.database.min_versions.mysql` | `VERSO_INSTALL_MIN_MYSQL` | 8.0.0 |  | Lowest server versions Verso supports. |
| `verso.install.database.min_versions.mariadb` | `VERSO_INSTALL_MIN_MARIADB` | 10.6.0 |  |  |
| `verso.install.database.min_versions.pgsql` | `VERSO_INSTALL_MIN_PGSQL` | 14.0 |  |  |
| `verso.install.database.sqlite_path` | `VERSO_INSTALL_SQLITE_PATH` | database/database.sqlite |  | Default SQLite file, relative to the project folder. |
| `verso.install.database.host` | `VERSO_INSTALL_DB_HOST` | 127.0.0.1 |  | Default host and database name for server databases. |
| `verso.install.database.name` | `VERSO_INSTALL_DB_NAME` | verso |  |  |
| `verso.install.database.charset` | `VERSO_INSTALL_DB_CHARSET` | utf8mb4 | characters | Defaults and choices for MySQL and MariaDB. |
| `verso.install.database.collation` | `VERSO_INSTALL_DB_COLLATION` | utf8mb4_unicode_ci |  |  |
| `verso.install.database.ssl_modes` |  | disable,prefer,require,verify-ca,veri… |  | SSL modes offered for PostgreSQL, and the default one. |
| `verso.install.database.ssl_mode` | `VERSO_INSTALL_DB_SSLMODE` | prefer |  |  |
| `verso.install.database.verso_tables` |  | users,entries,settings,media |  | Tables that show an old Verso install is in the database. |
| `verso.install.services.mailers` |  | log,smtp,sendmail |  | Choices for the services step. |
| `verso.install.services.smtp_port` | `VERSO_INSTALL_SMTP_PORT` | 587 |  |  |
| `verso.install.services.smtp_encryptions` |  | tls,ssl,none |  |  |
| `verso.install.services.sendmail_path` | `VERSO_INSTALL_SENDMAIL_PATH` | /usr/sbin/sendmail -bs -i |  |  |
| `verso.install.services.cache_drivers` |  | file,database,redis |  |  |
| `verso.install.services.session_drivers` |  | file,database,redis |  |  |
| `verso.install.services.queue_drivers` |  | sync,database,redis |  |  |
| `verso.install.services.redis_host` | `VERSO_INSTALL_REDIS_HOST` | 127.0.0.1 |  |  |
| `verso.install.services.redis_port` | `VERSO_INSTALL_REDIS_PORT` | 6379 |  |  |
| `verso.install.services.s3_region` | `VERSO_INSTALL_S3_REGION` | us-east-1 |  |  |
| `verso.install.services.storage_probe` | `VERSO_INSTALL_STORAGE_PROBE` | .verso-write-test |  | File written and removed again by the "Test write" button. |
| `verso.install.cli.site_name` | `VERSO_INSTALL_SITE_NAME` | null |  | Values the command line installer uses when a flag is missing (useful in Docker and CI). |
| `verso.install.cli.site_url` | `VERSO_INSTALL_SITE_URL` | null |  |  |
| `verso.install.cli.admin_name` | `VERSO_INSTALL_ADMIN_NAME` | Site Admin |  |  |
| `verso.install.cli.admin_email` | `VERSO_INSTALL_ADMIN_EMAIL` | null |  |  |
| `verso.install.cli.admin_password` | `VERSO_INSTALL_ADMIN_PASSWORD` | null |  |  |
| `verso.install.cli.timezone` | `VERSO_INSTALL_TIMEZONE` | UTC |  |  |
| `verso.generators.themes_path` | `VERSO_THEMES_PATH` | <base>/themes |  | Folder where verso:make-theme writes new themes. |
| `verso.generators.blocks_path` |  | <base>/app/Blocks |  | Folder and namespace where verso:make-block writes blocks that do not belong to a plugin. |
| `verso.generators.blocks_namespace` |  | App\Blocks |  |  |
| `verso.themes.path` | `VERSO_THEMES_PATH` | themes |  | Folder (from the project root) that holds the themes. |
| `verso.system.backup_path` | `VERSO_SYSTEM_BACKUP_PATH` | <base>/storage/app/private/system/bac… |  | Folder for copies of public/.user.ini and public/.htaccess made before Verso changes them. |
| `verso.system.health.writable` |  | storage,storage/app,storage/app/publi… |  | Folders (from the project root) that PHP must be able to write to. |
| `verso.system.health.optional` |  | plugins,themes |  | Folders that only some features write to (installing plugins and themes from the admin). |
| `verso.system.health.scan` |  | storage,bootstrap/cache,plugins,theme… |  | Folders searched for world writable files and files with the wrong owner. |
| `verso.system.health.scan_limit` | `VERSO_HEALTH_SCAN_LIMIT` | 5000 | items | Most files looked at by one scan, so the page stays fast on big sites. |
| `verso.export.path` | `VERSO_EXPORT_PATH` | storage/app/static |  | Default folder for "php artisan verso:export". |
| `shop.stripe.secret_key` | `SHOP_STRIPE_SECRET_KEY` | null |  | Keys used when none are saved in the shop settings. |
| `shop.stripe.webhook_secret` | `SHOP_STRIPE_WEBHOOK_SECRET` | null |  |  |

## (e) Config files and env vars only

These stay in `config/*.php` with an env override. Most are structural (cache keys and prefixes, cron schedules, route paths, option lists, capability names, service URLs) or developer tools (benchmark, demo, generators). Plugins keep their business settings (newsletter batches, form retention, shop taxes, analytics retention) on their own admin pages; those are not repeated in System settings.

| Key | Env var | Default | Unit | What it does |
|---|---|---|---|---|
| `verso.editor.allowed_url_schemes` |  | http,https,mailto,tel |  | URL schemes allowed in links, images, videos and buttons. Relative URLs are always allowed. |
| `verso.editor.html_capability` | `VERSO_EDITOR_HTML_CAPABILITY` | manage_settings |  | Capability a user needs to save raw HTML blocks. |
| `verso.editor.plugin_text_max` | `VERSO_EDITOR_PLUGIN_TEXT_MAX` | 1000 | characters | Longest text a plugin block text field can hold, in characters. |
| `verso.editor.breakpoints.tablet` | `VERSO_BREAKPOINT_TABLET` | 1024 | pixels | Largest screen width in pixels of each screen size used by responsive block styles. |
| `verso.editor.breakpoints.mobile` | `VERSO_BREAKPOINT_MOBILE` | 640 | pixels |  |
| `verso.editor.custom_css_capability` | `VERSO_EDITOR_CUSTOM_CSS_CAPABILITY` | edit_custom_css |  | Capability a user needs to save custom CSS on a block. `manage_settings` implies it. |
| `verso.editor.presets.max_saved` | `VERSO_STYLE_PRESETS_MAX` | 100 | items | Most style presets people can save from the Styles panel. |
| `verso.blocks.asset_cache_seconds` | `VERSO_BLOCK_ASSET_CACHE_SECONDS` | 31536000 | seconds | How long browsers may keep block CSS and JavaScript files. The URL changes when a file changes. |
| `verso.layouts.max_revisions` | `VERSO_LAYOUTS_MAX_REVISIONS` | 20 | items | Published versions kept per theme builder layout. |
| `verso.layouts.max_conditions` | `VERSO_LAYOUTS_MAX_CONDITIONS` | 30 | items | Most rules one layout can have. |
| `verso.layouts.words_per_minute` | `VERSO_READING_WORDS_PER_MINUTE` | 230 | words | Words people read in a minute, for the reading time block. |
| `verso.layouts.query_max` | `VERSO_QUERY_LOOP_MAX` | 48 | items | Most entries one query loop shows on a page. |
| `verso.layouts.query_choices` | `VERSO_QUERY_LOOP_CHOICES` | 300 | items | Most terms and authors offered as query loop filters. |
| `verso.library.editor_items` | `VERSO_LIBRARY_EDITOR_ITEMS` | 200 | items | Most library items sent to the editor Add panel. |
| `verso.library.import_max` | `VERSO_LIBRARY_IMPORT_MAX` | 100 | items | Most items one library import file can add. |
| `verso.library.import_max_kb` | `VERSO_LIBRARY_IMPORT_MAX_KB` | 2048 | KB | Biggest library import file. |
| `verso.library.used_in_limit` | `VERSO_LIBRARY_USED_IN_LIMIT` | 50 | items | Pages listed under "Used in" for a synced block. |
| `verso.editor.embed.youtube_url` | `VERSO_EMBED_YOUTUBE_URL` | https://www.youtube-nocookie.com/embed/ |  | Privacy friendly players used by the embed block. |
| `verso.editor.embed.vimeo_url` | `VERSO_EMBED_VIMEO_URL` | https://player.vimeo.com/video/ |  |  |
| `verso.builder.recent_limit` | `VERSO_BUILDER_RECENT_LIMIT` | 6 | items | How many items the "Recently used" list of the Add panel keeps. |
| `verso.builder.undo_steps` | `VERSO_BUILDER_UNDO_STEPS` | 100 | items | Most undo steps the editor keeps for one page. |
| `verso.builder.undo_merge_ms` | `VERSO_BUILDER_UNDO_MERGE_MS` | 800 | ms | Typing in the same block within this many milliseconds is one undo step. |
| `verso.builder.convert_max_length` | `VERSO_BUILDER_CONVERT_MAX_LENGTH` | 200000 | characters | Longest HTML, in characters, the code view can turn into blocks at once. |
| `verso.builder.custom_code.js_capability` | `VERSO_CUSTOM_JS_CAPABILITY` | manage_settings |  | Capability a user needs to save custom JavaScript (custom CSS uses editor.custom_css_capability). |
| `verso.builder.custom_code.cache_seconds` | `VERSO_CUSTOM_JS_CACHE_SECONDS` | 31536000 | seconds | How long browsers may keep a script file. The URL changes when the script changes. |
| `verso.builder.element.max_attributes` | `VERSO_BUILDER_ELEMENT_MAX_ATTRIBUTES` | 20 | items | Limits of the HTML element block. |
| `verso.builder.element.max_value_length` | `VERSO_BUILDER_ELEMENT_MAX_VALUE_LENGTH` | 500 | characters |  |
| `verso.builder.features.add_panel` | `VERSO_BUILDER_ADD_PANEL_ROLES` | admin,editor,author,contributor,shop_… |  | Each value is a comma separated list of roles. |
| `verso.builder.features.list_view` | `VERSO_BUILDER_LIST_VIEW_ROLES` | admin,editor,author,contributor,shop_… |  |  |
| `verso.builder.features.styles_panel` | `VERSO_BUILDER_STYLES_PANEL_ROLES` | admin,editor,author,shop_manager |  |  |
| `verso.builder.features.element_block` | `VERSO_BUILDER_ELEMENT_BLOCK_ROLES` | admin,editor |  |  |
| `verso.builder.features.code_editor` | `VERSO_BUILDER_CODE_EDITOR_ROLES` | admin,editor |  |  |
| `verso.builder.features.custom_css` | `VERSO_BUILDER_CUSTOM_CSS_ROLES` | admin |  |  |
| `verso.builder.features.custom_js` | `VERSO_BUILDER_CUSTOM_JS_ROLES` | admin |  |  |
| `verso.users.default_role` | `VERSO_DEFAULT_ROLE` | subscriber |  | Role given to new users. The first registered user is always an admin. |
| `verso.users.pending_invitations_limit` | `VERSO_PENDING_INVITATIONS_LIMIT` | 100 | items | Most pending invitations listed on the users page. |
| `verso.users.reassign_columns.entries` |  | author_id |  | Columns (table => column) that point to a user. They move to another user when a user is deleted. |
| `verso.users.reassign_columns.revisions` |  | user_id |  |  |
| `verso.users.reassign_columns.media` |  | uploaded_by |  |  |
| `verso.users.profile.author_base` | `VERSO_AUTHOR_BASE` | author |  | First part of the author page URL. |
| `verso.admin.dashboard_items` | `VERSO_ADMIN_DASHBOARD_ITEMS` | 5 | items | Rows in each short list on the admin dashboard. |
| `verso.admin.nav.new_badge_days` | `VERSO_ADMIN_NAV_NEW_BADGE_DAYS` | 14 | days | Days a sidebar item from a newly enabled plugin shows a "New" badge, until the user opens it. |
| `verso.admin.nav.max_plugin_items` | `VERSO_ADMIN_NAV_MAX_PLUGIN_ITEMS` | 12 | items | Most sidebar items one plugin may declare in the "admin" part of verso.json. |
| `verso.admin.nav.max_keywords` | `VERSO_ADMIN_NAV_MAX_KEYWORDS` | 20 | words | Most search words one sidebar item may declare. |
| `verso.admin.nav.max_seen` | `VERSO_ADMIN_NAV_MAX_SEEN` | 200 | items | Most opened sidebar items remembered per user for the "New" badge. |
| `verso.admin.lists.max_columns` | `VERSO_ADMIN_LIST_MAX_COLUMNS` | 40 | columns | Most columns a user may pick for one admin list. |
| `verso.admin.lists.thumbnail_size` | `VERSO_ADMIN_LIST_THUMBNAIL_SIZE` | 80 | pixels | Width of the small featured image in admin lists. |
| `verso.settings.cache_key` | `VERSO_SETTINGS_CACHE_KEY` | verso.settings |  | Cache key that holds all settings. |
| `verso.settings.cache_ttl` | `VERSO_SETTINGS_CACHE_TTL` | null | seconds | Seconds to keep settings in the cache. Null keeps them forever. |
| `verso.settings.defaults.site.name` | `APP_NAME` | Verso |  | Values used when a setting is not stored yet. |
| `verso.settings.defaults.site.tagline` |  | Just another Verso site |  |  |
| `verso.settings.defaults.site.url` | `APP_URL` | http://localhost |  |  |
| `verso.settings.defaults.site.locale` | `APP_LOCALE` | en |  |  |
| `verso.settings.defaults.site.timezone` | `APP_TIMEZONE` | UTC |  |  |
| `verso.settings.defaults.reading.posts_per_page` |  | 10 | items |  |
| `verso.settings.defaults.reading.front_page` |  | null |  |  |
| `verso.settings.defaults.content.revisions_limit` | `VERSO_REVISIONS_LIMIT` | 25 | items |  |
| `verso.settings.defaults.theme.active` |  | default |  |  |
| `verso.settings.defaults.plugins.enabled` |  | `[]` (none) |  |  |
| `verso.settings.defaults.reading.discourage_search` |  | false |  |  |
| `verso.settings.defaults.seo.robots_txt` |  | `User-agent: *` and `Disallow: /admin` |  |  |
| `verso.settings.defaults.permalinks.trailing_slash` | `VERSO_PERMALINKS_TRAILING_SLASH` | false |  |  |
| `verso.settings.defaults.permalinks.lowercase` | `VERSO_PERMALINKS_LOWERCASE` | false |  |  |
| `verso.settings.defaults.comments.enabled` | `VERSO_COMMENTS_ENABLED` | true |  |  |
| `verso.settings.defaults.updates.channel` | `VERSO_UPDATES_CHANNEL` | stable |  |  |
| `verso.settings.defaults.updates.automatic` | `VERSO_UPDATES_AUTOMATIC` | off |  |  |
| `verso.content.slug_max_length` | `VERSO_SLUG_MAX_LENGTH` | 190 | characters | Longest slug allowed for entries and terms. |
| `verso.content.excerpt_max_length` | `VERSO_EXCERPT_MAX_LENGTH` | 1000 | characters | Longest excerpt allowed, in characters. |
| `verso.content.reserved_slugs` |  | admin,blog,category,tag,search,feed,s… |  | Top level page slugs that would clash with public routes. |
| `verso.content.publish_schedule` | `VERSO_PUBLISH_SCHEDULE` | * * * * * |  | How often (cron expression) scheduled entries are checked. |
| `verso.comments.honeypot_field` | `VERSO_COMMENTS_HONEYPOT_FIELD` | website_address |  | Hidden field that only bots fill in. |
| `verso.comments.max_depth` | `VERSO_COMMENTS_MAX_DEPTH` | 5 | items | Deepest reply level. Deeper replies are attached to the last allowed level. |
| `verso.comments.open_by_default` | `VERSO_COMMENTS_OPEN_BY_DEFAULT` | true |  | Value used when an entry has no comments_open meta. |
| `verso.redirects.default_status` | `VERSO_REDIRECT_STATUS` | 301 |  | Status code used when none is given. |
| `verso.media.disk` | `VERSO_MEDIA_DISK` | public |  | Disk where uploaded files are stored. |
| `verso.media.folder_format` | `VERSO_MEDIA_FOLDER_FORMAT` | Y/m |  | Folder pattern for new uploads (PHP date format). |
| `verso.media.usage_meta_keys` |  | featured_media_id,featured_image.mediaId |  | Entry meta keys that hold a media id (dot notation), checked before a delete. |
| `verso.media.images.prefix` | `VERSO_IMAGE_PREFIX` | img |  | URL prefix of the image route, like /img/2026/11/photo.jpg. |
| `verso.media.images.cache_disk` | `VERSO_IMAGE_CACHE_DISK` | local |  | Disk and folder where resized images are cached. |
| `verso.media.images.cache_folder` | `VERSO_IMAGE_CACHE_FOLDER` | glide-cache |  |  |
| `verso.media.images.formats` |  | webp,avif,jpg,png,gif |  | Output formats a URL may ask for. |
| `verso.media.images.srcset_widths` |  | 320,640,960,1280,1920 | pixels | Widths used to build responsive srcset lists. |
| `verso.media.images.block_sizes` | `VERSO_IMAGE_BLOCK_SIZES` | (min-width: 760px) 720px, 100vw | pixels | The sizes attribute for image blocks, gallery items and featured images. |
| `verso.media.images.gallery_sizes` | `VERSO_IMAGE_GALLERY_SIZES` | (min-width: 760px) 240px, 50vw | pixels |  |
| `verso.media.images.featured_sizes` | `VERSO_IMAGE_FEATURED_SIZES` | (min-width: 760px) 640px, 100vw | pixels |  |
| `verso.site.locales` |  | en,es,fr,de,it,pt,pt-BR,nl,pl,ca,ja,zh,zh-Hans,hi,ar,bn,ru,id,tr,ko |  | Lists the admin settings page offers. Free values are not allowed. |
| `verso.site.max_posts_per_page` | `VERSO_MAX_POSTS_PER_PAGE` | 100 | items | Highest number of posts per page a user can pick. |
| `verso.site.max_revisions_limit` | `VERSO_MAX_REVISIONS_LIMIT` | 500 | items | Highest number of revisions to keep per entry. |
| `verso.site.robots_txt_max_length` | `VERSO_ROBOTS_TXT_MAX_LENGTH` | 5000 | characters | Longest robots.txt allowed, in characters. |
| `verso.i18n.admin_locales` | `VERSO_ADMIN_LOCALES` | en,es,zh-Hans,hi,ar,pt-BR,bn,ru,ja,fr,de,id,tr,ko,it |  | Languages of the admin (comma separated codes). Each one needs `lang/<code>.json`. See [I18N.md](I18N.md). |
| `verso.i18n.fallback_locale` | `VERSO_ADMIN_FALLBACK_LOCALE` | en |  | Language used when a text has no translation. |
| `verso.i18n.bundle_max_age` | `VERSO_I18N_BUNDLE_MAX_AGE` | 31536000 | seconds | Seconds browsers may keep a translation bundle. The URL changes when a file changes. |
| `verso.i18n.bundle_cache_ttl` | `VERSO_I18N_BUNDLE_CACHE_TTL` | 86400 | seconds | Seconds the server keeps a built bundle in the cache store. |
| `verso.i18n.extension_lang_path` | `VERSO_I18N_EXTENSION_LANG_PATH` | lang |  | Folder inside a plugin or theme with its `lang/<code>.json` files. |
| `verso.seo.title_separator` | `VERSO_SEO_TITLE_SEPARATOR` |  \|  |  | Text between the entry title and the site name in <title>. |
| `verso.seo.title_max_length` | `VERSO_SEO_TITLE_MAX_LENGTH` | 120 | characters | Longest SEO title and description allowed, in characters. |
| `verso.seo.description_max_length` | `VERSO_SEO_DESCRIPTION_MAX_LENGTH` | 320 | characters |  |
| `verso.seo.article_types` |  | post |  | Content types shown as an Article in schema.org data. Others are a WebPage. |
| `verso.seo.search_path` | `VERSO_SEARCH_PATH` | /search |  | Search page path and query parameter, used by the WebSite SearchAction. |
| `verso.seo.search_param` | `VERSO_SEARCH_PARAM` | q |  |  |
| `verso.seo.title_format` | `VERSO_SEO_TITLE_FORMAT` | {title}{sep}{site} |  | Default page title format. Tokens: {title}, {sep}, {site} and {tagline}. |
| `verso.seo.robots_default` |  | User-agent: *\nDisallow: /admin\n |  | The robots.txt rules used until the admin saves others, and by the "Use safe default" button. |
| `verso.seo.separator_max_length` | `VERSO_SEO_SEPARATOR_MAX_LENGTH` | 10 | characters | Longest title separator and schema.org name allowed, in characters. |
| `verso.seo.schema_name_max_length` | `VERSO_SEO_SCHEMA_NAME_MAX_LENGTH` | 120 | characters |  |
| `verso.seo.verification_max_length` | `VERSO_SEO_VERIFICATION_MAX_LENGTH` | 120 | characters | Longest verification code allowed, in characters. |
| `verso.seo.same_as_max` | `VERSO_SEO_SAME_AS_MAX` | 10 | characters | Most "same as" profile links for the schema.org organization or person. |
| `verso.seo.indexnow.endpoint` | `VERSO_INDEXNOW_ENDPOINT` | https://api.indexnow.org/indexnow |  | IndexNow: tells search engines about new and changed pages right away. |
| `verso.seo.indexnow.timeout` | `VERSO_INDEXNOW_TIMEOUT` | 10 | seconds |  |
| `verso.seo.indexnow.queue` | `VERSO_INDEXNOW_QUEUE` | default |  |  |
| `verso.seo.indexnow.key_min_length` | `VERSO_INDEXNOW_KEY_MIN_LENGTH` | 8 | characters |  |
| `verso.seo.indexnow.key_max_length` | `VERSO_INDEXNOW_KEY_MAX_LENGTH` | 128 | characters |  |
| `verso.seo.twitter_site` | `VERSO_TWITTER_SITE` |  |  | Twitter handle of the site, like "@versocms". Empty means none. |
| `verso.seo.schema_types` |  | post: BlogPosting |  | schema.org type per content type. Other article types are an Article. |
| `verso.seo.keyphrase_max_length` | `VERSO_SEO_KEYPHRASE_MAX_LENGTH` | 100 | characters | Longest focus keyphrase. |
| `verso.seo.faq_max_questions` | `VERSO_SEO_FAQ_MAX_QUESTIONS` | 50 | items | Most questions in the FAQPage schema of one page. |
| `verso.seo.analysis.keyphrase_max_words`, `slug_max_words`, `long_sentences_max_percent`, `subheading_max_words`, `passive_max_percent`, `transition_min_percent` | `VERSO_SEO_KEYPHRASE_MAX_WORDS`, `VERSO_SEO_SLUG_MAX_WORDS`, `VERSO_SEO_LONG_SENTENCES_MAX_PERCENT`, `VERSO_SEO_SUBHEADING_MAX_WORDS`, `VERSO_SEO_PASSIVE_MAX_PERCENT`, `VERSO_SEO_TRANSITION_MIN_PERCENT` | 4, 6, 25, 300, 10, 30 |  | Other limits of the editor checks. |
| `verso.seo.analysis.debounce_ms` | `VERSO_SEO_ANALYSIS_DEBOUNCE_MS` | 400 | ms | Wait after the last key press before the editor checks run. |
| `verso.seo.audit.chunk` | `VERSO_SEO_AUDIT_CHUNK` | 200 | items | Entries read at once while the overview is counted. |
| `verso.seo.audit.per_page` | `VERSO_SEO_AUDIT_PER_PAGE` | 25 | items | Rows per page in the list of one issue. |
| `verso.seo.audit.max_items` | `VERSO_SEO_AUDIT_MAX_ITEMS` | 1000 | items | Most rows kept for the list of one issue. Counts stay exact. |
| `verso.seo.crawl.queue` | `VERSO_SEO_CRAWL_QUEUE` | default |  | Queue of the link check jobs. |
| `verso.seo.crawl.schedule` | `VERSO_SEO_CRAWL_SCHEDULE` | 30 3 * * 0 |  | Cron of the automatic link check. Empty turns it off. |
| `verso.seo.crawl.max_links` | `VERSO_SEO_CRAWL_MAX_LINKS` | 500 | items | Most links read from one entry. |
| `verso.seo.crawl.stale_after` | `VERSO_SEO_CRAWL_STALE_AFTER` | 3600 | seconds | A link check that did not move for this long can be started again. |
| `verso.seo.not_found.per_visitor_per_minute` | `VERSO_404_PER_VISITOR_PER_MINUTE` | 10 | requests | Most 404 hits counted per visitor per minute (hashed IP, cache only). |
| `verso.seo.not_found.path_max_length` | `VERSO_404_PATH_MAX_LENGTH` | 255 | characters | Longest path kept. |
| `verso.seo.not_found.ignore` | `VERSO_404_IGNORE` | *.php, /wp-admin*, /wp-login*, /.env*, /.git*, /.well-known/*, /favicon.ico, /apple-touch-icon* |  | Paths never counted. |
| `verso.seo.not_found.suggestions`, `suggestion_candidates`, `suggestion_min_similarity` | `VERSO_404_SUGGESTIONS`, `VERSO_404_SUGGESTION_CANDIDATES`, `VERSO_404_SUGGESTION_MIN_SIMILARITY` | 3, 300, 45 |  | Closest pages suggested per missing address. |
| `verso.seo.not_found.per_page` | `VERSO_404_PER_PAGE` | 25 | items | Rows per page of the 404 monitor. |
| `verso.seo.not_found.prune_at` | `VERSO_404_PRUNE_AT` | 03:15 |  | Daily clean up time. |
| `verso.seo.alt_text.per_page`, `max_length` | `VERSO_SEO_ALT_TEXT_PER_PAGE`, `VERSO_SEO_ALT_TEXT_MAX_LENGTH` | 24, 250 |  | Images per page and longest alt text on the bulk alt text screen. |
| `verso.permalinks.category_taxonomy` | `VERSO_PERMALINKS_CATEGORY_TAXONOMY` | category |  | Taxonomy used by the {category} token, and the slug used when an entry has no category. |
| `verso.permalinks.uncategorized` | `VERSO_PERMALINKS_UNCATEGORIZED` | uncategorized |  |  |
| `verso.permalinks.previous_kept` | `VERSO_PERMALINKS_PREVIOUS_KEPT` | 10 | items | How many older structures per type are kept to redirect old links. |
| `verso.permalinks.max_length` | `VERSO_PERMALINKS_MAX_LENGTH` | 120 | characters | Longest URL structure or base allowed, in characters. |
| `verso.permalinks.reserved_paths` |  | admin,api,search,feed,img,login,logou… |  | First path parts that no structure or base may use, because Verso or Laravel serve them. |
| `verso.sitemap.cache_prefix` | `VERSO_SITEMAP_CACHE_PREFIX` | verso.sitemap |  | Prefix of the sitemap cache keys. |
| `verso.sitemap.enabled` | `VERSO_SITEMAP_ENABLED` | true |  | Sitemap on or off until the admin saves the setting. |
| `verso.sitemap.include_images` | `VERSO_SITEMAP_INCLUDE_IMAGES` | false |  | Add <image:image> rows for featured images until the admin saves the setting. |
| `verso.sitemap.entry_picker_limit` | `VERSO_SITEMAP_ENTRY_PICKER_LIMIT` | 500 | items | Most published entries offered in the "Leave out of the sitemap" picker. |
| `verso.sitemap.max_urls_limit` | `VERSO_SITEMAP_MAX_URLS_LIMIT` | 50000 | items | Highest value the admin may pick for max_urls (the sitemap protocol allows 50000). |
| `verso.sitemap.exclude_types` |  |  |  | Content types left out of the sitemap. |
| `verso.sitemap.exclude_taxonomies` |  |  |  | Taxonomies whose term archives are left out of the sitemap. |
| `verso.menus.max_depth` | `VERSO_MENU_MAX_DEPTH` | 3 | items | Deepest nesting allowed in a menu (1 means no sub items). |
| `verso.menus.picker_limit` | `VERSO_MENU_PICKER_LIMIT` | 50 | items | Most entries and terms offered in the menu builder lists. |
| `verso.menus.cache_prefix` | `VERSO_MENU_CACHE_PREFIX` | verso.menus |  | Prefix of the menu cache keys. |
| `verso.site_parts.max_revisions` | `VERSO_SITE_PART_REVISIONS` | 20 | items | Revisions kept for each edited part (header, footer...). Older ones are deleted on publish. |
| `verso.site_parts.autosave_delay` | `VERSO_SITE_EDITOR_AUTOSAVE_DELAY` | 1200 | ms | Milliseconds the Site Editor waits after the last change before it saves a draft and refreshes the preview. |
| `verso.site_parts.undo_steps` | `VERSO_SITE_EDITOR_UNDO_STEPS` | 50 | items | How many changes "Undo" can step back in the Site Editor. |
| `verso.site_parts.preview_widths.desktop` |  | null | pixels | Preview widths in pixels of the Site Editor. Null fills the space. |
| `verso.site_parts.preview_widths.tablet` | `VERSO_SITE_EDITOR_TABLET_WIDTH` | 768 | pixels |  |
| `verso.site_parts.preview_widths.phone` | `VERSO_SITE_EDITOR_PHONE_WIDTH` | 390 | pixels |  |
| `verso.site_parts.preview_desktop_min_width` | `VERSO_SITE_EDITOR_DESKTOP_MIN_WIDTH` | 1280 | pixels | The builders draw the computer preview at least this wide and shrink it to fit a narrower pane. |
| `verso.plugins.migrate_on_enable` | `VERSO_PLUGINS_MIGRATE_ON_ENABLE` | true |  | Run the migrations of a plugin when it is enabled. |
| `verso.extensions.max_manifest_kb` | `VERSO_EXTENSIONS_MAX_MANIFEST_KB` | 64 | KB | Biggest manifest file (verso.json or theme.json), in kilobytes. |
| `verso.extensions.allowed_extensions` |  | php,json,md,txt,css,js,mjs,ts,tsx,map… |  | File extensions a package may contain. Anything else is refused. |
| `verso.extensions.allowed_plain_files` |  | LICENSE,README,CHANGELOG,NOTICE,AUTHORS |  | File names without an extension that a package may contain. |
| `verso.extensions.plugin_php_folders` |  | src,config,routes,database/migrations… |  | Folders of a plugin where PHP files may live, besides its autoload folders. |
| `verso.extensions.theme_php_folders` |  | views |  | Folders of a theme where PHP files may live. Theme PHP files must be Blade views. |
| `verso.extensions.registry.url` | `VERSO_REGISTRY_URL` |  |  | URL of a JSON index with plugins and themes to install. Empty turns the Browse tab off. |
| `verso.extensions.registry.timeout` | `VERSO_REGISTRY_TIMEOUT` | 15 | seconds | Seconds to wait for the index or a download. |
| `verso.extensions.registry.cache_ttl` | `VERSO_REGISTRY_CACHE_TTL` | 600 | seconds | Seconds to keep the index in the cache. |
| `verso.extensions.registry.cache_key` | `VERSO_REGISTRY_CACHE_KEY` | verso.registry.index |  | Cache key for the index. |
| `verso.extensions.registry.download_schemes` |  | https |  | URL schemes allowed for package downloads. |
| `verso.extensions.verification.block_high_risk` | `VERSO_VERIFY_BLOCK_HIGH_RISK` | false |  | Default of the "block high risk plugins" setting. When on, high risk plugins cannot be installed. |
| `verso.extensions.verification.weights.info` | `VERSO_VERIFY_WEIGHT_INFO` | 0 |  | Points added once per rule and file for each finding severity. |
| `verso.extensions.verification.weights.low` | `VERSO_VERIFY_WEIGHT_LOW` | 2 |  |  |
| `verso.extensions.verification.weights.medium` | `VERSO_VERIFY_WEIGHT_MEDIUM` | 6 |  |  |
| `verso.extensions.verification.weights.high` | `VERSO_VERIFY_WEIGHT_HIGH` | 25 |  |  |
| `verso.extensions.verification.medium_score` | `VERSO_VERIFY_MEDIUM_SCORE` | 6 |  | Scores from which a plugin counts as medium or high risk. Any high finding also makes it high risk. |
| `verso.extensions.verification.high_score` | `VERSO_VERIFY_HIGH_SCORE` | 25 |  |  |
| `verso.extensions.verification.max_file_kb` | `VERSO_VERIFY_MAX_FILE_KB` | 1024 | KB | Biggest PHP, JavaScript or Blade file that is scanned, in kilobytes. Bigger files are reported. |
| `verso.extensions.verification.encoded_string_length` | `VERSO_VERIFY_ENCODED_STRING_LENGTH` | 400 | characters | String literals at least this long that look like base64 or hex count as encoded data. |
| `verso.extensions.verification.script_extensions` |  | js,mjs |  | File extensions scanned as JavaScript. Blade views are scanned as Blade and PHP. |
| `verso.extensions.verification.reports_kept` | `VERSO_VERIFY_REPORTS_KEPT` | 10 | items | Reports kept for each plugin. Older ones are deleted. |
| `verso.extensions.verification.sandbox.enabled` | `VERSO_VERIFY_SANDBOX` | true |  | Boot the plugin in a separate PHP process with a temporary SQLite database. |
| `verso.extensions.verification.sandbox.max_output` | `VERSO_VERIFY_SANDBOX_MAX_OUTPUT` | 4000 | items | Most characters of sandbox output kept in a report. |
| `verso.updates.feed_url` | `VERSO_UPDATES_FEED_URL` | https://api.github.com/repos/victorag… |  | Release feed. The default is the GitHub releases API of Verso. Empty turns update checks off. |
| `verso.updates.feed_timeout` | `VERSO_UPDATES_FEED_TIMEOUT` | 15 | seconds | Seconds to wait for the release feed. |
| `verso.updates.cache_ttl` | `VERSO_UPDATES_CACHE_TTL` | 172800 | seconds | Seconds to keep the result of a check. The scheduled check refreshes it every day. |
| `verso.updates.cache_key` | `VERSO_UPDATES_CACHE_KEY` | verso.updates.check |  | Cache key that holds the result of the last check. |
| `verso.updates.check_schedule` | `VERSO_UPDATES_CHECK_SCHEDULE` | 17 4 * * * |  | When (cron expression) the scheduled check runs. |
| `verso.updates.asset_name` | `VERSO_UPDATES_ASSET_NAME` | verso-{version}.zip |  | Name of the zip asset of a release. {version} is the version without the "v". |
| `verso.updates.checksum_suffix` | `VERSO_UPDATES_CHECKSUM_SUFFIX` | .sha256 |  | Suffixes of the checksum and signature assets, added to the zip name. |
| `verso.updates.signature_suffix` | `VERSO_UPDATES_SIGNATURE_SUFFIX` | .sig |  |  |
| `verso.updates.security_marker` | `VERSO_UPDATES_SECURITY_MARKER` | [security] |  | A release whose title or notes contain this word is a security release. |
| `verso.updates.download_schemes` |  | https |  | URL schemes allowed for package downloads. |
| `verso.updates.keep_paths` |  | .env,storage,plugins,themes,public/up… |  | Paths (relative to the install folder) an update never touches. |
| `verso.updates.replace_paths` |  | themes/default,themes/ashgrove |  | Paths inside kept paths that an update still replaces. |
| `verso.updates.maintenance_retry` | `VERSO_UPDATES_MAINTENANCE_RETRY` | 60 | seconds | Seconds visitors are told to wait while the site is in maintenance mode. |
| `verso.updates.health_check.base_url` | `VERSO_UPDATES_HEALTH_URL` |  |  | Site address checked after an update. Empty uses APP_URL. |
| `verso.updates.health_check.timeout` | `VERSO_UPDATES_HEALTH_TIMEOUT` | 20 | seconds | Seconds to wait for each health check request. |
| `verso.updates.poll_interval` | `VERSO_UPDATES_POLL_INTERVAL` | 1500 | ms | Milliseconds between progress refreshes on the Updates page. |
| `verso.updates.log_limit` | `VERSO_UPDATES_LOG_LIMIT` | 20 | items | Updates listed in the log on the Updates page. |
| `verso.updates.run_after_response` | `VERSO_UPDATES_RUN_AFTER_RESPONSE` | true |  | Run a started update after the response is sent (no queue worker needed). Off sends it to the queue. |
| `verso.updates.queue` | `VERSO_UPDATES_QUEUE` | null |  |  |
| `verso.api.enabled` | `VERSO_API_ENABLED` | true |  | Turn the public JSON API on or off. |
| `verso.api.prefix` | `VERSO_API_PREFIX` | api/v1 |  | URL prefix of the public JSON API. |
| `verso.importer.disk` | `VERSO_IMPORT_DISK` | local |  | Disk and folder where uploaded WordPress export files are kept. |
| `verso.importer.directory` | `VERSO_IMPORT_DIRECTORY` | imports |  |  |
| `verso.importer.media_disk` | `VERSO_IMPORT_MEDIA_DISK` | public |  | Disk and folder for downloaded images and attachments. |
| `verso.importer.media_directory` | `VERSO_IMPORT_MEDIA_DIRECTORY` | uploads |  |  |
| `verso.importer.download_attachments` | `VERSO_IMPORT_DOWNLOAD_ATTACHMENTS` | true |  | Download attachments and featured images from the old site. |
| `verso.importer.download_retry_delay` | `VERSO_IMPORT_DOWNLOAD_RETRY_DELAY` | 500 | ms |  |
| `verso.importer.skip_post_types` |  | revision,nav_menu_item,custom_css,cus… |  | WordPress post types that are never imported. |
| `verso.importer.skip_statuses` |  | auto-draft,trash |  | WordPress statuses that are never imported. |
| `verso.importer.author_role` | `VERSO_IMPORT_AUTHOR_ROLE` | author |  | Role given to imported authors and the length of their random password. |
| `verso.importer.password_length` | `VERSO_IMPORT_PASSWORD_LENGTH` | 32 | characters |  |
| `verso.importer.fallback_email_domain` | `VERSO_IMPORT_FALLBACK_EMAIL_DOMAIN` | imported.invalid |  | Email domain for imported authors that have no email address. |
| `verso.importer.seo_separator` | `VERSO_IMPORT_SEO_SEPARATOR` | - |  | Text used for the %%sep%% and %sep% variables in Yoast and Rank Math titles. |
| `verso.importer.date_redirects` | `VERSO_IMPORT_DATE_REDIRECTS` | true |  | Also redirect "/2024/05/17/slug" and "/2024/05/slug" style URLs. |
| `verso.importer.report_message_limit` | `VERSO_IMPORT_REPORT_MESSAGE_LIMIT` | 200 | items | Most warnings kept in an import report. |
| `verso.importer.queue` | `VERSO_IMPORT_QUEUE` | null |  | Queue settings for the import job (timeout in seconds). |
| `verso.importer.poll_interval` | `VERSO_IMPORT_POLL_INTERVAL` | 2000 | ms | How often the status page refreshes (ms) and how many past imports it lists. |
| `verso.importer.recent_limit` | `VERSO_IMPORT_RECENT_LIMIT` | 10 | items |  |
| `verso.themes.fallback` | `VERSO_FALLBACK_THEME` | default |  | Theme used when the active one is missing. Its views are the fallback for every theme. |
| `verso.themes.home_content_type` | `VERSO_HOME_CONTENT_TYPE` | post |  | Content type listed on the home page and in the RSS feed. |
| `verso.themes.nav_pages` | `VERSO_NAV_PAGES` | 6 |  | Most pages listed in the site navigation of the default theme. |
| `verso.themes.search_min_length` | `VERSO_SEARCH_MIN_LENGTH` | 2 | characters | Shortest and longest search text accepted on /search. |
| `verso.themes.assets.prefix` | `VERSO_THEME_ASSETS_PREFIX` | theme-assets |  | Files a theme may serve from its assets folder (fonts and images), and how long browsers keep them. |
| `verso.themes.assets.extensions` |  | woff2,woff,svg,png,jpg,jpeg,webp,avif… |  |  |
| `verso.themes.assets.cache_seconds` | `VERSO_THEME_ASSETS_CACHE` | 31536000 | seconds |  |
| `verso.themes.toc_min_headings` | `VERSO_THEME_TOC_MIN_HEADINGS` | 3 |  | Blog themes: fewest h2 headings before a post shows a table of contents, and how many related posts to show. |
| `verso.themes.related_posts` | `VERSO_THEME_RELATED_POSTS` | 3 |  |  |
| `verso.themes.category_strip` | `VERSO_THEME_CATEGORY_STRIP` | 8 |  | Categories listed in the category strip of blog themes. |
| `verso.themes.setting_text_max` | `VERSO_THEME_SETTING_TEXT_MAX` | 500 | characters | Longest text a theme setting of type "text" may hold, in characters. |
| `verso.themes.pattern_max_kb` | `VERSO_THEME_PATTERN_MAX_KB` | 256 | KB | Largest block pattern file (patterns/*.json) read from a theme, in kilobytes. |
| `verso.themes.template_debug.enabled` | `VERSO_TEMPLATE_DEBUG` | true |  | Template debug panel for users who manage themes: add ?verso-template-debug=1 to a public URL. |
| `verso.themes.template_debug.query` | `VERSO_TEMPLATE_DEBUG_QUERY` | verso-template-debug |  |  |
| `verso.manifest_cache.enabled` | `VERSO_MANIFEST_CACHE` | true |  | Keep theme and plugin manifests (and the plugin list from Composer) in the cache. |
| `verso.manifest_cache.store` | `VERSO_MANIFEST_CACHE_STORE` | file |  | Cache store for manifests. "file" is fast and needs no database query. Empty uses the default store. |
| `verso.manifest_cache.ttl` | `VERSO_MANIFEST_CACHE_TTL` | 604800 | seconds | Seconds to keep an entry. Changed files get a new key at once, so this only cleans up old entries. |
| `verso.manifest_cache.prefix` | `VERSO_MANIFEST_CACHE_PREFIX` | verso.manifest |  | Prefix of the manifest cache keys. |
| `verso.shortcodes.enabled` | `VERSO_SHORTCODES` | true |  | Turn shortcodes like [form slug="contact"] into the HTML of their block. |
| `verso.shortcodes.max_depth` | `VERSO_SHORTCODES_MAX_DEPTH` | 3 | items | How deep enclosing shortcodes can be nested inside each other. |
| `verso.shortcodes.max_per_text` | `VERSO_SHORTCODES_MAX_PER_TEXT` | 50 | items | Most shortcodes read from one text. Extra ones stay as plain text. |
| `verso.shortcodes.max_attribute_length` | `VERSO_SHORTCODES_MAX_ATTRIBUTE_LENGTH` | 500 | characters | Longest value of one shortcode attribute, in characters. |
| `verso.shortcodes.places` | `VERSO_SHORTCODES_PLACES` | excerpt,custom_field,theme_setting,fo… |  | Text places (besides entry blocks) where shortcodes run: excerpt, custom_field, theme_setting, form_email, footer. |
| `verso.shortcodes.warning_capability` | `VERSO_SHORTCODES_WARNING_CAPABILITY` | edit_any_entry |  | Capability that shows the warning box for broken shortcodes on the public site. |
| `verso.shortcodes.used_in_limit` | `VERSO_SHORTCODES_USED_IN_LIMIT` | 50 | items | Most entries shown in a "Used in" list in the admin. |
| `verso.shortcodes.targeted_invalidation` | `VERSO_SHORTCODES_TARGETED_INVALIDATION` | true |  | Clear only the cached pages that embed a changed item. False clears every cached page. |
| `verso.block_cache.store` | `VERSO_BLOCK_CACHE_STORE` | file |  | Cache store for block HTML. "file" needs no database query per entry. Empty uses the default store. |
| `verso.block_cache.prefix` | `VERSO_BLOCK_CACHE_PREFIX` | verso.blocks |  | Prefix of the block cache keys. |
| `verso.page_cache.store` | `VERSO_PAGE_CACHE_STORE` | null |  | Cache store for pages. Null uses the default store. "file" is a good choice. |
| `verso.page_cache.prefix` | `VERSO_PAGE_CACHE_PREFIX` | verso.page |  | Prefix for every page cache key. |
| `verso.page_cache.allowed_query` |  | page |  | Query parameters that can be part of a cached URL. Other parameters skip the cache. |
| `verso.page_cache.bypass_cookie` | `VERSO_PAGE_CACHE_BYPASS_COOKIE` | verso_nocache |  | Cookie set for signed in visitors and pending notices. Visitors with it never get a stored page. |
| `verso.page_cache.cache_control` | `VERSO_PAGE_CACHE_CONTROL` | public, max-age=0, must-revalidate | seconds | Cache-Control header of pages served from the cache. Browsers check them again with ETag and Last-Modified. |
| `verso.admin_bar.enabled` | `VERSO_ADMIN_BAR` | true |  | Show the admin bar on top of public pages for users who may see it. |
| `verso.admin_bar.height` | `VERSO_ADMIN_BAR_HEIGHT` | 36 | pixels | Height of the bar in pixels. Themes read it as the --verso-adminbar-height CSS variable. |
| `verso.benchmark.entries` | `VERSO_BENCHMARK_ENTRIES` | 500 |  | Posts seeded into the temporary database by "php artisan verso:benchmark". |
| `verso.benchmark.requests` | `VERSO_BENCHMARK_REQUESTS` | 50 |  | Measured requests per route and mode, and requests sent first to warm up caches. |
| `verso.benchmark.warmup` | `VERSO_BENCHMARK_WARMUP` | 5 |  |  |
| `verso.benchmark.percentile` | `VERSO_BENCHMARK_PERCENTILE` | 95 | percent | Percentile compared with the budgets, like 95 for p95. |
| `verso.benchmark.cached_budget_ms` | `VERSO_BENCHMARK_CACHED_BUDGET_MS` | 50 | ms | Most milliseconds a page may take with and without the page cache. 0 turns a budget off. |
| `verso.benchmark.uncached_budget_ms` | `VERSO_BENCHMARK_UNCACHED_BUDGET_MS` | 250 | ms |  |
| `verso.system.audit_limit` | `VERSO_SYSTEM_AUDIT_LIMIT` | 30 | items | Changes listed in the audit log of System settings. |
| `verso.access.audit_limit` | `VERSO_ACCESS_AUDIT_LIMIT` | 30 | items | Role and access changes listed on the roles pages. |
| `verso.access.members_limit` | `VERSO_ACCESS_MEMBERS_LIMIT` | 50 | users | People listed on a role page. |
| `verso.access.compare_limit` | `VERSO_ACCESS_COMPARE_LIMIT` | 4 | roles | Roles shown side by side on the compare page. |
| `verso.fields.json_folder` | `VERSO_FIELDS_JSON_FOLDER` | fields |  | Folder inside a theme or plugin with field group JSON files. |
| `verso.fields.text_max` | `VERSO_FIELDS_TEXT_MAX` | 255 | characters | Longest value of text, email and url fields, in characters (a field can set a lower max). |
| `verso.fields.textarea_max` | `VERSO_FIELDS_TEXTAREA_MAX` | 20000 | characters | Longest value of textarea and rich text fields, in characters. |
| `verso.fields.max_rows` | `VERSO_FIELDS_MAX_ROWS` | 100 | items | Most rows a repeater can hold and most images in a gallery, when the field sets no max. |
| `verso.fields.max_depth` | `VERSO_FIELDS_MAX_DEPTH` | 4 | items | How deep repeaters and groups can be nested. |
| `verso.fields.max_fields` | `VERSO_FIELDS_MAX_FIELDS` | 200 | items | Most fields one field group can hold, counting nested fields. |
| `verso.fields.search_limit` | `VERSO_FIELDS_SEARCH_LIMIT` | 20 | items | Results returned by the relation search in the entry editor. |
| `verso.fields.image_sizes` | `VERSO_FIELDS_IMAGE_SIZES` | (min-width: 1024px) 960px, 100vw | pixels | Image sizes attribute used by field images in themes and the API. |
| `verso.fields.date_format` | `VERSO_FIELDS_DATE_FORMAT` | j F Y |  | PHP date formats used when @field prints a date or a date time. |
| `verso.fields.date_time_format` | `VERSO_FIELDS_DATE_TIME_FORMAT` | j F Y, H:i |  |  |
| `verso.demo.media_folder` | `VERSO_DEMO_MEDIA_FOLDER` | demo |  | Folder on the media disk where the demo seeder writes its images. |
| `verso.demo.image_width` | `VERSO_DEMO_IMAGE_WIDTH` | 960 | pixels | Size and JPEG quality of the demo images. Small images keep seeding fast. |
| `verso.demo.image_height` | `VERSO_DEMO_IMAGE_HEIGHT` | 640 | pixels |  |
| `verso.demo.image_quality` | `VERSO_DEMO_IMAGE_QUALITY` | 78 | percent |  |
| `verso.demo.avatar_size` | `VERSO_DEMO_AVATAR_SIZE` | 320 | pixels | Width and height of the square demo author photos. |
| `verso.demo.theme` | `VERSO_DEMO_THEME` | ashgrove |  | Theme the demo seeder turns on. The install wizard recommends it too. |
| `verso.demo.plugins` | `VERSO_DEMO_PLUGINS` | reading-time,forms |  | Plugins the demo seeder turns on (comma separated slugs). |
| `verso.easy.guided_start_after_install` | `VERSO_EASY_GUIDED_START_AFTER_INSTALL` | true |  | Send a new admin to the guided start ("What is your site for?") right after the install. |
| `verso.easy.media_folder` | `VERSO_EASY_MEDIA_FOLDER` | easy |  | Folder on the media disk for the sample pictures of starter sites. |
| `verso.easy.image_width` | `VERSO_EASY_IMAGE_WIDTH` | 1200 | pixels | Size and JPEG quality of the sample pictures. Portraits are square. |
| `verso.easy.image_height` | `VERSO_EASY_IMAGE_HEIGHT` | 800 | pixels |  |
| `verso.easy.image_quality` | `VERSO_EASY_IMAGE_QUALITY` | 80 | percent |  |
| `verso.easy.portrait_size` | `VERSO_EASY_PORTRAIT_SIZE` | 480 | pixels |  |
| `verso.easy.wide_templates` | `VERSO_EASY_WIDE_TEMPLATES` | full-width,wide |  | Page templates that give starter pages the full width. The first match of the active theme wins. |
| `verso.easy.contact_form` | `VERSO_EASY_CONTACT_FORM` | contact |  | Slug of the form the contact section shows when the Forms plugin is on. |
| `verso.easy.map.embed_url` | `VERSO_EASY_MAP_EMBED_URL` | https://www.openstreetmap.org/export/embed.html |  | OpenStreetMap page that shows a map in a frame, and the page linked under it. |
| `verso.easy.map.link_url` | `VERSO_EASY_MAP_LINK_URL` | https://www.openstreetmap.org/ |  |  |
| `verso.easy.map.default_zoom` | `VERSO_EASY_MAP_ZOOM` | 15 |  | Zoom of a new map (1 shows the world, 19 one street). |
| `verso.easy.map.height` | `VERSO_EASY_MAP_HEIGHT` | 360 | pixels | Height of the map frame. |
| `verso.easy.preview_delay` | `VERSO_EASY_PREVIEW_DELAY` | 350 | ms | "Make it yours": wait after the last pick before the preview reloads. |
| `verso.easy.palette.sample_size` | `VERSO_EASY_PALETTE_SAMPLE_SIZE` | 96 | pixels | The logo is shrunk to this size on its longest side before its colors are read. |
| `verso.easy.palette.max_colors` | `VERSO_EASY_PALETTE_MAX_COLORS` | 6 | items | Most main colors kept from the logo. |
| `verso.easy.page_editor.render_delay` | `VERSO_EASY_PAGE_RENDER_DELAY` | 250 | ms | Visual page editor: wait after a change before the page is drawn again. |
| `verso.easy.page_editor.undo_steps` | `VERSO_EASY_PAGE_UNDO_STEPS` | 100 | items | Undo steps kept in the page editor. |
| `verso.easy.page_editor.undo_seconds` | `VERSO_EASY_PAGE_UNDO_SECONDS` | 8 | seconds | How long the "Undo" button of a removed section stays. |
| `verso.easy.page_editor.autosave_delay` | `VERSO_EASY_PAGE_AUTOSAVE_DELAY` | 2000 | ms | Unsaved changes are kept as a draft this long after the last change. |
| `verso.easy.page_editor.versions` | `VERSO_EASY_PAGE_VERSIONS` | 20 | items | Earlier versions listed in "Restore this page". |
| `verso.easy.section_pictures` | `VERSO_EASY_SECTION_PICTURES` | 6 | items | A new picture section gets up to this many of the latest pictures of the media library. |
| `verso.easy.simple_mode.default` | `VERSO_EASY_SIMPLE_MODE_DEFAULT` | false |  | Simple admin mode on sites that never saved a default. |
| `verso.easy.simple_mode.new_sites` | `VERSO_EASY_SIMPLE_MODE_NEW_SITES` | true |  | Simple admin mode saved as the site default by the install and by the first starter site. |
| `verso.easy.launch.min_pages` | `VERSO_EASY_LAUNCH_MIN_PAGES` | 3 | items | Launch checklist: the "pages" step is done after a starter site or this many published pages. |
| `verso.easy.privacy.slug` | `VERSO_EASY_PRIVACY_SLUG` | privacy-policy |  | Address of the page made by "Create a privacy page". A published page with this address also counts. |
| `verso.easy.privacy.menu_location` | `VERSO_EASY_PRIVACY_MENU_LOCATION` | footer |  | Menu location that gets a link to a new privacy page. Empty for none. |
| `verso.easy.details.phone_max` | `VERSO_EASY_DETAILS_PHONE_MAX` | 40 | characters | Longest values of "Your site details". |
| `verso.easy.details.address_max` | `VERSO_EASY_DETAILS_ADDRESS_MAX` | 500 | characters |  |
| `verso.easy.details.hours_max` | `VERSO_EASY_DETAILS_HOURS_MAX` | 255 | characters |  |
| `verso.easy.help.videos` | `VERSO_EASY_HELP_VIDEOS` |  |  | Help panel videos by topic key, like `go-live=https://videos.example.com/go-live.mp4` (comma separated pairs). |
| `forms.capability` | `FORMS_CAPABILITY` | forms.manage |  | Capability needed to build forms. |
| `forms.messages_capability` | `FORMS_MESSAGES_CAPABILITY` | forms.messages |  | Capability needed to read, export and delete form messages. |
| `forms.max_options` | `FORMS_MAX_OPTIONS` | 50 | items | Most options one select, radio or checkbox field may have. |
| `forms.text_max` | `FORMS_TEXT_MAX` | 255 | characters | Longest text accepted by a text field and by a textarea when the form does not set a limit. |
| `forms.upload_types` | `FORMS_UPLOAD_TYPES` | pdf,jpg,jpeg,png,webp,gif,txt,csv,doc… |  |  |
| `forms.disk` | `FORMS_DISK` | local |  | Disk and folder for uploaded files. Use a private disk: files are only served to the admin. |
| `forms.folder` | `FORMS_FOLDER` | forms |  |  |
| `forms.retention_days` | `FORMS_RETENTION_DAYS` | 365 | days | Days a submission is kept. 0 keeps submissions forever. The admin can change it. |
| `forms.prune_schedule` | `FORMS_PRUNE_SCHEDULE` | 15 3 * * * |  | When old submissions are deleted (a cron expression). |
| `forms.queue` | `FORMS_QUEUE` |  |  | Queue for notification emails. Empty uses the default queue. |
| `forms.spam.honeypot_field` | `FORMS_HONEYPOT_FIELD` | company_website |  | Hidden field that only bots fill in. |
| `forms.spam.min_seconds` | `FORMS_MIN_SECONDS` | 3 | seconds | A form sent faster than this (seconds after the visitor started typing) is treated as a bot. |
| `forms.spam.max_links` | `FORMS_MAX_LINKS` | 3 | items | A message with this many links or more is marked as spam. 0 turns the check off. |
| `forms.spam.blocked_words` | `FORMS_BLOCKED_WORDS` | viagra,casino bonus,crypto giveaway,s… |  | Words that mark a message as spam (comma separated). The admin can change the list. |
| `forms.spam.keep_bot_messages` | `FORMS_KEEP_BOT_MESSAGES` | false |  | Keep messages caught by the honeypot or the time trap as spam (true) or drop them (false). |
| `forms.captcha.turnstile.script` | `FORMS_TURNSTILE_SCRIPT` | https://challenges.cloudflare.com/tur… |  |  |
| `forms.captcha.turnstile.verify_url` | `FORMS_TURNSTILE_VERIFY_URL` | https://challenges.cloudflare.com/tur… |  |  |
| `forms.captcha.turnstile.response_field` |  | cf-turnstile-response |  |  |
| `forms.captcha.turnstile.widget_class` |  | cf-turnstile |  |  |
| `forms.captcha.hcaptcha.script` | `FORMS_HCAPTCHA_SCRIPT` | https://js.hcaptcha.com/1/api.js |  |  |
| `forms.captcha.hcaptcha.verify_url` | `FORMS_HCAPTCHA_VERIFY_URL` | https://api.hcaptcha.com/siteverify |  |  |
| `forms.captcha.hcaptcha.response_field` |  | h-captcha-response |  |  |
| `forms.captcha.hcaptcha.widget_class` |  | h-captcha |  |  |
| `newsletter.batch_size` | `NEWSLETTER_BATCH_SIZE` | 50 | pixels | Emails sent by one queued job. |
| `newsletter.batch_delay` | `NEWSLETTER_BATCH_DELAY` | 60 |  | Seconds between two batches, to stay under the limits of the mail provider. |
| `newsletter.from_address` | `NEWSLETTER_FROM_ADDRESS` |  |  | Sender of campaigns. Empty uses the mail.from config. |
| `newsletter.from_name` | `NEWSLETTER_FROM_NAME` |  |  |  |
| `newsletter.track_opens` | `NEWSLETTER_TRACK_OPENS` | false |  | Open and click tracking for new campaigns. Off by default to respect privacy. |
| `newsletter.track_clicks` | `NEWSLETTER_TRACK_CLICKS` | false |  |  |
| `newsletter.queue` | `NEWSLETTER_QUEUE` |  |  | Queue used for sending campaigns. Empty uses the default queue. |
| `newsletter.heartbeat_schedule` | `NEWSLETTER_HEARTBEAT_SCHEDULE` | */5 * * * * |  | How often a heartbeat job is put on the queue (a cron expression). A worker that runs it proves campaigns can be sent. |
| `shop.routes.product_base` | `SHOP_PRODUCT_BASE` | shop |  | Public URL parts. Products live under "/{product_base}/{slug}". |
| `shop.routes.shop` | `SHOP_PATH` | shop |  |  |
| `shop.routes.cart` | `SHOP_CART_PATH` | cart |  |  |
| `shop.routes.checkout` | `SHOP_CHECKOUT_PATH` | checkout |  |  |
| `shop.routes.category_base` | `SHOP_CATEGORY_BASE` | product-category |  |  |
| `shop.routes.stripe_webhook` | `SHOP_STRIPE_WEBHOOK_PATH` | shop/webhooks/stripe |  |  |
| `shop.cart_session_key` | `SHOP_CART_SESSION_KEY` | shop.cart |  | Session key that holds the cart. |
| `shop.order_prefix` | `SHOP_ORDER_PREFIX` | ORD- |  | Text put in front of the order id, like "ORD-00042". |
| `shop.order_digits` | `SHOP_ORDER_DIGITS` | 5 | items | Digits of the order id in the order number (zeros are added on the left). |
| `shop.pending_orders.gateway_minutes.bank_transfer` | `SHOP_CANCEL_BANK_TRANSFER_AFTER` | 10080 |  | Longer or shorter waits for single gateways, in minutes. Bank transfers take days. |
| `shop.pending_orders.schedule` | `SHOP_CANCEL_PENDING_SCHEDULE` | */15 * * * * |  | How often the check runs (a cron expression). |
| `shop.pending_orders.chunk` | `SHOP_CANCEL_PENDING_CHUNK` | 100 | items | Orders loaded at once while cancelling. |
| `shop.currencies` | `SHOP_CURRENCIES` | EUR,USD,GBP,CHF,CAD,AUD,SEK,NOK,DKK,P… |  | Currencies the admin can pick from. |
| `shop.zero_decimal_currencies` |  | BIF,CLP,DJF,GNF,JPY,KMF,KRW,MGA,PYG,R… |  | Currencies without minor units (amounts are stored as whole units). |
| `shop.defaults.currency` | `SHOP_CURRENCY` | EUR |  | Values used until the shop settings are saved in the admin. |
| `shop.defaults.tax_rate` | `SHOP_TAX_RATE` | 0 |  |  |
| `shop.defaults.tax_shipping` | `SHOP_TAX_SHIPPING` | true |  |  |
| `shop.defaults.shipping_rate` | `SHOP_SHIPPING_RATE` | 0 |  |  |
| `shop.defaults.free_shipping_over` | `SHOP_FREE_SHIPPING_OVER` | null |  |  |
| `shop.defaults.notify_email` | `SHOP_NOTIFY_EMAIL` | null |  |  |
| `shop.defaults.bank_transfer_enabled` | `SHOP_BANK_TRANSFER_ENABLED` | true |  |  |
| `shop.defaults.bank_transfer_instructions` | `SHOP_BANK_TRANSFER_INSTRUCTIONS` | Please send the total to our bank acc… |  |  |
| `shop.defaults.stripe_enabled` | `SHOP_STRIPE_ENABLED` | false |  |  |
| `shop.stripe.api_url` | `SHOP_STRIPE_API_URL` | https://api.stripe.com |  | Base URL of the Stripe REST API. |
| `shop.stripe.retry_delay` | `SHOP_STRIPE_RETRY_DELAY` | 300 | ms | Milliseconds to wait between tries. |
| `analytics.enabled` | `ANALYTICS_ENABLED` | true |  | Turn the beacon on public pages on or off. |
| `analytics.endpoint` | `ANALYTICS_ENDPOINT` | _verso/collect |  | Path that receives the beacon. It must not clash with a page slug. |
| `analytics.capability` | `ANALYTICS_CAPABILITY` | analytics.view |  | Capability needed to see the stats. Changing the analytics settings also needs `manage_settings`. |
| `analytics.breakpoints.tablet` | `ANALYTICS_TABLET_WIDTH` | 768 | pixels | Screen widths (in CSS pixels) where the tablet and desktop buckets start. |
| `analytics.breakpoints.desktop` | `ANALYTICS_DESKTOP_WIDTH` | 1024 | pixels |  |
| `analytics.defaults.retention_days` | `ANALYTICS_RETENTION_DAYS` | 30 | days | Default values of the settings shown on the analytics page. |
| `analytics.defaults.ignore_roles` | `ANALYTICS_IGNORE_ROLES` | admin |  | Logged-in users with one of these roles get no beacon (comma separated role names). |
| `analytics.retention_limits.min` | `ANALYTICS_RETENTION_MIN` | 1 |  | Smallest and largest number of days raw events can be kept. |
| `analytics.retention_limits.max` | `ANALYTICS_RETENTION_MAX` | 365 |  |  |
| `analytics.ranges` |  | 7,30,90 |  | Date ranges (in days) offered on the analytics page, and the one shown first. |
| `analytics.default_range` | `ANALYTICS_DEFAULT_RANGE` | 30 |  |  |
| `analytics.rollup_at` | `ANALYTICS_ROLLUP_AT` | 00:15 |  | Time of day (site time zone) when yesterday's events are rolled up. |
| `analytics.sensitive_query_keys` |  | token,signature,expires,key,code,emai… |  | Pages opened with one of these query keys are private links, so they are never counted. |
| `analytics.token_min_length` | `ANALYTICS_TOKEN_MIN_LENGTH` | 20 | characters | A path part this long made of letters and digits (with both) is treated as a secret token. |
| `analytics.exclude_paths` | `ANALYTICS_EXCLUDE_PATHS` | /admin,/api,/install,/preview,… |  | Private paths that are never counted. "/admin" also covers everything under it, "*" is a wildcard. The env var adds more (comma separated). |
| `ai-assistant.anthropic.url` | `AI_ASSISTANT_ANTHROPIC_URL` | https://api.anthropic.com/v1/messages |  | Messages API endpoint. |
| `ai-assistant.anthropic.version` | `AI_ASSISTANT_ANTHROPIC_VERSION` | 2026-10-21 |  | Value of the anthropic-version header. |
| `ai-assistant.anthropic.default_model` | `AI_ASSISTANT_DEFAULT_MODEL` | claude-sonnet-5-5 |  | Model used when the settings page has no model yet. |
| `ai-assistant.anthropic.effort` | `AI_ASSISTANT_EFFORT` | low |  | How hard the model thinks (low, medium, high). Empty uses the model default. |
| `ai-assistant.anthropic.fallbacks` | `AI_ASSISTANT_FALLBACKS` | default |  | Server side fallback when the model declines a request. Empty turns it off. |
| `ai-assistant.anthropic.fallbacks_beta` | `AI_ASSISTANT_FALLBACKS_BETA` | server-side-fallback-2026-07-01 |  | Beta header that turns on server side fallbacks. |
| `ai-assistant.anthropic.fallback_models` | `AI_ASSISTANT_FALLBACK_MODELS` | claude-sonnet-5-5,claude-opus-5-5,cla… |  | Models that accept server side fallbacks. Other models are called without them. |
| `ai-assistant.defaults.max_tokens` | `AI_ASSISTANT_MAX_TOKENS` | 4000 | items | Most tokens one answer can use, when the settings page has no value yet. |
| `ai-assistant.defaults.rate_limit` | `AI_ASSISTANT_RATE_LIMIT` | 30 | items | Requests one user can make in each rate limit window, when the settings page has no value yet. |
| `ai-assistant.limits.max_tokens_ceiling` | `AI_ASSISTANT_MAX_TOKENS_CEILING` | 64000 | items | Highest max tokens value the settings page accepts. |
| `ai-assistant.limits.max_translation_segments` | `AI_ASSISTANT_MAX_TRANSLATION_SEGMENTS` | 500 | items | Most text pieces sent in one translation. |
| `ai-assistant.limits.max_section_pieces` | `AI_ASSISTANT_MAX_SECTION_PIECES` | 60 | items | Most text pieces of one section written by the page editor helper. |
| `ai-assistant.limits.max_business_chars` | `AI_ASSISTANT_MAX_BUSINESS_CHARS` | 1000 | characters | Longest business description the helper keeps. |
| `ai-assistant.limits.max_section_images` | `AI_ASSISTANT_MAX_SECTION_IMAGES` | 12 | items | Most pictures of one section described in one go by the page editor helper. |
| `ai-assistant.targets.excerpt_words` | `AI_ASSISTANT_EXCERPT_WORDS` | 40 |  | Words the model aims for in an excerpt. |
| `ai-assistant.targets.seo_title_chars` | `AI_ASSISTANT_SEO_TITLE_CHARS` | 60 | characters | Characters the model aims for in the SEO title and meta description. |
| `ai-assistant.targets.seo_description_chars` | `AI_ASSISTANT_SEO_DESCRIPTION_CHARS` | 155 | characters |  |
| `ai-assistant.targets.alt_chars` | `AI_ASSISTANT_ALT_CHARS` | 125 | characters | Characters the model aims for in image alt text. |
| `ai-assistant.targets.headline_count` | `AI_ASSISTANT_HEADLINE_COUNT` | 5 | items | How many headlines the page editor helper suggests. |
| `ai-assistant.targets.headline_chars` | `AI_ASSISTANT_HEADLINE_CHARS` | 80 | characters | Longest suggested headline. |
| `ai-assistant.translation.content_types` |  | paragraph,heading,list,quote |  | Block types whose content is translated. |
| `ai-assistant.translation.attrs` |  | alt,caption,label,citation |  | Block attributes that hold text to translate. |
| `ai-assistant.image_types` |  | image/jpeg,image/png,image/gif,image/… |  | Image types the vision API accepts. |
| `ai-assistant.usage_days` | `AI_ASSISTANT_USAGE_DAYS` | 30 | days | Days shown on the usage page. |
| `memberships.join_url` | `MEMBERSHIPS_JOIN_URL` |  |  | Link of the "Join" button in the teaser. Empty hides the button. The admin can change it in Settings. |
| `memberships.teaser_message` | `MEMBERSHIPS_TEASER_MESSAGE` | This content is for members only. |  | Text of the teaser shown in place of members only content. |
| `memberships.bypass_capability` | `MEMBERSHIPS_BYPASS_CAPABILITY` | edit_any_entry |  | Capability that always sees restricted content (editors and admins by default). |
| `memberships.expire_at` | `MEMBERSHIPS_EXPIRE_AT` | 00:15 |  | Time of day when expired memberships are closed. |
| `reading-time.auto_insert` | `READING_TIME_AUTO_INSERT` | false |  | Add the reading time on top of every entry that has no reading-time block. |
| `reading-time.label` | `READING_TIME_LABEL` |  |  | Text shown in the badge. ":minutes" is replaced by the number. Empty uses the translated "N min read". |

## Adding settings from a plugin

```php
public function register(): void
{
    $this->mergeConfigFrom($this->path('config/bookshop.php'), 'bookshop');
    $this->registerSystemSettings('Bookshop', 'shop',
        new ConfigParameter('bookshop.per_page', $this->systemGroup(), 'Books per page', 'Books on one page of the catalogue.',
            unit: Unit::Items, min: 1, max: 200, env: 'BOOKSHOP_PER_PAGE'),
        new ConfigParameter('bookshop.cover_max_kb', $this->systemGroup(), 'Largest cover image',
            unit: Unit::Kilobytes, min: 64, max: 20480, limits: ServerLimit::uploads()),
    );
}
```

Core parameters use the same `ConfigRegistry::add()`. A key may be declared only once. Declare the env var name so the admin can tell when the environment sets the value, and `apply` actions when the value is read once by long running processes or kept in a cache.

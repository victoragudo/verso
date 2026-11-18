<?php

return [

    // Verso version, read from the VERSION file at the root. Plugins and updates use it.
    'version' => is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '0.0.0',

    'editor' => [
        // URL schemes allowed in links, images, videos and buttons. Relative URLs are always allowed.
        'allowed_url_schemes' => ['http', 'https', 'mailto', 'tel'],

        // Capability a user needs to save raw HTML blocks.
        'html_capability' => env('VERSO_EDITOR_HTML_CAPABILITY', 'manage_settings'),

        // Most blocks one entry can hold, counting nested blocks.
        'max_blocks' => (int) env('VERSO_EDITOR_MAX_BLOCKS', 2000),

        // How deep blocks can be nested (columns inside an entry count as one level).
        'max_depth' => (int) env('VERSO_EDITOR_MAX_DEPTH', 8),

        // Longest text a plugin block text field can hold, in characters.
        'plugin_text_max' => (int) env('VERSO_EDITOR_PLUGIN_TEXT_MAX', 1000),

        // Largest screen width in pixels of each screen size used by responsive block styles.
        'breakpoints' => [
            'tablet' => (int) env('VERSO_BREAKPOINT_TABLET', 1024),
            'mobile' => (int) env('VERSO_BREAKPOINT_MOBILE', 640),
        ],

        // Capability a user needs to save custom CSS on a block.
        'custom_css_capability' => env('VERSO_EDITOR_CUSTOM_CSS_CAPABILITY', 'edit_custom_css'),

        // Longest custom CSS one block can hold, in characters.
        'custom_css_max' => (int) env('VERSO_EDITOR_CUSTOM_CSS_MAX', 2000),

        // Style presets ("Card", "Hero") people save from the Styles panel.
        'presets' => [
            // Most saved presets the site keeps.
            'max_saved' => (int) env('VERSO_STYLE_PRESETS_MAX', 100),
        ],

        // Privacy friendly players used by the embed block.
        'embed' => [
            'youtube_url' => env('VERSO_EMBED_YOUTUBE_URL', 'https://www.youtube-nocookie.com/embed/'),
            'vimeo_url' => env('VERSO_EMBED_VIMEO_URL', 'https://player.vimeo.com/video/'),
        ],
    ],

    // Theme builder layouts: templates, headers, footers and popups.
    'layouts' => [
        // Published versions kept per layout.
        'max_revisions' => (int) env('VERSO_LAYOUTS_MAX_REVISIONS', 20),
        // Most rules one layout can have.
        'max_conditions' => (int) env('VERSO_LAYOUTS_MAX_CONDITIONS', 30),
        // Words people read in a minute, for the reading time block.
        'words_per_minute' => (int) env('VERSO_READING_WORDS_PER_MINUTE', 230),
        // Most entries one query loop shows on a page.
        'query_max' => (int) env('VERSO_QUERY_LOOP_MAX', 48),
        // Most terms and authors offered as query loop filters.
        'query_choices' => (int) env('VERSO_QUERY_LOOP_CHOICES', 300),
    ],

    // Design library: synced blocks and saved patterns.
    'library' => [
        // Most saved items sent to the editor Add panel.
        'editor_items' => (int) env('VERSO_LIBRARY_EDITOR_ITEMS', 200),
        // Most items one import file can add.
        'import_max' => (int) env('VERSO_LIBRARY_IMPORT_MAX', 100),
        // Biggest import file, in kilobytes.
        'import_max_kb' => (int) env('VERSO_LIBRARY_IMPORT_MAX_KB', 2048),
        // Pages listed under "Used in" for a synced block.
        'used_in_limit' => (int) env('VERSO_LIBRARY_USED_IN_LIMIT', 50),
    ],

    'blocks' => [
        // How long browsers may keep block CSS and JavaScript files. The URL changes when a file changes.
        'asset_cache_seconds' => (int) env('VERSO_BLOCK_ASSET_CACHE_SECONDS', 31536000),
    ],

    'builder' => [
        // How many items the "Recently used" list of the Add panel keeps.
        'recent_limit' => (int) env('VERSO_BUILDER_RECENT_LIMIT', 6),

        // Most undo steps the editor keeps for one page.
        'undo_steps' => (int) env('VERSO_BUILDER_UNDO_STEPS', 100),

        // Typing in the same block within this many milliseconds is one undo step.
        'undo_merge_ms' => (int) env('VERSO_BUILDER_UNDO_MERGE_MS', 800),

        // Longest HTML, in characters, the code view can turn into blocks at once.
        'convert_max_length' => (int) env('VERSO_BUILDER_CONVERT_MAX_LENGTH', 200000),

        // Custom CSS and JavaScript for the site and for single pages.
        'custom_code' => [
            // Capability a user needs to save custom JavaScript (custom CSS uses editor.custom_css_capability).
            'js_capability' => env('VERSO_CUSTOM_JS_CAPABILITY', 'manage_settings'),
            'css_max' => (int) env('VERSO_CUSTOM_CSS_MAX', 50000),
            'js_max' => (int) env('VERSO_CUSTOM_JS_MAX', 50000),
            // How long browsers may keep a script file. The URL changes when the script changes.
            'cache_seconds' => (int) env('VERSO_CUSTOM_JS_CACHE_SECONDS', 31536000),
        ],

        // Limits of the HTML element block.
        'element' => [
            'max_attributes' => (int) env('VERSO_BUILDER_ELEMENT_MAX_ATTRIBUTES', 20),
            'max_value_length' => (int) env('VERSO_BUILDER_ELEMENT_MAX_VALUE_LENGTH', 500),
        ],

        // Roles that may use each editor feature, until an admin changes it on the "Editor settings" page.
        // Each value is a comma separated list of roles.
        'features' => [
            'add_panel' => env('VERSO_BUILDER_ADD_PANEL_ROLES', 'admin,editor,author,contributor,shop_manager'),
            'list_view' => env('VERSO_BUILDER_LIST_VIEW_ROLES', 'admin,editor,author,contributor,shop_manager'),
            'styles_panel' => env('VERSO_BUILDER_STYLES_PANEL_ROLES', 'admin,editor,author,shop_manager'),
            'element_block' => env('VERSO_BUILDER_ELEMENT_BLOCK_ROLES', 'admin,editor'),
            'code_editor' => env('VERSO_BUILDER_CODE_EDITOR_ROLES', 'admin,editor'),
            'custom_css' => env('VERSO_BUILDER_CUSTOM_CSS_ROLES', 'admin'),
            'custom_js' => env('VERSO_BUILDER_CUSTOM_JS_ROLES', 'admin'),
        ],
    ],

    'users' => [
        // Role given to new users. The first registered user is always an admin.
        'default_role' => env('VERSO_DEFAULT_ROLE', 'subscriber'),

        // Hours an invitation link stays valid.
        'invitation_expires_hours' => (int) env('VERSO_INVITATION_EXPIRES_HOURS', 72),

        // Most pending invitations listed on the users page.
        'pending_invitations_limit' => (int) env('VERSO_PENDING_INVITATIONS_LIMIT', 100),

        // Public profile on the account page, shown by themes in the author box and on the author page.
        'profile' => [
            // Longest bio, in characters.
            'bio_max' => (int) env('VERSO_PROFILE_BIO_MAX', 1000),

            // Most social profile links one person can add.
            'social_links_max' => (int) env('VERSO_PROFILE_SOCIAL_LINKS_MAX', 8),

            // Biggest profile photo upload, in kilobytes. The media upload limit still applies.
            'avatar_max_kb' => (int) env('VERSO_PROFILE_AVATAR_MAX_KB', 4096),

            // First part of the author page URL, like /author/clara-moreno.
            'author_base' => env('VERSO_AUTHOR_BASE', 'author'),
        ],

        // Columns (table => column) that point to a user. They move to another user when a user is deleted.
        'reassign_columns' => [
            'entries' => 'author_id',
            'revisions' => 'user_id',
            'media' => 'uploaded_by',
        ],
    ],

    'admin' => [
        // URL prefix for the admin area.
        'prefix' => env('VERSO_ADMIN_PREFIX', 'admin'),

        // Rows per page in admin lists.
        'per_page' => (int) env('VERSO_ADMIN_PER_PAGE', 20),

        // Rows in each short list on the admin dashboard.
        'dashboard_items' => (int) env('VERSO_ADMIN_DASHBOARD_ITEMS', 5),

        'nav' => [
            // Days a sidebar item from a newly enabled plugin shows a "New" badge, until the user opens it.
            'new_badge_days' => (int) env('VERSO_ADMIN_NAV_NEW_BADGE_DAYS', 14),

            // Most sidebar items one plugin may declare in the "admin" part of verso.json.
            'max_plugin_items' => (int) env('VERSO_ADMIN_NAV_MAX_PLUGIN_ITEMS', 12),

            // Most search words one sidebar item may declare.
            'max_keywords' => (int) env('VERSO_ADMIN_NAV_MAX_KEYWORDS', 20),

            // Most "opened" sidebar items remembered per user for the "New" badge.
            'max_seen' => (int) env('VERSO_ADMIN_NAV_MAX_SEEN', 200),
        ],

        'lists' => [
            // Most columns a user may pick for one admin list.
            'max_columns' => (int) env('VERSO_ADMIN_LIST_MAX_COLUMNS', 40),

            // Width of the small featured image in admin lists, in pixels.
            'thumbnail_size' => (int) env('VERSO_ADMIN_LIST_THUMBNAIL_SIZE', 80),
        ],
    ],

    'settings' => [
        // Cache key that holds all settings.
        'cache_key' => env('VERSO_SETTINGS_CACHE_KEY', 'verso.settings'),

        // Seconds to keep settings in the cache. Null keeps them forever.
        'cache_ttl' => env('VERSO_SETTINGS_CACHE_TTL') !== null ? (int) env('VERSO_SETTINGS_CACHE_TTL') : null,

        // Values used when a setting is not stored yet.
        'defaults' => [
            'site.name' => env('APP_NAME', 'Verso'),
            'site.tagline' => 'Just another Verso site',
            'site.url' => env('APP_URL', 'http://localhost'),
            'site.locale' => env('APP_LOCALE', 'en'),
            'site.timezone' => env('APP_TIMEZONE', 'UTC'),
            'reading.posts_per_page' => 10,
            'reading.front_page' => null,
            'content.revisions_limit' => (int) env('VERSO_REVISIONS_LIMIT', 25),
            'theme.active' => 'default',
            'plugins.enabled' => [],
            'reading.discourage_search' => false,
            'seo.robots_txt' => "User-agent: *\nDisallow: /admin\n",
            'permalinks.trailing_slash' => (bool) env('VERSO_PERMALINKS_TRAILING_SLASH', false),
            'permalinks.lowercase' => (bool) env('VERSO_PERMALINKS_LOWERCASE', false),
            'comments.enabled' => (bool) env('VERSO_COMMENTS_ENABLED', true),
            'updates.channel' => env('VERSO_UPDATES_CHANNEL', 'stable'),
            'updates.automatic' => env('VERSO_UPDATES_AUTOMATIC', 'off'),
        ],
    ],

    'content' => [
        // Longest slug allowed for entries and terms.
        'slug_max_length' => (int) env('VERSO_SLUG_MAX_LENGTH', 190),

        // Longest excerpt allowed, in characters.
        'excerpt_max_length' => (int) env('VERSO_EXCERPT_MAX_LENGTH', 1000),

        // Top level page slugs that would clash with public routes.
        'reserved_slugs' => ['admin', 'blog', 'category', 'tag', 'search', 'feed', 'sitemap.xml', 'robots.txt', 'img', 'login', 'register', 'settings', 'dashboard'],

        // How often (cron expression) scheduled entries are checked.
        'publish_schedule' => env('VERSO_PUBLISH_SCHEDULE', '* * * * *'),
    ],

    'comments' => [
        // Comments a visitor can send per window (see throttle_minutes).
        'throttle_attempts' => (int) env('VERSO_COMMENTS_THROTTLE_ATTEMPTS', 5),

        // Length of the throttle window, in minutes.
        'throttle_minutes' => (int) env('VERSO_COMMENTS_THROTTLE_MINUTES', 1),

        // A form sent faster than this (in seconds) is treated as a bot.
        'min_seconds' => (int) env('VERSO_COMMENTS_MIN_SECONDS', 3),

        // A form older than this (in seconds) must be loaded again.
        'max_seconds' => (int) env('VERSO_COMMENTS_MAX_SECONDS', 86400),

        // Hidden field that only bots fill in.
        'honeypot_field' => env('VERSO_COMMENTS_HONEYPOT_FIELD', 'website_address'),

        // A comment with this many links or more goes straight to spam.
        'spam_links' => (int) env('VERSO_COMMENTS_SPAM_LINKS', 3),

        // Longest comment allowed, in characters.
        'max_length' => (int) env('VERSO_COMMENTS_MAX_LENGTH', 5000),

        // Deepest reply level. Deeper replies are attached to the last allowed level.
        'max_depth' => (int) env('VERSO_COMMENTS_MAX_DEPTH', 5),

        // Value used when an entry has no comments_open meta.
        'open_by_default' => (bool) env('VERSO_COMMENTS_OPEN_BY_DEFAULT', true),
    ],

    'redirects' => [
        // Status code used when none is given.
        'default_status' => (int) env('VERSO_REDIRECT_STATUS', 301),
    ],

    'media' => [
        // Disk where uploaded files are stored.
        'disk' => env('VERSO_MEDIA_DISK', 'public'),

        // Folder pattern for new uploads (PHP date format).
        'folder_format' => env('VERSO_MEDIA_FOLDER_FORMAT', 'Y/m'),

        // Biggest upload allowed, in kilobytes.
        'max_upload_kb' => (int) env('VERSO_MEDIA_MAX_UPLOAD_KB', 10240),

        // File types people may upload.
        'allowed_mimes' => array_values(array_filter(explode(',', (string) env(
            'VERSO_MEDIA_ALLOWED_MIMES',
            'image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/wav,application/pdf',
        )))),

        // Longest caption allowed, in characters.
        'caption_max_length' => (int) env('VERSO_MEDIA_CAPTION_MAX_LENGTH', 2000),

        // Items per page in the media library and the picker.
        'per_page' => (int) env('VERSO_MEDIA_PER_PAGE', 40),

        // Entry meta keys that hold a media id (dot notation), checked before a delete.
        'usage_meta_keys' => ['featured_media_id', 'featured_image.mediaId'],

        'images' => [
            // URL prefix of the image route, like /img/2026/11/photo.jpg.
            'prefix' => env('VERSO_IMAGE_PREFIX', 'img'),

            // Disk and folder where resized images are cached.
            'cache_disk' => env('VERSO_IMAGE_CACHE_DISK', 'local'),
            'cache_folder' => env('VERSO_IMAGE_CACHE_FOLDER', 'glide-cache'),

            // Image library used by Glide: gd or imagick.
            'driver' => env('VERSO_IMAGE_DRIVER', 'gd'),

            // Largest image size Glide will create, in pixels (width x height).
            'max_image_size' => (int) env('VERSO_IMAGE_MAX_SIZE', 4096 * 4096),

            // Largest width or height a URL may ask for.
            'max_dimension' => (int) env('VERSO_IMAGE_MAX_DIMENSION', 3840),

            // Output formats a URL may ask for.
            'formats' => ['webp', 'avif', 'jpg', 'png', 'gif'],

            // Format and quality used when the URL does not give one.
            'default_format' => env('VERSO_IMAGE_FORMAT', 'webp'),
            'default_quality' => (int) env('VERSO_IMAGE_QUALITY', 80),

            // Size of the square thumbnails in the media library, in pixels.
            'thumbnail_size' => (int) env('VERSO_IMAGE_THUMBNAIL_SIZE', 400),

            // Widths used to build responsive srcset lists.
            'srcset_widths' => [320, 640, 960, 1280, 1920],

            // The sizes attribute for image blocks, gallery items and featured images.
            'block_sizes' => env('VERSO_IMAGE_BLOCK_SIZES', '(min-width: 760px) 720px, 100vw'),
            'gallery_sizes' => env('VERSO_IMAGE_GALLERY_SIZES', '(min-width: 760px) 240px, 50vw'),
            'featured_sizes' => env('VERSO_IMAGE_FEATURED_SIZES', '(min-width: 760px) 640px, 100vw'),

            // Seconds browsers may cache an image.
            'browser_cache_seconds' => (int) env('VERSO_IMAGE_BROWSER_CACHE', 31536000),
        ],
    ],

    'site' => [
        // Lists the admin settings page offers. Free values are not allowed.
        'locales' => ['en', 'es', 'fr', 'de', 'it', 'pt', 'pt-BR', 'nl', 'pl', 'ca', 'ja', 'zh', 'zh-Hans', 'hi', 'ar', 'bn', 'ru', 'id', 'tr', 'ko'],

        // Highest number of posts per page a user can pick.
        'max_posts_per_page' => (int) env('VERSO_MAX_POSTS_PER_PAGE', 100),

        // Highest number of revisions to keep per entry.
        'max_revisions_limit' => (int) env('VERSO_MAX_REVISIONS_LIMIT', 500),

        // Longest robots.txt allowed, in characters.
        'robots_txt_max_length' => (int) env('VERSO_ROBOTS_TXT_MAX_LENGTH', 5000),
    ],

    'i18n' => [
        // Languages of the admin. Each one needs lang/<code>.json. English is the source language.
        'admin_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('VERSO_ADMIN_LOCALES', 'en,es,zh-Hans,hi,ar,pt-BR,bn,ru,ja,fr,de,id,tr,ko,it'))))),

        // Language used when a text has no translation.
        'fallback_locale' => env('VERSO_ADMIN_FALLBACK_LOCALE', 'en'),

        // Seconds browsers may keep a translation bundle. The URL changes when a file changes.
        'bundle_max_age' => (int) env('VERSO_I18N_BUNDLE_MAX_AGE', 31536000),

        // Seconds the server keeps a built bundle in the cache store.
        'bundle_cache_ttl' => (int) env('VERSO_I18N_BUNDLE_CACHE_TTL', 86400),

        // Folder inside a plugin or theme with its lang/<code>.json files.
        'extension_lang_path' => env('VERSO_I18N_EXTENSION_LANG_PATH', 'lang'),
    ],

    'seo' => [
        // Text between the entry title and the site name in <title>.
        'title_separator' => env('VERSO_SEO_TITLE_SEPARATOR', ' | '),

        // Longest SEO title and description allowed, in characters.
        'title_max_length' => (int) env('VERSO_SEO_TITLE_MAX_LENGTH', 120),
        'description_max_length' => (int) env('VERSO_SEO_DESCRIPTION_MAX_LENGTH', 320),

        // Content types shown as an Article in schema.org data. Others are a WebPage.
        'article_types' => ['post'],

        // Search page path and query parameter, used by the WebSite SearchAction.
        'search_path' => env('VERSO_SEARCH_PATH', '/search'),
        'search_param' => env('VERSO_SEARCH_PARAM', 'q'),

        // Default page title format. Tokens: {title}, {sep}, {site} and {tagline}.
        'title_format' => env('VERSO_SEO_TITLE_FORMAT', '{title}{sep}{site}'),

        // The robots.txt rules used until the admin saves others, and by the "Use safe default" button.
        'robots_default' => "User-agent: *\nDisallow: /admin\n",

        // Longest title separator and schema.org name allowed, in characters.
        'separator_max_length' => (int) env('VERSO_SEO_SEPARATOR_MAX_LENGTH', 10),
        'schema_name_max_length' => (int) env('VERSO_SEO_SCHEMA_NAME_MAX_LENGTH', 120),

        // Longest verification code allowed, in characters.
        'verification_max_length' => (int) env('VERSO_SEO_VERIFICATION_MAX_LENGTH', 120),

        // Most "same as" profile links for the schema.org organization or person.
        'same_as_max' => (int) env('VERSO_SEO_SAME_AS_MAX', 10),

        // IndexNow: tells search engines about new and changed pages right away.
        'indexnow' => [
            'endpoint' => env('VERSO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
            'timeout' => (int) env('VERSO_INDEXNOW_TIMEOUT', 10),
            'queue' => env('VERSO_INDEXNOW_QUEUE', 'default'),
            'key_min_length' => (int) env('VERSO_INDEXNOW_KEY_MIN_LENGTH', 8),
            'key_max_length' => (int) env('VERSO_INDEXNOW_KEY_MAX_LENGTH', 128),
        ],

        // Twitter handle of the site, like "@versocms". Empty means none.
        'twitter_site' => env('VERSO_TWITTER_SITE', ''),

        // schema.org type per content type. Types in article_types without an entry here are an Article.
        'schema_types' => ['post' => 'BlogPosting'],

        // Longest focus keyphrase and longest share title or description saved with an entry, in characters.
        'keyphrase_max_length' => (int) env('VERSO_SEO_KEYPHRASE_MAX_LENGTH', 100),

        // Most questions read from one page for the FAQ schema.
        'faq_max_questions' => (int) env('VERSO_SEO_FAQ_MAX_QUESTIONS', 50),

        // The checks of the SEO card in the editor. They run in the browser while people write.
        'analysis' => [
            'title_min' => (int) env('VERSO_SEO_TITLE_MIN', 30),
            'title_max' => (int) env('VERSO_SEO_TITLE_MAX', 60),
            'description_min' => (int) env('VERSO_SEO_DESCRIPTION_MIN', 120),
            'description_max' => (int) env('VERSO_SEO_DESCRIPTION_MAX', 160),
            'min_words' => (int) env('VERSO_SEO_MIN_WORDS', 300),
            'keyphrase_max_words' => (int) env('VERSO_SEO_KEYPHRASE_MAX_WORDS', 4),
            'keyphrase_density_min' => (float) env('VERSO_SEO_KEYPHRASE_DENSITY_MIN', 0.5),
            'keyphrase_density_max' => (float) env('VERSO_SEO_KEYPHRASE_DENSITY_MAX', 3.0),
            'slug_max_words' => (int) env('VERSO_SEO_SLUG_MAX_WORDS', 6),
            'sentence_max_words' => (int) env('VERSO_SEO_SENTENCE_MAX_WORDS', 20),
            'long_sentences_max_percent' => (int) env('VERSO_SEO_LONG_SENTENCES_MAX_PERCENT', 25),
            'paragraph_max_words' => (int) env('VERSO_SEO_PARAGRAPH_MAX_WORDS', 150),
            'subheading_max_words' => (int) env('VERSO_SEO_SUBHEADING_MAX_WORDS', 300),
            'passive_max_percent' => (int) env('VERSO_SEO_PASSIVE_MAX_PERCENT', 10),
            'transition_min_percent' => (int) env('VERSO_SEO_TRANSITION_MIN_PERCENT', 30),
            // Scores from 0 to 100: at or over good_score is green, at or over ok_score is amber, lower is red.
            'good_score' => (int) env('VERSO_SEO_GOOD_SCORE', 70),
            'ok_score' => (int) env('VERSO_SEO_OK_SCORE', 40),
            // Wait after the last key press before the checks run again.
            'debounce_ms' => (int) env('VERSO_SEO_ANALYSIS_DEBOUNCE_MS', 400),
        ],

        // The SEO overview page (site health score and issues).
        'audit' => [
            // How long the issue counts stay cached. Saving content clears them.
            'cache_ttl' => (int) env('VERSO_SEO_AUDIT_CACHE_TTL', 600),
            // Entries read at once while the counts are built.
            'chunk' => (int) env('VERSO_SEO_AUDIT_CHUNK', 200),
            // Rows per page in the list of one issue.
            'per_page' => (int) env('VERSO_SEO_AUDIT_PER_PAGE', 25),
            // Most rows kept for the list of one issue. The count stays exact.
            'max_items' => (int) env('VERSO_SEO_AUDIT_MAX_ITEMS', 1000),
        ],

        // The link check: reads published content in the background to find broken links, orphan pages and slow pages.
        'crawl' => [
            'batch_size' => (int) env('VERSO_SEO_CRAWL_BATCH_SIZE', 25),
            'queue' => env('VERSO_SEO_CRAWL_QUEUE', 'default'),
            // Cron expression of the automatic check. Empty turns it off.
            'schedule' => env('VERSO_SEO_CRAWL_SCHEDULE', '30 3 * * 0'),
            // Most links read from one entry.
            'max_links' => (int) env('VERSO_SEO_CRAWL_MAX_LINKS', 500),
            // A page whose content takes longer than this to build is listed as slow.
            'slow_ms' => (int) env('VERSO_SEO_SLOW_PAGE_MS', 300),
            // A check that did not move for this long can be started again.
            'stale_after' => (int) env('VERSO_SEO_CRAWL_STALE_AFTER', 3600),
        ],

        // The 404 monitor: counts addresses that were not found. No IP address or other personal data is kept.
        'not_found' => [
            'enabled' => (bool) env('VERSO_404_MONITOR', true),
            // Days a not found address is kept after its last hit.
            'retention_days' => (int) env('VERSO_404_RETENTION_DAYS', 30),
            // Most different addresses kept. New ones are dropped when the list is full.
            'max_rows' => (int) env('VERSO_404_MAX_ROWS', 5000),
            // Most 404 hits counted per minute for the whole site, and for one visitor (the IP is only hashed in the cache).
            'per_minute' => (int) env('VERSO_404_PER_MINUTE', 120),
            'per_visitor_per_minute' => (int) env('VERSO_404_PER_VISITOR_PER_MINUTE', 10),
            // Longest path kept, in characters.
            'path_max_length' => (int) env('VERSO_404_PATH_MAX_LENGTH', 255),
            // Paths that are never counted, like scans for other software. "*" is a wildcard.
            'ignore' => array_values(array_filter(array_map('trim', explode(',', (string) env('VERSO_404_IGNORE', '*.php,/wp-admin*,/wp-login*,/.env*,/.git*,/.well-known/*,/favicon.ico,/apple-touch-icon*'))))),
            // Closest existing addresses suggested for each row, and the published entries compared to find them.
            'suggestions' => (int) env('VERSO_404_SUGGESTIONS', 3),
            'suggestion_candidates' => (int) env('VERSO_404_SUGGESTION_CANDIDATES', 300),
            // How alike (0 to 100) an address must be to be suggested.
            'suggestion_min_similarity' => (int) env('VERSO_404_SUGGESTION_MIN_SIMILARITY', 45),
            'per_page' => (int) env('VERSO_404_PER_PAGE', 25),
            // Daily clean up time, in the server time zone.
            'prune_at' => env('VERSO_404_PRUNE_AT', '03:15'),
        ],

        // The bulk "add alt text" screen.
        'alt_text' => [
            'per_page' => (int) env('VERSO_SEO_ALT_TEXT_PER_PAGE', 24),
            'max_length' => (int) env('VERSO_SEO_ALT_TEXT_MAX_LENGTH', 250),
        ],
    ],

    'permalinks' => [
        // Taxonomy used by the {category} token, and the slug used when an entry has no category.
        'category_taxonomy' => env('VERSO_PERMALINKS_CATEGORY_TAXONOMY', 'category'),
        'uncategorized' => env('VERSO_PERMALINKS_UNCATEGORIZED', 'uncategorized'),

        // How many older structures per type are kept to redirect old links.
        'previous_kept' => (int) env('VERSO_PERMALINKS_PREVIOUS_KEPT', 10),

        // Longest URL structure or base allowed, in characters.
        'max_length' => (int) env('VERSO_PERMALINKS_MAX_LENGTH', 120),

        // First path parts that no structure or base may use, because Verso or Laravel serve them.
        'reserved_paths' => ['admin', 'api', 'search', 'feed', 'img', 'login', 'logout', 'register', 'install', 'storage', 'build', 'up', 'sitemap.xml', 'robots.txt', '_verso', 'dashboard', 'settings', 'password', 'email', 'user'],

        // Presets offered in the admin. {slug} holds the whole path for hierarchical types like pages.
        'presets' => [
            'plain' => '/{slug}',
            'blog' => '/blog/{slug}',
            'month' => '/{year}/{month}/{slug}',
            'day' => '/{year}/{month}/{day}/{slug}',
            'category' => '/{category}/{slug}',
            'numeric' => '/archives/{id}',
        ],
    ],

    'sitemap' => [
        // Seconds to keep a generated sitemap in the cache.
        'cache_ttl' => (int) env('VERSO_SITEMAP_CACHE_TTL', 86400),

        // Prefix of the sitemap cache keys.
        'cache_prefix' => env('VERSO_SITEMAP_CACHE_PREFIX', 'verso.sitemap'),

        // Sitemap on or off until the admin saves the setting.
        'enabled' => (bool) env('VERSO_SITEMAP_ENABLED', true),

        // Add <image:image> rows for featured images until the admin saves the setting.
        'include_images' => (bool) env('VERSO_SITEMAP_INCLUDE_IMAGES', false),

        // Most URLs in one sitemap file. Bigger types are split in pages.
        'max_urls' => (int) env('VERSO_SITEMAP_MAX_URLS', 5000),

        // Most published entries offered in the "Leave out of the sitemap" picker.
        'entry_picker_limit' => (int) env('VERSO_SITEMAP_ENTRY_PICKER_LIMIT', 500),

        // Highest value the admin may pick for max_urls (the sitemap protocol allows 50000).
        'max_urls_limit' => (int) env('VERSO_SITEMAP_MAX_URLS_LIMIT', 50000),

        // Content types left out of the sitemap.
        'exclude_types' => [],

        // Taxonomies whose term archives are left out of the sitemap.
        'exclude_taxonomies' => [],
    ],

    'menus' => [
        // Places in a theme where a menu can be shown.
        'locations' => [
            'primary' => 'Primary menu',
            'footer' => 'Footer menu',
        ],

        // Deepest nesting allowed in a menu (1 means no sub items).
        'max_depth' => (int) env('VERSO_MENU_MAX_DEPTH', 3),

        // Most entries and terms offered in the menu builder lists.
        'picker_limit' => (int) env('VERSO_MENU_PICKER_LIMIT', 50),

        // Seconds to keep built menu trees in the cache. 0 turns the menu cache off.
        'cache_ttl' => (int) env('VERSO_MENU_CACHE_TTL', 86400),

        // Prefix of the menu cache keys.
        'cache_prefix' => env('VERSO_MENU_CACHE_PREFIX', 'verso.menus'),
    ],

    'site_parts' => [
        // Revisions kept for each edited part (header, footer...). Older ones are deleted on publish.
        'max_revisions' => (int) env('VERSO_SITE_PART_REVISIONS', 20),

        // Milliseconds the Site Editor waits after the last change before it saves a draft and refreshes the preview.
        'autosave_delay' => (int) env('VERSO_SITE_EDITOR_AUTOSAVE_DELAY', 1200),

        // How many changes "Undo" can step back in the Site Editor.
        'undo_steps' => (int) env('VERSO_SITE_EDITOR_UNDO_STEPS', 50),

        // Largest customization file the Site Editor imports, in kilobytes.
        'import_max_kb' => (int) env('VERSO_SITE_EDITOR_IMPORT_MAX_KB', 2048),

        // Preview widths in pixels of the Site Editor. Null fills the space.
        'preview_widths' => [
            'desktop' => null,
            'tablet' => (int) env('VERSO_SITE_EDITOR_TABLET_WIDTH', 768),
            'phone' => (int) env('VERSO_SITE_EDITOR_PHONE_WIDTH', 390),
        ],
        // The builders draw the computer preview at least this wide (pixels) and shrink it to fit a narrower pane.
        'preview_desktop_min_width' => (int) env('VERSO_SITE_EDITOR_DESKTOP_MIN_WIDTH', 1280),
    ],

    'plugins' => [
        // Folders scanned for plugins. Each plugin is a sub folder with a manifest file.
        'paths' => array_values(array_filter(explode(',', (string) env('VERSO_PLUGINS_PATHS', base_path('plugins'))))),

        // Name of the manifest file inside each plugin folder.
        'manifest' => env('VERSO_PLUGINS_MANIFEST', 'verso.json'),

        // Composer file that lists installed packages. Packages with extra.verso in their composer.json are plugins. Empty turns it off.
        'composer_installed' => env('VERSO_PLUGINS_COMPOSER_INSTALLED', base_path('vendor/composer/installed.json')) ?: null,

        // Folder inside a plugin that holds its database migrations. They run when the plugin is enabled.
        'migrations_path' => env('VERSO_PLUGINS_MIGRATIONS_PATH', 'database/migrations'),

        // Run the migrations of a plugin when it is enabled.
        'migrate_on_enable' => (bool) env('VERSO_PLUGINS_MIGRATE_ON_ENABLE', true),

        // Folder inside a plugin that holds its config files. "config/shop.php" becomes the "shop" config key.
        'config_path' => env('VERSO_PLUGINS_CONFIG_PATH', 'config'),
    ],

    'extensions' => [
        // Biggest plugin or theme zip the admin accepts, in kilobytes.
        'max_upload_kb' => (int) env('VERSO_EXTENSIONS_MAX_UPLOAD_KB', 10240),

        // Biggest size of all files once unpacked, in kilobytes. This stops zip bombs.
        'max_unpacked_kb' => (int) env('VERSO_EXTENSIONS_MAX_UNPACKED_KB', 51200),

        // Most files one package may hold.
        'max_files' => (int) env('VERSO_EXTENSIONS_MAX_FILES', 2000),

        // Biggest manifest file (verso.json or theme.json), in kilobytes.
        'max_manifest_kb' => (int) env('VERSO_EXTENSIONS_MAX_MANIFEST_KB', 64),

        // File extensions a package may contain. Anything else is refused.
        'allowed_extensions' => array_values(array_filter(explode(',', (string) env(
            'VERSO_EXTENSIONS_ALLOWED_EXTENSIONS',
            'php,json,md,txt,css,js,mjs,ts,tsx,map,svg,png,jpg,jpeg,gif,webp,avif,ico,woff,woff2,xml,pot,po,mo,lock',
        )))),

        // File names without an extension that a package may contain.
        'allowed_plain_files' => ['LICENSE', 'README', 'CHANGELOG', 'NOTICE', 'AUTHORS'],

        // Folders of a plugin where PHP files may live, besides its autoload folders.
        'plugin_php_folders' => ['src', 'config', 'routes', 'database/migrations', 'resources/views'],

        // Folders of a theme where PHP files may live. Theme PHP files must be Blade views.
        'theme_php_folders' => ['views'],

        // Folder for uploaded packages waiting for consent, backups and temporary files.
        'storage_path' => env('VERSO_EXTENSIONS_STORAGE_PATH', storage_path('app/private/extensions')),

        // Minutes an uploaded package waits for consent before it is deleted.
        'staging_ttl_minutes' => (int) env('VERSO_EXTENSIONS_STAGING_TTL', 60),

        // Backups of older versions kept for each plugin or theme.
        'backups_kept' => (int) env('VERSO_EXTENSIONS_BACKUPS_KEPT', 3),

        'registry' => [
            // URL of a JSON index with plugins and themes to install. Empty turns the Browse tab off.
            'url' => env('VERSO_REGISTRY_URL', ''),

            // Seconds to wait for the index or a download.
            'timeout' => (int) env('VERSO_REGISTRY_TIMEOUT', 15),

            // Seconds to keep the index in the cache.
            'cache_ttl' => (int) env('VERSO_REGISTRY_CACHE_TTL', 600),

            // Cache key for the index.
            'cache_key' => env('VERSO_REGISTRY_CACHE_KEY', 'verso.registry.index'),

            // URL schemes allowed for package downloads.
            'download_schemes' => ['https'],
        ],

        'verification' => [
            // Default of the "block high risk plugins" setting. When on, high risk plugins cannot be installed.
            'block_high_risk' => (bool) env('VERSO_VERIFY_BLOCK_HIGH_RISK', false),

            // Points added once per rule and file for each finding severity.
            'weights' => [
                'info' => (int) env('VERSO_VERIFY_WEIGHT_INFO', 0),
                'low' => (int) env('VERSO_VERIFY_WEIGHT_LOW', 2),
                'medium' => (int) env('VERSO_VERIFY_WEIGHT_MEDIUM', 6),
                'high' => (int) env('VERSO_VERIFY_WEIGHT_HIGH', 25),
            ],

            // Scores from which a plugin counts as medium or high risk. Any high finding also makes it high risk.
            'medium_score' => (int) env('VERSO_VERIFY_MEDIUM_SCORE', 6),
            'high_score' => (int) env('VERSO_VERIFY_HIGH_SCORE', 25),

            // Biggest PHP, JavaScript or Blade file that is scanned, in kilobytes. Bigger files are reported.
            'max_file_kb' => (int) env('VERSO_VERIFY_MAX_FILE_KB', 1024),

            // String literals at least this long that look like base64 or hex count as encoded data.
            'encoded_string_length' => (int) env('VERSO_VERIFY_ENCODED_STRING_LENGTH', 400),

            // File extensions scanned as JavaScript. Blade views are scanned as Blade and PHP.
            'script_extensions' => ['js', 'mjs'],

            // Reports kept for each plugin. Older ones are deleted.
            'reports_kept' => (int) env('VERSO_VERIFY_REPORTS_KEPT', 10),

            'sandbox' => [
                // Boot the plugin in a separate PHP process with a temporary SQLite database.
                'enabled' => (bool) env('VERSO_VERIFY_SANDBOX', true),

                // Seconds the sandbox process may run before it is stopped.
                'timeout' => (int) env('VERSO_VERIFY_SANDBOX_TIMEOUT', 60),

                // PHP binary for the sandbox process. Empty finds the PHP command line binary.
                'php_binary' => env('VERSO_VERIFY_PHP_BINARY', ''),

                // Most characters of sandbox output kept in a report.
                'max_output' => (int) env('VERSO_VERIFY_SANDBOX_MAX_OUTPUT', 4000),
            ],

            // Checks run on a theme before it is installed. Scores, weights and the sandbox above apply too.
            'themes' => [
                // Biggest theme screenshot, in kilobytes, and its widest and tallest size in pixels.
                'screenshot_max_kb' => (int) env('VERSO_VERIFY_THEME_SCREENSHOT_MAX_KB', 1024),
                'screenshot_max_width' => (int) env('VERSO_VERIFY_THEME_SCREENSHOT_MAX_WIDTH', 2400),
                'screenshot_max_height' => (int) env('VERSO_VERIFY_THEME_SCREENSHOT_MAX_HEIGHT', 1800),

                // Files looked for when theme.json does not name a screenshot.
                'screenshot_files' => ['screenshot.png', 'screenshot.webp', 'screenshot.jpg', 'screenshot.jpeg'],

                // Image types a screenshot may have.
                'screenshot_extensions' => ['png', 'webp', 'jpg', 'jpeg', 'avif'],

                // Published demo posts written to the sandbox database before the theme renders.
                'preview_posts' => (int) env('VERSO_VERIFY_THEME_PREVIEW_POSTS', 6),

                // Most HTML kept for each rendered preview page, in kilobytes. 0 keeps no HTML.
                'preview_max_kb' => (int) env('VERSO_VERIFY_THEME_PREVIEW_MAX_KB', 256),

                // Hosts that serve web fonts. Loading fonts from them shares visitor data, so it is reported.
                'remote_font_hosts' => array_values(array_filter(explode(',', (string) env(
                    'VERSO_VERIFY_THEME_FONT_HOSTS',
                    'fonts.googleapis.com,fonts.gstatic.com,use.typekit.net,p.typekit.net,fonts.bunny.net,use.fontawesome.com',
                )))),
            ],
        ],
    ],

    'updates' => [
        // Release feed. The default is the GitHub releases API of Verso. Empty turns update checks off.
        'feed_url' => env('VERSO_UPDATES_FEED_URL', 'https://api.github.com/repos/victoragudo/verso/releases'),

        // Seconds to wait for the release feed.
        'feed_timeout' => (int) env('VERSO_UPDATES_FEED_TIMEOUT', 15),

        // Seconds to keep the result of a check. The scheduled check refreshes it every day.
        'cache_ttl' => (int) env('VERSO_UPDATES_CACHE_TTL', 172800),

        // Cache key that holds the result of the last check.
        'cache_key' => env('VERSO_UPDATES_CACHE_KEY', 'verso.updates.check'),

        // When (cron expression) the scheduled check runs.
        'check_schedule' => env('VERSO_UPDATES_CHECK_SCHEDULE', '17 4 * * *'),

        // Name of the zip asset of a release. {version} is the version without the "v".
        'asset_name' => env('VERSO_UPDATES_ASSET_NAME', 'verso-{version}.zip'),

        // Suffixes of the checksum and signature assets, added to the zip name.
        'checksum_suffix' => env('VERSO_UPDATES_CHECKSUM_SUFFIX', '.sha256'),
        'signature_suffix' => env('VERSO_UPDATES_SIGNATURE_SUFFIX', '.sig'),

        // A release whose title or notes contain this word is a security release.
        'security_marker' => env('VERSO_UPDATES_SECURITY_MARKER', '[security]'),

        // URL schemes allowed for package downloads.
        'download_schemes' => ['https'],

        // Seconds to wait for a package download.
        'download_timeout' => (int) env('VERSO_UPDATES_DOWNLOAD_TIMEOUT', 120),

        // Biggest package the updater downloads, in kilobytes.
        'max_download_kb' => (int) env('VERSO_UPDATES_MAX_DOWNLOAD_KB', 153600),

        // Base64 Ed25519 public key. When set, every package must have a valid signature.
        // The default is the key that signs the official Verso releases.
        'public_key' => env('VERSO_UPDATES_PUBLIC_KEY', 'UJVLlIER3fdcrW4BR/LfTFX7z0o9tfbQ/+hBL3pZKnY='),

        // Folder the updater replaces. Tests point it to a temporary folder.
        'install_path' => env('VERSO_UPDATES_INSTALL_PATH', base_path()),

        // Folder for downloads, backups and the update lock file.
        'storage_path' => env('VERSO_UPDATES_STORAGE_PATH', storage_path('app/private/updates')),

        // Backups of earlier versions kept on disk.
        'backups_kept' => (int) env('VERSO_UPDATES_BACKUPS_KEPT', 2),

        // Paths (relative to the install folder) an update never touches.
        'keep_paths' => ['.env', 'storage', 'plugins', 'themes', 'public/uploads', 'public/storage', 'database/database.sqlite', 'bootstrap/cache', '.git', 'node_modules', 'dist'],

        // Paths inside kept paths that an update still replaces.
        'replace_paths' => ['themes/default', 'themes/ashgrove'],

        // PHP command line program used for artisan steps. Empty finds it on its own.
        'php_binary' => env('VERSO_UPDATES_PHP_BINARY', ''),

        // Seconds each artisan step (migrate, cache clear, maintenance) may take.
        'command_timeout' => (int) env('VERSO_UPDATES_COMMAND_TIMEOUT', 600),

        // Seconds visitors are told to wait while the site is in maintenance mode.
        'maintenance_retry' => (int) env('VERSO_UPDATES_MAINTENANCE_RETRY', 60),

        // Seconds a database dump or restore may take.
        'database_timeout' => (int) env('VERSO_UPDATES_DATABASE_TIMEOUT', 600),

        // Programs used to back up MySQL and PostgreSQL databases.
        'binaries' => [
            'mysqldump' => env('VERSO_UPDATES_MYSQLDUMP', 'mysqldump'),
            'mysql' => env('VERSO_UPDATES_MYSQL', 'mysql'),
            'pg_dump' => env('VERSO_UPDATES_PG_DUMP', 'pg_dump'),
            'psql' => env('VERSO_UPDATES_PSQL', 'psql'),
        ],

        'health_check' => [
            // Site address checked after an update. Empty uses APP_URL.
            'base_url' => env('VERSO_UPDATES_HEALTH_URL', ''),

            // Paths that must answer without an error after an update.
            'paths' => ['/', '/login'],

            // Seconds to wait for each health check request.
            'timeout' => (int) env('VERSO_UPDATES_HEALTH_TIMEOUT', 20),
        ],

        // Milliseconds between progress refreshes on the Updates page.
        'poll_interval' => (int) env('VERSO_UPDATES_POLL_INTERVAL', 1500),

        // Updates listed in the log on the Updates page.
        'log_limit' => (int) env('VERSO_UPDATES_LOG_LIMIT', 20),

        // Run a started update after the response is sent (no queue worker needed). Off sends it to the queue.
        'run_after_response' => (bool) env('VERSO_UPDATES_RUN_AFTER_RESPONSE', true),
        'queue' => env('VERSO_UPDATES_QUEUE'),
    ],

    'api' => [
        // Turn the public JSON API on or off.
        'enabled' => (bool) env('VERSO_API_ENABLED', true),

        // URL prefix of the public JSON API.
        'prefix' => env('VERSO_API_PREFIX', 'api/v1'),

        // Requests per minute allowed for one client (by IP address).
        'rate_limit' => (int) env('VERSO_API_RATE_LIMIT', 60),

        // Items per page when the client does not ask for a number.
        'per_page' => (int) env('VERSO_API_PER_PAGE', 10),

        // Most items per page a client can ask for.
        'max_per_page' => (int) env('VERSO_API_MAX_PER_PAGE', 100),
    ],

    'install' => [
        // File written when the site is installed. Its presence locks the installer.
        'lock_file' => env('VERSO_INSTALL_LOCK_FILE', storage_path('app/verso-installed.lock')),

        // File that keeps the progress of a running install, so a failed step can be retried.
        'state_file' => env('VERSO_INSTALL_STATE_FILE', storage_path('app/verso-install-progress.json')),

        // The .env file the installer writes. Empty means the Laravel default (base_path('.env')).
        'env_file' => env('VERSO_INSTALL_ENV_FILE', ''),

        // File copied to .env when it is missing.
        'env_example' => env('VERSO_INSTALL_ENV_EXAMPLE', base_path('.env.example')),

        // Permissions for the written .env file (octal). It holds secrets.
        'env_permissions' => (int) octdec((string) env('VERSO_INSTALL_ENV_PERMISSIONS', '0640')),

        // Send every web request to /install while Verso is not installed.
        'redirect' => (bool) env('VERSO_INSTALL_REDIRECT', true),

        // Drivers forced while the installer runs, because the database may not exist yet.
        'safe_drivers' => [
            'session' => 'file',
            'cache' => 'file',
            'queue' => 'sync',
        ],

        // Session cookie name while the installer runs. It must not change when .env changes.
        'session_cookie' => env('VERSO_INSTALL_SESSION_COOKIE', 'verso_install_session'),

        // Lowest PHP version Verso runs on.
        'php_version' => env('VERSO_INSTALL_PHP_VERSION', '8.4.0'),

        // PHP extensions Verso needs.
        'extensions' => ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'intl', 'zip', 'sodium'],

        // At least one of these image extensions must be loaded.
        'image_extensions' => ['gd', 'imagick'],

        // Lowest PHP memory_limit the installer accepts.
        'memory_limit' => env('VERSO_INSTALL_MEMORY_LIMIT', '128M'),

        // Folders the web server must be able to write to.
        'writable' => [storage_path(), base_path('bootstrap/cache'), storage_path('app/public')],

        // Seconds to wait when the installer tests a connection (database, Redis, SMTP).
        'connect_timeout' => (int) env('VERSO_INSTALL_CONNECT_TIMEOUT', 5),

        // Tries per minute for the test buttons (database, mail, Redis, storage), per IP address.
        'test_attempts_per_minute' => (int) env('VERSO_INSTALL_TEST_ATTEMPTS', 10),

        // Shortest admin password the installer accepts.
        'password_min_length' => (int) env('VERSO_INSTALL_PASSWORD_MIN', 10),

        // Minutes the "go to your dashboard" sign-in link stays valid after install.
        'login_link_minutes' => (int) env('VERSO_INSTALL_LOGIN_LINK_MINUTES', 30),

        'database' => [
            // Drivers the installer offers, with the PHP extension each one needs.
            'drivers' => [
                'sqlite' => ['label' => 'SQLite', 'extension' => 'pdo_sqlite', 'port' => null],
                'mysql' => ['label' => 'MySQL', 'extension' => 'pdo_mysql', 'port' => 3306],
                'mariadb' => ['label' => 'MariaDB', 'extension' => 'pdo_mysql', 'port' => 3306],
                'pgsql' => ['label' => 'PostgreSQL', 'extension' => 'pdo_pgsql', 'port' => 5432],
            ],

            // Lowest server versions Verso supports.
            'min_versions' => [
                'mysql' => env('VERSO_INSTALL_MIN_MYSQL', '8.0.0'),
                'mariadb' => env('VERSO_INSTALL_MIN_MARIADB', '10.6.0'),
                'pgsql' => env('VERSO_INSTALL_MIN_PGSQL', '14.0'),
            ],

            // Default SQLite file, relative to the project folder.
            'sqlite_path' => env('VERSO_INSTALL_SQLITE_PATH', 'database/database.sqlite'),

            // Default host and database name for server databases.
            'host' => env('VERSO_INSTALL_DB_HOST', '127.0.0.1'),
            'name' => env('VERSO_INSTALL_DB_NAME', 'verso'),

            // Defaults and choices for MySQL and MariaDB.
            'charset' => env('VERSO_INSTALL_DB_CHARSET', 'utf8mb4'),
            'collation' => env('VERSO_INSTALL_DB_COLLATION', 'utf8mb4_unicode_ci'),

            // SSL modes offered for PostgreSQL, and the default one.
            'ssl_modes' => ['disable', 'prefer', 'require', 'verify-ca', 'verify-full'],
            'ssl_mode' => env('VERSO_INSTALL_DB_SSLMODE', 'prefer'),

            // Tables that show an old Verso install is in the database.
            'verso_tables' => ['users', 'entries', 'settings', 'media'],
        ],

        'services' => [
            // Choices for the services step.
            'mailers' => ['log', 'smtp', 'sendmail'],
            'smtp_port' => (int) env('VERSO_INSTALL_SMTP_PORT', 587),
            'smtp_encryptions' => ['tls', 'ssl', 'none'],
            'sendmail_path' => env('VERSO_INSTALL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
            'cache_drivers' => ['file', 'database', 'redis'],
            'session_drivers' => ['file', 'database', 'redis'],
            'queue_drivers' => ['sync', 'database', 'redis'],
            'redis_host' => env('VERSO_INSTALL_REDIS_HOST', '127.0.0.1'),
            'redis_port' => (int) env('VERSO_INSTALL_REDIS_PORT', 6379),
            's3_region' => env('VERSO_INSTALL_S3_REGION', 'us-east-1'),

            // File written and removed again by the "Test write" button.
            'storage_probe' => env('VERSO_INSTALL_STORAGE_PROBE', '.verso-write-test'),
        ],

        // Values the command line installer uses when a flag is missing (useful in Docker and CI).
        'cli' => [
            'site_name' => env('VERSO_INSTALL_SITE_NAME'),
            'site_url' => env('VERSO_INSTALL_SITE_URL'),
            'admin_name' => env('VERSO_INSTALL_ADMIN_NAME', 'Site Admin'),
            'admin_email' => env('VERSO_INSTALL_ADMIN_EMAIL'),
            'admin_password' => env('VERSO_INSTALL_ADMIN_PASSWORD'),
            'timezone' => env('VERSO_INSTALL_TIMEZONE', 'UTC'),
        ],
    ],

    'generators' => [
        // Folder where verso:make-theme writes new themes.
        'themes_path' => base_path((string) env('VERSO_THEMES_PATH', 'themes')),

        // Folder and namespace where verso:make-block writes blocks that do not belong to a plugin.
        'blocks_path' => app_path('Blocks'),
        'blocks_namespace' => 'App\\Blocks',
    ],

    'importer' => [
        // Disk and folder where uploaded WordPress export files are kept.
        'disk' => env('VERSO_IMPORT_DISK', 'local'),
        'directory' => env('VERSO_IMPORT_DIRECTORY', 'imports'),

        // Largest export file the admin page accepts, in kilobytes.
        'max_upload_kb' => (int) env('VERSO_IMPORT_MAX_UPLOAD_KB', 204800),

        // Disk and folder for downloaded images and attachments.
        'media_disk' => env('VERSO_IMPORT_MEDIA_DISK', 'public'),
        'media_directory' => env('VERSO_IMPORT_MEDIA_DIRECTORY', 'uploads'),

        // Download attachments and featured images from the old site.
        'download_attachments' => (bool) env('VERSO_IMPORT_DOWNLOAD_ATTACHMENTS', true),

        // Seconds to wait for one download, how many times to retry and the pause between tries (ms).
        'download_timeout' => (int) env('VERSO_IMPORT_DOWNLOAD_TIMEOUT', 30),
        'download_retries' => (int) env('VERSO_IMPORT_DOWNLOAD_RETRIES', 2),
        'download_retry_delay' => (int) env('VERSO_IMPORT_DOWNLOAD_RETRY_DELAY', 500),

        // Largest file to download, in bytes.
        'max_download_bytes' => (int) env('VERSO_IMPORT_MAX_DOWNLOAD_BYTES', 26214400),

        // WordPress post types that are never imported.
        'skip_post_types' => ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face'],

        // WordPress statuses that are never imported.
        'skip_statuses' => ['auto-draft', 'trash'],

        // Role given to imported authors and the length of their random password.
        'author_role' => env('VERSO_IMPORT_AUTHOR_ROLE', 'author'),
        'password_length' => (int) env('VERSO_IMPORT_PASSWORD_LENGTH', 32),

        // Email domain for imported authors that have no email address.
        'fallback_email_domain' => env('VERSO_IMPORT_FALLBACK_EMAIL_DOMAIN', 'imported.invalid'),

        // Text used for the %%sep%% and %sep% variables in Yoast and Rank Math titles.
        'seo_separator' => env('VERSO_IMPORT_SEO_SEPARATOR', '-'),

        // Also redirect "/2024/05/17/slug" and "/2024/05/slug" style URLs.
        'date_redirects' => (bool) env('VERSO_IMPORT_DATE_REDIRECTS', true),

        // Save progress to the import record every N items.
        'progress_every' => (int) env('VERSO_IMPORT_PROGRESS_EVERY', 25),

        // Most warnings kept in an import report.
        'report_message_limit' => (int) env('VERSO_IMPORT_REPORT_MESSAGE_LIMIT', 200),

        // Queue settings for the import job (timeout in seconds).
        'queue' => env('VERSO_IMPORT_QUEUE'),
        'job_timeout' => (int) env('VERSO_IMPORT_JOB_TIMEOUT', 3600),

        // How often the status page refreshes (ms) and how many past imports it lists.
        'poll_interval' => (int) env('VERSO_IMPORT_POLL_INTERVAL', 2000),
        'recent_limit' => (int) env('VERSO_IMPORT_RECENT_LIMIT', 10),
    ],

    'themes' => [
        // Folder (from the project root) that holds the themes.
        'path' => env('VERSO_THEMES_PATH', 'themes'),

        // Theme used when the active one is missing. Its views are the fallback for every theme.
        'fallback' => env('VERSO_FALLBACK_THEME', 'default'),

        // Content type listed on the home page and in the RSS feed.
        'home_content_type' => env('VERSO_HOME_CONTENT_TYPE', 'post'),

        // Most entries listed in the RSS feed.
        'feed_items' => (int) env('VERSO_FEED_ITEMS', 20),

        // Most pages listed in the site navigation of the default theme.
        'nav_pages' => (int) env('VERSO_NAV_PAGES', 6),

        // Shortest and longest search text accepted on /search.
        'search_min_length' => (int) env('VERSO_SEARCH_MIN_LENGTH', 2),
        'search_max_length' => (int) env('VERSO_SEARCH_MAX_LENGTH', 100),

        // Safe font list for the theme customizer. Themes pick fonts by key, never by free CSS.
        'fonts' => [
            'charter' => ['label' => 'Charter (serif)', 'stack' => "Charter, 'Bitstream Charter', 'Sitka Text', Cambria, Georgia, serif"],
            'georgia' => ['label' => 'Georgia (serif)', 'stack' => "Georgia, 'Times New Roman', Times, serif"],
            'system' => ['label' => 'System (sans serif)', 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"],
            'humanist' => ['label' => 'Humanist (sans serif)', 'stack' => "Seravek, 'Gill Sans Nova', Ubuntu, Calibri, 'DejaVu Sans', sans-serif"],
            'mono' => ['label' => 'Monospace', 'stack' => "ui-monospace, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace"],
            'newsreader' => ['label' => 'Newsreader (serif, from the theme)', 'stack' => "Newsreader, 'Newsreader Fallback', Charter, 'Bitstream Charter', Cambria, Georgia, serif"],
            'instrument-sans' => ['label' => 'Instrument Sans (sans serif, from the theme)', 'stack' => "'Instrument Sans', 'Instrument Sans Fallback', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"],
        ],

        // Files a theme may serve from its assets folder (fonts and images), and how long browsers keep them.
        'assets' => [
            'prefix' => env('VERSO_THEME_ASSETS_PREFIX', 'theme-assets'),
            'extensions' => ['woff2', 'woff', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'ico'],
            'cache_seconds' => (int) env('VERSO_THEME_ASSETS_CACHE', 31536000),
        ],

        // Blog themes: fewest h2 headings before a post shows a table of contents, and how many related posts to show.
        'toc_min_headings' => (int) env('VERSO_THEME_TOC_MIN_HEADINGS', 3),
        'related_posts' => (int) env('VERSO_THEME_RELATED_POSTS', 3),

        // Categories listed in the category strip of blog themes.
        'category_strip' => (int) env('VERSO_THEME_CATEGORY_STRIP', 8),

        // Longest text a theme setting of type "text" may hold, in characters.
        'setting_text_max' => (int) env('VERSO_THEME_SETTING_TEXT_MAX', 500),

        // Largest block pattern file (patterns/*.json) read from a theme, in kilobytes.
        'pattern_max_kb' => (int) env('VERSO_THEME_PATTERN_MAX_KB', 256),

        // Template debug panel for users who manage themes: add ?verso-template-debug=1 to a public URL.
        'template_debug' => [
            'enabled' => (bool) env('VERSO_TEMPLATE_DEBUG', true),
            'query' => env('VERSO_TEMPLATE_DEBUG_QUERY', 'verso-template-debug'),
        ],
    ],

    'manifest_cache' => [
        // Keep theme and plugin manifests (and the plugin list from Composer) in the cache.
        'enabled' => (bool) env('VERSO_MANIFEST_CACHE', true),

        // Cache store for manifests. "file" is fast and needs no database query. Empty uses the default store.
        'store' => env('VERSO_MANIFEST_CACHE_STORE', 'file'),

        // Seconds to keep an entry. Changed files get a new key at once, so this only cleans up old entries.
        'ttl' => (int) env('VERSO_MANIFEST_CACHE_TTL', 604800),

        // Prefix of the manifest cache keys.
        'prefix' => env('VERSO_MANIFEST_CACHE_PREFIX', 'verso.manifest'),
    ],

    'shortcodes' => [
        // Turn shortcodes like [form slug="contact"] into the HTML of their block.
        'enabled' => (bool) env('VERSO_SHORTCODES', true),

        // How deep enclosing shortcodes can be nested inside each other.
        'max_depth' => (int) env('VERSO_SHORTCODES_MAX_DEPTH', 3),

        // Most shortcodes read from one text. Extra ones stay as plain text.
        'max_per_text' => (int) env('VERSO_SHORTCODES_MAX_PER_TEXT', 50),

        // Longest value of one shortcode attribute, in characters.
        'max_attribute_length' => (int) env('VERSO_SHORTCODES_MAX_ATTRIBUTE_LENGTH', 500),

        // Text places (besides entry blocks) where shortcodes run: excerpt, custom_field, theme_setting, form_email, footer.
        'places' => array_values(array_filter(explode(',', (string) env('VERSO_SHORTCODES_PLACES', 'excerpt,custom_field,theme_setting,form_email,footer')))),

        // Capability that shows the warning box for broken shortcodes on the public site.
        'warning_capability' => env('VERSO_SHORTCODES_WARNING_CAPABILITY', 'edit_any_entry'),

        // Most entries shown in a "Used in" list in the admin.
        'used_in_limit' => (int) env('VERSO_SHORTCODES_USED_IN_LIMIT', 50),

        // Clear only the cached pages that embed a changed item. False clears every cached page.
        'targeted_invalidation' => (bool) env('VERSO_SHORTCODES_TARGETED_INVALIDATION', true),
    ],

    'block_cache' => [
        // Keep the HTML of core blocks per entry. Entries with plugin blocks are always rendered fresh.
        'enabled' => (bool) env('VERSO_BLOCK_CACHE', true),

        // Cache store for block HTML. "file" needs no database query per entry. Empty uses the default store.
        'store' => env('VERSO_BLOCK_CACHE_STORE', 'file'),

        // Seconds to keep the HTML of one entry.
        'ttl' => (int) env('VERSO_BLOCK_CACHE_TTL', 86400),

        // Prefix of the block cache keys.
        'prefix' => env('VERSO_BLOCK_CACHE_PREFIX', 'verso.blocks'),
    ],

    'page_cache' => [
        // Turn the full page cache for guests on or off.
        'enabled' => (bool) env('VERSO_PAGE_CACHE', true),

        // Cache store for pages. Null uses the default store. "file" is a good choice.
        'store' => env('VERSO_PAGE_CACHE_STORE'),

        // Seconds to keep a cached page.
        'ttl' => (int) env('VERSO_PAGE_CACHE_TTL', 3600),

        // Prefix for every page cache key.
        'prefix' => env('VERSO_PAGE_CACHE_PREFIX', 'verso.page'),

        // Query parameters that can be part of a cached URL. Other parameters skip the cache.
        'allowed_query' => ['page'],

        // Cookie set for signed in visitors and pending notices. Visitors with it never get a stored page.
        'bypass_cookie' => env('VERSO_PAGE_CACHE_BYPASS_COOKIE', 'verso_nocache'),

        // Cache-Control header of pages served from the cache. Browsers check them again with ETag and Last-Modified.
        'cache_control' => env('VERSO_PAGE_CACHE_CONTROL', 'public, max-age=0, must-revalidate'),
    ],

    'admin_bar' => [
        // Show the admin bar on top of public pages for users who may see it.
        'enabled' => (bool) env('VERSO_ADMIN_BAR', true),

        // Height of the bar in pixels. Themes read it as the --verso-adminbar-height CSS variable.
        'height' => (int) env('VERSO_ADMIN_BAR_HEIGHT', 36),
    ],

    'benchmark' => [
        // Posts seeded into the temporary database by "php artisan verso:benchmark".
        'entries' => (int) env('VERSO_BENCHMARK_ENTRIES', 500),

        // Measured requests per route and mode, and requests sent first to warm up caches.
        'requests' => (int) env('VERSO_BENCHMARK_REQUESTS', 50),
        'warmup' => (int) env('VERSO_BENCHMARK_WARMUP', 5),

        // Percentile compared with the budgets, like 95 for p95.
        'percentile' => (int) env('VERSO_BENCHMARK_PERCENTILE', 95),

        // Most milliseconds a page may take with and without the page cache. 0 turns a budget off.
        'cached_budget_ms' => (float) env('VERSO_BENCHMARK_CACHED_BUDGET_MS', 50),
        'uncached_budget_ms' => (float) env('VERSO_BENCHMARK_UNCACHED_BUDGET_MS', 250),
    ],

    // Roles and permissions admin.
    'access' => [
        // Lines of the role change log shown on the roles pages.
        'audit_limit' => (int) env('VERSO_ACCESS_AUDIT_LIMIT', 30),
        // People listed on a role page.
        'members_limit' => (int) env('VERSO_ACCESS_MEMBERS_LIMIT', 50),
        // Roles shown side by side on the compare page.
        'compare_limit' => (int) env('VERSO_ACCESS_COMPARE_LIMIT', 4),
    ],

    'system' => [
        // Heavy tasks (imports, image work, updates, exports) may raise PHP limits up to these values, when PHP allows it.
        'heavy_tasks' => [
            'memory_mb' => (int) env('VERSO_HEAVY_TASK_MEMORY_MB', 512),
            'time_limit' => (int) env('VERSO_HEAVY_TASK_TIME_LIMIT', 600),
        ],

        'server' => [
            // Part of PHP post_max_size kept for the other form fields sent with a file, in kilobytes.
            'upload_overhead_kb' => (int) env('VERSO_UPLOAD_OVERHEAD_KB', 256),

            // The web server body limit (nginx client_max_body_size, Apache LimitRequestBody) in megabytes. 0 means unknown.
            'web_server_body_mb' => (int) env('VERSO_WEB_SERVER_BODY_MB', 0),
        ],

        // Folder for copies of public/.user.ini and public/.htaccess made before Verso changes them.
        'backup_path' => env('VERSO_SYSTEM_BACKUP_PATH', storage_path('app/private/system/backups')),

        // Changes listed in the audit log of System settings.
        'audit_limit' => (int) env('VERSO_SYSTEM_AUDIT_LIMIT', 30),

        'health' => [
            // Folders (from the project root) that PHP must be able to write to.
            'writable' => ['storage', 'storage/app', 'storage/app/public', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'],

            // Folders that only some features write to (installing plugins and themes from the admin).
            'optional' => ['plugins', 'themes'],

            // Folders searched for world writable files and files with the wrong owner.
            'scan' => ['storage', 'bootstrap/cache', 'plugins', 'themes', 'public'],

            // Most files looked at by one scan, so the page stays fast on big sites.
            'scan_limit' => (int) env('VERSO_HEALTH_SCAN_LIMIT', 5000),
        ],
    ],

    'fields' => [
        // Folder inside a theme or plugin with field group JSON files.
        'json_folder' => env('VERSO_FIELDS_JSON_FOLDER', 'fields'),

        // Longest value of text, email and url fields, in characters (a field can set a lower max).
        'text_max' => (int) env('VERSO_FIELDS_TEXT_MAX', 255),

        // Longest value of textarea and rich text fields, in characters.
        'textarea_max' => (int) env('VERSO_FIELDS_TEXTAREA_MAX', 20000),

        // Most rows a repeater can hold and most images in a gallery, when the field sets no max.
        'max_rows' => (int) env('VERSO_FIELDS_MAX_ROWS', 100),

        // How deep repeaters and groups can be nested.
        'max_depth' => (int) env('VERSO_FIELDS_MAX_DEPTH', 4),

        // Most fields one field group can hold, counting nested fields.
        'max_fields' => (int) env('VERSO_FIELDS_MAX_FIELDS', 200),

        // Biggest JSON file accepted by the import, in kilobytes.
        'import_max_kb' => (int) env('VERSO_FIELDS_IMPORT_MAX_KB', 512),

        // Results returned by the relation search in the entry editor.
        'search_limit' => (int) env('VERSO_FIELDS_SEARCH_LIMIT', 20),

        // Image sizes attribute used by field images in themes and the API.
        'image_sizes' => env('VERSO_FIELDS_IMAGE_SIZES', '(min-width: 1024px) 960px, 100vw'),

        // PHP date formats used when @field prints a date or a date time.
        'date_format' => env('VERSO_FIELDS_DATE_FORMAT', 'j F Y'),
        'date_time_format' => env('VERSO_FIELDS_DATE_TIME_FORMAT', 'j F Y, H:i'),
    ],

    'export' => [
        // Default folder for "php artisan verso:export".
        'path' => env('VERSO_EXPORT_PATH', 'storage/app/static'),
    ],

    'demo' => [
        // Folder on the media disk where the demo seeder writes its images.
        'media_folder' => env('VERSO_DEMO_MEDIA_FOLDER', 'demo'),

        // Size and JPEG quality of the demo images. Small images keep seeding fast.
        'image_width' => (int) env('VERSO_DEMO_IMAGE_WIDTH', 960),
        'image_height' => (int) env('VERSO_DEMO_IMAGE_HEIGHT', 640),
        'image_quality' => (int) env('VERSO_DEMO_IMAGE_QUALITY', 78),

        // Width and height of the square demo author photos.
        'avatar_size' => (int) env('VERSO_DEMO_AVATAR_SIZE', 320),

        // Theme the demo seeder turns on. The install wizard recommends it too.
        'theme' => env('VERSO_DEMO_THEME', 'ashgrove'),

        // Plugins the demo seeder turns on (comma separated slugs).
        'plugins' => array_values(array_filter(explode(',', (string) env('VERSO_DEMO_PLUGINS', 'reading-time,forms')))),
    ],

    'easy' => [
        // Send a new admin to the guided start ("What is your site for?") right after the install.
        'guided_start_after_install' => (bool) env('VERSO_EASY_GUIDED_START_AFTER_INSTALL', true),

        // Folder on the media disk for the sample pictures of starter sites.
        'media_folder' => env('VERSO_EASY_MEDIA_FOLDER', 'easy'),

        // Size and JPEG quality of the sample pictures. Portraits are square.
        'image_width' => (int) env('VERSO_EASY_IMAGE_WIDTH', 1200),
        'image_height' => (int) env('VERSO_EASY_IMAGE_HEIGHT', 800),
        'image_quality' => (int) env('VERSO_EASY_IMAGE_QUALITY', 80),
        'portrait_size' => (int) env('VERSO_EASY_PORTRAIT_SIZE', 480),

        // Page templates that give starter pages the full width, first match of the active theme wins.
        'wide_templates' => array_values(array_filter(explode(',', (string) env('VERSO_EASY_WIDE_TEMPLATES', 'full-width,wide')))),

        // Slug of the form the contact section shows when the Forms plugin is on.
        'contact_form' => env('VERSO_EASY_CONTACT_FORM', 'contact'),

        'map' => [
            // OpenStreetMap page that shows a map in a frame, and the page linked under it.
            'embed_url' => env('VERSO_EASY_MAP_EMBED_URL', 'https://www.openstreetmap.org/export/embed.html'),
            'link_url' => env('VERSO_EASY_MAP_LINK_URL', 'https://www.openstreetmap.org/'),

            // Zoom of a new map (1 shows the world, 19 one street) and height of the map frame in pixels.
            'default_zoom' => (int) env('VERSO_EASY_MAP_ZOOM', 15),
            'height' => (int) env('VERSO_EASY_MAP_HEIGHT', 360),
        ],

        // "Make it yours": wait this long (milliseconds) after the last pick before the preview reloads.
        'preview_delay' => (int) env('VERSO_EASY_PREVIEW_DELAY', 350),

        // Logo colors: the logo is shrunk to this many pixels on its longest side before reading colors,
        // and at most this many main colors are kept.
        'palette' => [
            'sample_size' => (int) env('VERSO_EASY_PALETTE_SAMPLE_SIZE', 96),
            'max_colors' => (int) env('VERSO_EASY_PALETTE_MAX_COLORS', 6),
        ],

        // Visual page editor: wait this long (milliseconds) after a change before the page is drawn again,
        // keep this many undo steps, and keep the "Undo" button of a removed section this many seconds.
        'page_editor' => [
            'render_delay' => (int) env('VERSO_EASY_PAGE_RENDER_DELAY', 250),
            'undo_steps' => (int) env('VERSO_EASY_PAGE_UNDO_STEPS', 100),
            'undo_seconds' => (int) env('VERSO_EASY_PAGE_UNDO_SECONDS', 8),

            // Keep unsaved changes as a draft this long (milliseconds) after the last change,
            // and list this many earlier versions in "Restore this page".
            'autosave_delay' => (int) env('VERSO_EASY_PAGE_AUTOSAVE_DELAY', 2000),
            'versions' => (int) env('VERSO_EASY_PAGE_VERSIONS', 20),
        ],

        // A new picture section ("Add section") gets up to this many of the latest pictures of the media library.
        'section_pictures' => (int) env('VERSO_EASY_SECTION_PICTURES', 6),

        // Simple admin mode (fewer menus, plain words). "default" applies on sites that never saved a default;
        // "new_sites" is saved as the site default by the install and by the first starter site.
        'simple_mode' => [
            'default' => (bool) env('VERSO_EASY_SIMPLE_MODE_DEFAULT', false),
            'new_sites' => (bool) env('VERSO_EASY_SIMPLE_MODE_NEW_SITES', true),
        ],

        // Launch checklist: the "pages" step is done after a starter site or this many published pages.
        'launch' => [
            'min_pages' => (int) env('VERSO_EASY_LAUNCH_MIN_PAGES', 3),
        ],

        // Address of the page made by "Create a privacy page". A published page with this address also counts.
        'privacy' => [
            'slug' => env('VERSO_EASY_PRIVACY_SLUG', 'privacy-policy'),

            // Menu location that gets a link to a new privacy page, so visitors can find it. Empty for none.
            'menu_location' => env('VERSO_EASY_PRIVACY_MENU_LOCATION', 'footer'),
        ],

        // Longest values of "Your site details", in characters.
        'details' => [
            'phone_max' => (int) env('VERSO_EASY_DETAILS_PHONE_MAX', 40),
            'address_max' => (int) env('VERSO_EASY_DETAILS_ADDRESS_MAX', 500),
            'hours_max' => (int) env('VERSO_EASY_DETAILS_HOURS_MAX', 255),
        ],

        // Help panel: short videos by topic key, like "go-live=https://videos.example.com/go-live.mp4,make-it-yours=...".
        'help' => [
            'videos' => collect(explode(',', (string) env('VERSO_EASY_HELP_VIDEOS', '')))
                ->map(fn (string $pair): array => array_map(trim(...), explode('=', $pair, 2)))
                ->filter(fn (array $pair): bool => count($pair) === 2 && $pair[0] !== '' && $pair[1] !== '')
                ->mapWithKeys(fn (array $pair): array => [$pair[0] => $pair[1]])
                ->all(),
        ],
    ],

];

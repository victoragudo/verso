# Performance

Verso aims for a time to first byte under 50 ms for cached pages and a light, fast default theme. This page explains what makes it fast, how to measure it and how to keep it fast.

## Results

Measured with `php artisan verso:benchmark` on a MacBook (PHP 8.4, SQLite, database cache store, `APP_ENV=production`). Times are for one request through the HTTP kernel, without the PHP boot that PHP-FPM adds (see "What the benchmark measures").

500 posts:

| Route | Page cache off, p95 | Page cache on, p95 |
|---|---|---|
| `/` (home) | 5.05 ms | 0.28 ms |
| `/blog/{slug}` (entry) | 2.90 ms | 0.28 ms |
| `/blog` (archive) | 5.22 ms | 0.29 ms |
| `/category/travel` (term) | 5.49 ms | 0.29 ms |
| `/about` (page) | 2.32 ms | 0.27 ms |
| `/feed` | 2.80 ms | 0.28 ms |

2000 posts:

| Route | Page cache off, p95 | Page cache on, p95 |
|---|---|---|
| `/` (home) | 8.04 ms | 0.28 ms |
| `/blog/{slug}` (entry) | 5.59 ms | 0.28 ms |
| `/blog` (archive) | 6.52 ms | 0.27 ms |
| `/category/travel` (term) | 6.61 ms | 0.28 ms |
| `/about` (page) | 2.29 ms | 0.27 ms |
| `/feed` | 4.73 ms | 0.27 ms |

Cached pages stay flat as the site grows: a hit reads one cache entry and nothing else.

Plugin discovery with the manifest cache: 1.2 ms instead of 2.3 ms per request (the Composer `installed.json` of this repository is 460 KB).

## Running the benchmark

```bash
php artisan verso:benchmark
php artisan verso:benchmark --entries=2000 --requests=100 --warmup=10
```

What it does:

1. Creates a temporary SQLite file in `storage/framework/verso-benchmark-*.sqlite` and starts a child process that uses it. The child uses production settings, the database cache store (inside the temporary database), sessions in memory and a sync queue. Your real database, cache and sessions are never touched. The file is deleted at the end, also when the run fails.
2. Migrates the temporary database and seeds a realistic blog: N posts with headings and paragraphs, four categories, six tags, an about page with a child page and a primary menu.
3. For each route (home, an entry, the blog archive, a category, a page and the feed) it sends warm up requests and then measured requests through the HTTP kernel in the same process, first with the page cache off, then on. With the page cache on, every measured response must be a `HIT`.
4. Prints p50, p95, p99 and the mean in milliseconds, and exits with code 1 when a route is over its budget.

The worker refuses to run on any database that is not the temporary file, and the command refuses to run while the config is cached (`php artisan config:clear` first), because a cached config would ignore the temporary database.

### Budgets

Set in `config/verso.php` under `benchmark`, with env overrides:

| Env | Default | Meaning |
|---|---|---|
| `VERSO_BENCHMARK_ENTRIES` | 500 | Posts to seed |
| `VERSO_BENCHMARK_REQUESTS` | 50 | Measured requests per route and mode |
| `VERSO_BENCHMARK_WARMUP` | 5 | Requests sent before measuring |
| `VERSO_BENCHMARK_PERCENTILE` | 95 | Percentile compared with the budgets |
| `VERSO_BENCHMARK_CACHED_BUDGET_MS` | 50 | Budget with the page cache on (0 turns it off) |
| `VERSO_BENCHMARK_UNCACHED_BUDGET_MS` | 250 | Budget with the page cache off (0 turns it off) |

### What the benchmark measures

The requests run inside one booted application, like Octane would run them. PHP-FPM also boots Laravel on every request, which adds a few milliseconds that depend on OPcache and the server. To see the full cost on your server, use a load tester against a real deployment, for example `ab -n 500 -c 10 https://example.org/` or `wrk`, and compare the `X-Verso-Cache: HIT` responses.

## What makes Verso fast

### Full page cache

- Guests get stored pages (`verso.page_cache`). The `ServeCachedPage` middleware (alias `verso.cache.early`) runs before the `web` group on public routes, so a hit starts no session, sets no cookie, runs no Inertia middleware and no redirect lookup. With the database cache store a hit costs two small queries (the cache version and the page), with the file store none.
- Signed in visitors and visitors with a notice waiting (form errors, "comment waiting for approval") carry the `verso_nocache` cookie, set by `MarkPageCacheBypass` at the end of the `web` group. A `remember_*` login cookie also skips the early cache. These visitors go through the full stack, where `CachePage` checks the user and the session again.
- Cached pages get an `ETag` (a hash of the HTML) and `Last-Modified` (the time they were stored), on the first response and on hits. A browser that sends `If-None-Match` or `If-Modified-Since` gets `304 Not Modified` with no body.
- Hits send `Cache-Control: public, max-age=0, must-revalidate` (`VERSO_PAGE_CACHE_CONTROL`), so browsers and proxies keep the page but check it again on every visit, which costs a 304 at most.
- The cache is emptied when entries, terms, menus, redirects, public comments or settings change.

### Data caches

| Cache | Where | Invalidation |
|---|---|---|
| Settings | default store (`verso.settings`) | every `Settings::set()`, migrations |
| Menu trees per location | default store (`verso.menus`) | menus and items saved or deleted, entry events, terms, settings |
| Theme manifests (`theme.json`) | `verso.manifest_cache.store` (file) | key holds inode, times and size of every manifest, so a change, a new theme or a removed theme is read again at once |
| Plugin manifests and Composer plugin packages | `verso.manifest_cache.store` (file) | same as theme manifests, plus `installed.json` |
| Core block HTML per entry | `verso.block_cache.store` (file) | key holds a hash of the blocks; media changes, plugin enable or disable and settings changes move to a new version |
| Sitemaps | default store (`verso.sitemap`) | entry events, terms, settings |

Entries that contain plugin blocks (at any depth) are always rendered fresh, because plugin blocks can depend on the visitor (members only content, for example). `EntryContentFilter` always runs after the cache, so filters see every request.

The manifest and block caches use the `file` store by default: one small file read is cheaper than a database query. `php artisan cache:clear file` empties them by hand, which is never needed for correctness.

### Queries

- `Model::preventLazyLoading()` is on outside production, so an N+1 query fails tests instead of slowing the site.
- Entry lists load their featured images and image blocks with one query (`ResponsiveImages::preloadEntries`), and every image is loaded once per request.
- Term archives read entry ids from the `entry_term` pivot index instead of a correlated `exists` query.
- Query budgets (`tests/Support/QueryBudget.php`) are checked for the home page, an entry, a nested page, the blog archive, a category, the feed, the sitemap, the admin entries list and the admin dashboard, and the counts must not change when 25 more posts are added. A cached hit must stay within `QueryBudget::CACHED_PAGE`.

### Indexes

Migration `2026_11_16_170500_add_performance_indexes` adds plain column indexes that work on SQLite, MySQL and PostgreSQL:

| Table | Index | Used by |
|---|---|---|
| `entries` | `type, status, published_at` (already there) | published entries by type and date |
| `entries` | `type, updated_at` | admin entry lists, dashboard recent entries |
| `entries` | `status, type` | status counts, search |
| `entries` | `parent_id, menu_order` | child pages |
| `terms` | `taxonomy, slug` (unique, already there) | term archives |
| `terms` | `taxonomy, name` | admin term lists, term pickers |
| `entry_term` | `term_id` (already there) | term archives |
| `comments` | `entry_id, status, created_at` (already there) | comments of an entry |
| `analytics_events` | `day, path` (analytics plugin migration) | daily rollup |
| `analytics_daily` | `day, dimension, value` and `dimension, day` (already there) | reports |

`tests/Feature/Performance/DatabaseIndexesTest.php` checks that the indexes exist on every database and, on SQLite, that the query plans use them.

### Default theme front end

- No render blocking resources: no stylesheet links and no external scripts. The theme CSS (about 12 KB) is inlined in a `<style>` tag in the head, so the critical CSS is always there with the HTML.
- No web fonts: the theme uses system font stacks (`verso.themes.fonts`), so there is nothing to download or preload. A theme that adds a web font should preload it with `<link rel="preload" as="font" type="font/woff2" crossorigin>` in `@push('head')`.
- Every image has `width`, `height`, `loading` and `decoding="async"`, so the layout does not move while images load. The site logo gets a width from its aspect ratio.
- The first image of a page loads eagerly with `fetchpriority="high"`: the featured image of an entry, the first card image of a list, or the first image of the content when the entry has no featured image above it (`LeadImage`). All other images load lazily.
- Minimal JavaScript: only the small inline comment form token script on entries with open comments and, when the analytics plugin is on, its beacon (under 1 KB).
- `tests/Feature/Performance/ThemeFrontEndChecklistTest.php` parses the HTML of the home page, an entry, a page, the archive, a category and search, and checks all of the above. Lighthouse is not part of the tool chain; this test is the checklist.

### Static files

- `/img` responses (Glide) send `Cache-Control: public, max-age=31536000, immutable` (`VERSO_IMAGE_BROWSER_CACHE`).
- `public/.htaccess` sends `Cache-Control: public, max-age=31536000, immutable` for `/build/assets/*` (file names change with every build) and `public, max-age=2592000` for `/fonts/*`, when Apache has `mod_headers`.
- On nginx, add:

```nginx
location /build/assets/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}

location /fonts/ {
    add_header Cache-Control "public, max-age=2592000";
    try_files $uri =404;
}
```

## Production checklist

- `php artisan view:cache`. A cached config (`config:cache`) still leaves plugin config empty (see PROGRESS), and `verso:benchmark` needs `php artisan config:clear` first.
- OPcache on, with `opcache.validate_timestamps=0` on servers that deploy by replacing files.
- `VERSO_PAGE_CACHE_STORE=file` (or redis) keeps page hits away from the database.
- Keep `VERSO_MANIFEST_CACHE_STORE` and `VERSO_BLOCK_CACHE_STORE` on `file` unless the site runs on several servers without a shared disk; then use redis.

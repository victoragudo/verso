# Verso Progress

Shared state file. Every agent updates its own section when it finishes or stops.
Weekly usage guard: run `.verso-state/usage.sh` (local only). If weekly >= 60, stop, commit work in progress and write state here.

## Phase 1: Foundation

Status: done

- Core: config/verso.php, roles and capabilities, AdminNav, admin dashboard, Settings, redirects.
- Content: content types, taxonomies, entries, revisions, terms, actions, events, publish job, policies, admin UI for entries and terms.
- Seeder: admin@verso.test / password.

## Phase 2: Modules

Status: done. Every branch is merged into main and kept on the remote.

| Module                       | Branch        | Status | Notes                                                                           |
| ---------------------------- | ------------- | ------ | ------------------------------------------------------------------------------- |
| Editor                       | feat/editor   | merged | 15 core blocks, sanitizer, ValidBlocks rule, Tiptap block editor.               |
| Media                        | feat/media    | merged | Library, picker, Glide images, usage check before delete.                       |
| Theme and cache              | feat/theme    | merged | Theme manager, default theme, public routes, page cache, feed, static export.   |
| Site (SEO, menus, settings)  | feat/site     | merged | Settings page, SEO panel and tags, sitemaps, robots.txt, menus, redirects page. |
| Users and comments           | feat/users    | merged | Users admin, invitations, comments with moderation and spam checks.             |
| Plugins, API, CLI, installer | feat/plugins  | merged | Plugin system, filters, example plugin, REST API, generators, installer.        |
| WordPress importer           | feat/importer | merged | WXR import with media, comments, SEO meta and redirects.                        |

## Phase 2 integration

Status: done

What was joined together:

- Entry editor uses the real block editor, the media picker, the SEO panel and a comments switch. Blocks are checked with ValidBlocks (nested children are kept).
- Plugin blocks are added to the core BlockRegistry, so they pass validation and render inside columns.
- Public entries and the feed render through the plugin ContentRenderer, so EntryContentFilter runs.
- Image and gallery blocks with a mediaId use Glide URLs with srcset and sizes from config.
- Default theme uses `<x-verso-seo>`, `<x-verso-menu>` (primary in the header with a fallback to top level pages, footer menu) and `<x-verso-comments>` on posts, with CSS for menus and comments.
- Page cache is cleared when menus change and when a comment becomes or stops being public. Pages with flash data (comment notice, form errors) are never cached.
- Site settings page has a comments switch. The site.locale setting is applied to web requests.
- Importer cleans SEO meta with EntrySeo (no new revisions on a second run). Imported featured images count as media usage.
- Starter welcome page removed. All Verso env options listed in .env.example. README, LICENSE and CONTRIBUTING added.

Checks on main: 1498 Pest tests, pint, phpstan, types:check and build all pass.
Smoke test (browser): log in as admin, create a post with a paragraph and an uploaded image, publish, view it as a guest (srcset from /img, SEO tags), X-Verso-Cache MISS then HIT, post a comment and see the cache cleared.

What remained from this pass was fixed in phase 3 (feat/fixes, feat/jstests).

## Design pass

Status: done

What changed in the admin:

- Figtree is self-hosted (variable woff2, latin and latin-ext, in `public/fonts/figtree`). The Bunny font loader is gone. Tables, stats, times and number inputs use tabular numbers.
- Design tokens in `resources/css/app.css`: zinc grays, white surfaces, zinc-200 borders, blue-600 accent (`brand`) for focus, links and the active nav item, zinc-900 primary buttons, soft status tones (success, warning, danger, neutral, info). Light is the default theme, dark is supported.
- Custom Verso icon set in `resources/js/components/verso/icons` (20px grid, 1.5 stroke, `currentColor`) with `<VersoIcon name="...">`. Every AdminNav item, content type and taxonomy uses an icon key.
- `NavItem` has a `group` (`NavGroup`: Main, Content, Site, Settings). The new shell has a fixed 240px sidebar with grouped sections, a top bar with breadcrumbs, Cmd+K page search and the user menu, and a sheet for the sidebar under `lg`. The site name is shared as `siteName`.
- Shared blocks in `resources/js/components/verso/ui`: PageHeader, SectionCard, StatCard, EmptyState, StatusBadge, FilterTabs, RowActions and DataTable (sticky header, row menu, stacked cards under md).
- The dashboard shows stat cards plus "Recent entries" and "Pending comments" panels (`DashboardPanel`, limit `verso.admin.dashboard_items`).
- Every admin page and the account settings pages use the new blocks. The entry editor has two columns with a meta panel (publish, taxonomies, SEO, revisions).
- Buttons, inputs, selects and menu items are at least 40px tall under md. No admin page scrolls sideways at 360px (checked in a browser on all admin pages).

Checks: 1511 Pest tests, pint, phpstan, types:check and build pass. Screenshots of the dashboard, entries, entry editor, media and settings at 1440px and 390px are in `.verso-state/screenshots`.

What remained from this pass was fixed in phase 3 (feat/fixes).

## Pending items pass

Status: done

- Plugin blocks in the editor: plugin blocks can implement `HasBlockFields` (description and `BlockField` list). The fields give the attrs validation rules and are shared as the `pluginBlocks` prop of the entry editor, which lists them in the slash and "Add block" menus with a generated form. Reading Time describes its `minutes` field.
- Fixed a bug found on the way: enabled plugins were never loaded in real requests (the plugins module read settings before the cache store existed). They now load in a booting callback.
- PHP 8.4: composer.json requires `^8.4` (lock file only changed its hash and platform). Tests check that the installer and composer.json agree.
- Dashboard stat cards have icons (`DashboardStat` `icon` key). Every built-in stat sets one.
- Demo seeder: six GD-painted JPEG photos on the public disk (`verso.demo.*` config) with alt text and captions, used as featured images and image blocks; primary and footer menus; approved and pending comments with a team reply. `migrate:fresh --seed` takes about half a second and rewrites the same files. The default theme shows featured images on entry cards (`<x-verso-featured-image>`).
- Site time zone: `SiteTime` converts UTC dates to `site.timezone` for the admin (`siteTimezone` prop, `useSiteDates()`, `<SiteDate>`), the theme, comments, feed, sitemap and SEO tags. The publish date is typed in the site zone and saved in UTC.

Checks: 1581 Pest tests, pint, phpstan, types:check and build pass. Screenshots with the `pending-` prefix are in `.verso-state/screenshots` (dashboard, slash menu, plugin block form, public home).

What remained from this pass was fixed in phase 3 (feat/fixes).

## Phase 3: Platform modules

Status: done. Every branch is merged into main with `--no-ff` and kept on the remote. No module stopped early.

| Module | Branch | Status | Notes |
| --- | --- | --- | --- |
| Backlog fixes | feat/fixes | merged | Composer package plugins, taxonomy sitemaps, menu picker server search, editor can keep an admin's html block, comment CSRF token endpoint, site name in the tab title, invitations prop, starter header removed, demo PluginSeeder, featured image on entry pages. |
| CI and databases | feat/ci | merged | GitHub Actions on PHP 8.4 and 8.5 against SQLite, MySQL 8.4 and PostgreSQL 17. `scripts/test-databases.sh`. Fixes for case-insensitive search on Postgres, JSON key order on MySQL, redirect path case on MySQL and paging ties. |
| JavaScript tests | feat/jstests | merged | Vitest, jsdom and Testing Library, 308 tests in `resources/js/tests`. Fixed three bugs (bad dates, statusTone with prototype keys, stale media search answers). |
| Extensions and customizer | feat/extensions | merged | Zip install, update with rollback, remove, consent screen, optional registry, plugin migrations, theme customizer with live preview. |
| Analytics plugin | feat/analytics | merged | Cookieless beacon, daily rollup, admin report, `ThemeFooterFilter` and `<x-verso-footer />`. |
| Newsletter and memberships | feat/growth | merged | Double opt-in newsletter with campaigns, memberships with plans and members only content, entry editor panels, plugin child blocks. |
| AI Assistant plugin | feat/ai | merged | Preview-first editor panel with Claude (Messages API through Laravel Http), encrypted key, usage log, `ai.use` capability. PHPStan now scans `plugins/`. |
| Shop plugin | feat/shop | merged | Products, variants, cart, checkout, orders, bank transfer and Stripe Checkout, emails, inventory and orders admin, plugin theme views. |

Integration work on main (each its own commit):

- Four branches each added a way to run plugin migrations. They now share one `PluginMigrator`, called by `EnablePlugin` while `verso.plugins.migrate_on_enable` is on. The duplicate listener, `PluginMigrations` class and `verso.plugins.migrations` key are gone; the folder is `verso.plugins.migrations_path`.
- PHPStan (which now scans `plugins/`) passes: plugin models have `@property` docs, `tests/Support/phpstan-bootstrap.php` registers plugin view namespaces, and a few type bugs were fixed.
- JavaScript tests updated for the merged editor (insert toolbar, `children` on plugin blocks) and for the installed jsdom.
- Settings are forgotten after migrations, so `migrate:fresh --seed` enables the demo plugins again (the seeder read stale settings before).

Checks on main: 2467 Pest tests (parallel), pint, phpstan, types:check, 308 Vitest tests and build all pass.

Smoke test in a browser (screenshots `phase3-*` in `.verso-state/screenshots`): log in as admin, enable AI Assistant, Analytics, Memberships, Newsletter and Shop, open every new admin page (no errors, no sideways scroll at 1440px), create and publish a product with a price and SKU, buy it as a guest with bank transfer (order ORD-00001, thank-you page), add a newsletter signup block to a page, subscribe as a guest and confirm with the emailed link, see the visits in Analytics, change the accent color in the theme customizer and see `--accent` on the public home page.

## Phase 4: Platform depth

Status: done. Every branch is merged into main with `--no-ff` (in this order: hardening, performance, hooks, fields, templates, verify, updates, qa) and kept on the remote. No module stopped early.

| Module | Branch | Status | Notes |
| --- | --- | --- | --- |
| Phase 3 fixes | feat/hardening | merged | Analytics skips private paths (PrivatePathsFilter), plugin config works with `config:cache`, shop cancels stale pending orders, product categories and `shop.manage` with a Shop manager role, newsletter pages in the theme layout and a queue heartbeat notice, analytics ignores chosen roles, `verso.footer` middleware, remove plugin or theme with or without its data. |
| Performance | feat/performance | merged | Lazy loading throws outside production, query budget tests, menu, manifest and block caches, page cache served before the session with ETag and 304, `verso:benchmark`, new indexes, eager first image in the default theme. docs/PERFORMANCE.md. |
| Hooks and slots | feat/hooks | merged | 13 new typed filters (entry query, search, block HTML, head tags, body class, SEO, sitemap, feed, mail, admin nav, entry rows, permissions, uploads, public routes) and 8 admin slots with a React slot registry. SEO card, comments switch and AI panel are slot components. docs/EXTENDING.md. |
| Custom fields | feat/fields | merged | Field groups from the admin, JSON files and code, 22 field types, repeaters, conditional logic, location rules, validation, theme helpers (`field()`, `@field`), REST API `fields`. |
| Theme templates | feat/templates | merged | Child themes, template hierarchy with TemplateCandidatesFilter, page templates, template parts, block patterns, template debug panel, `verso:make-theme --parent`. docs/THEMES.md. |
| Plugin verification | feat/verify | merged | Static scan, permissions and network hosts, compatibility and clashes, sandbox boot, risk report on the consent screen and the plugin detail page, "Block high risk plugins", `verso:verify-plugin`. |
| Core updates | feat/updates | merged | VERSION file, release feed check, Updates page, one click update with backups and rollback, `verso:update`, release workflow and `scripts/build-release.sh`. |
| Browser QA | feat/qa | merged | 16 accessibility and polish fixes from a full browser pass in light and dark mode at 1440px and 390px. Report in docs/QA.md. |

Integration work on main (each its own commit):

- Merge resolutions: public middleware is `verso.cache.early, web, verso.redirects, verso.theme, verso.cache, verso.footer`; home, archive and term pages use the template hierarchy and pass the `ArchiveContext` for the entry query filter; the entry editor puts the page template card and the sidebar field groups into the slot-ordered side panel, so field group template rules follow the template picked in the editor (before, only the saved value counted); `capabilities` props use `Capability::labels()`.
- The entry editor crashed for every content type with taxonomies (the term counter was used before it was defined, since the hooks branch). Fixed, with a Vitest render test.
- Four tests lazy loaded relations and failed with lazy loading turned off; they now eager load.
- The shop capability has a readable label, and field group location rules offer only roles that are available.
- The plugin verification migration moved to its own range (`2026_11_16_190000`), because the updates module already uses `2026_11_16_180000`. A database that ran the old name needs its `migrations` row renamed (done for the local database) or `migrate:fresh`.
- Also on main during this pass: a new Verso logo, favicon and split auth layout with calmer, more accessible auth forms (separate work, its own commits).

Checks on main: 3330 Pest tests (parallel), pint, phpstan, types:check, 514 Vitest tests and build all pass.

Smoke test in a browser on a fresh `migrate:fresh --seed` (screenshots `phase4-*` in `.verso-state/screenshots`): log in as admin; create the field group "Travel details" with a text field and see it in the new post editor, save a post with it (stored in `meta.fields`); create a page with the "Full width" page template (stored in `meta.template`); upload the `weather-widget` fixture plugin zip and read its high risk report (score 34, undeclared network hosts with file and line, slug confirmation); open the Updates page (version 0.1.0, not checked yet, settings); as a guest, `/blog` returns MISS then HIT, and a request with its ETag in `If-None-Match` returns 304.

## Install wizard (feat/wizard)

Status: done, merged into main.

- Works with no .env and no database: .env is created from .env.example with a new APP_KEY, every request goes to /install with file sessions, file cache and the sync queue until the lock file exists.
- Web wizard (Inertia, admin design system, Verso logo, stepper, dark mode, 390px): welcome and language, server checks with fixes, database cards (SQLite, MySQL, MariaDB, PostgreSQL) with test connection and create database, site and admin (strength meter), optional services (mail with test email, cache, sessions, queue, Redis test, S3 test write), content (demo, theme, plugins), masked review, live install with retry and a one-time dashboard link.
- `verso:install` mirrors the steps with Laravel Prompts and has a full non-interactive mode for Docker and CI. Both share `EnvWriter`, `DatabaseConnectionTester`, `DatabaseCreator`, `StepRules` and `InstallPipeline`.
- Checked: full non-interactive install against MySQL 8.4 and PostgreSQL 17 in temporary Docker containers (database created by the installer, demo content, plugin). Browser walk with SQLite and demo content, screenshots `wizard-*` in `.verso-state/screenshots`.

## Permalinks and SEO (feat/permalinks)

Status: done, merged into main.

- One catch-all route (`PublicPathController`) resolves archives, terms and entries from the permalink settings, so structures change without touching route files or the route cache. `Entry::path()` and `Term::path()` read the same settings.
- URL structure per content type (also plugin types) with presets and custom patterns (`{slug}`, `{id}`, `{year}`, `{month}`, `{day}`, `{category}`), archive and taxonomy bases, trailing slash and lowercase redirects. A wrong date or category in a URL gets a 301 to the right one.
- Saving keeps older structures and bases (`verso.permalinks.previous_kept`), and the resolver sends old links to the new path with a 301. The page cache, sitemap and menus are refreshed.
- Clash checks: broken patterns, reserved paths, existing routes, two flat types with the same shape, bases used twice, and bases that would hide a published page.
- Sitemap: on or off, content types and taxonomies, single entries left out (settings and the entry SEO panel), featured images, URLs per file, last modified source, status counts.
- Search engines: discourage switch, validated robots.txt with a safe default, title format and separator, default description and share image, schema.org organization or person, Google and Bing verification, optional IndexNow key file and ping on publish (queued).
- The general settings page no longer has robots.txt and the discourage switch; they live on the new page.
- Theme fix: single entries got the body class `entry`, so `.entry { max-width }` squeezed the whole page. Body classes are now `single type-{type} slug-{slug}`, and the theme rule targets `article.entry`.
- Not done: trailing slash mode does not redirect `/blog/post` to `/blog/post/` (the default `.htaccess` removes trailing slashes, so the page warns about it instead). The sitemap entry picker lists the newest `verso.sitemap.entry_picker_limit` entries, not a server search.

## Contact forms plugin (feat/forms)

Status: done, merged into main.

- `plugins/forms`: form builder (11 field types, multi column layout, rules, success message or redirect), `form` block and `<x-forms-form>` component, inbox with status, search, date and form filters, bulk actions, CSV export, privacy delete and retention, queued notification and confirmation emails with placeholders, spam protection on the site (honeypot, time trap, rate limit per IP hash, links, blocked words) and optional Turnstile or hCaptcha.
- Works with the page cache (fresh token from `/forms/token`) and without JavaScript; accessible markup and focus on the first error. Plugin verification: low risk (score 4: one inline script, one network call for the optional captcha).
- Core additions: `BlockField::$searchable`, `SeedsDemoContent` and `PluginDemoSeeder`. Demo seeding turns on `forms` and adds the Contact form to the Contact page.
- Checked in a browser on a fresh `migrate:fresh --seed` (screenshots `forms-*`): send the demo form with JS, client and server errors with focus, admin list, builder, inbox, message and settings.
- Not checked: real Turnstile or hCaptcha keys, real mail delivery (only the log mailer), screen readers, MySQL and PostgreSQL (CI covers them).

## Shortcodes (feat/shortcodes)

Status: done, merged into main.

- `app/Verso/Shortcodes`: a tokenizer (`ShortcodeParser`, no regex on the text) with quoted values, flags, `[name /]`, enclosing tags, `[[name]]` escapes and limits from `verso.shortcodes`. `ShortcodeRegistry` reads the plugin block registry, so every plugin block with fields is also a shortcode and `<x-verso-embed>` (opt-out and aliases with `EmbeddableBlock`). `ShortcodeExpander` is the one place that renders them, after the text is sanitized, through `ContentRenderer::renderBlock()`.
- Paragraphs split around block output. Broken shortcodes stay as text for visitors and show a small warning to users with `warning_capability`. Entries with shortcodes skip the block cache. Shortcodes in text need the block capability on save (`ValidBlocks`).
- Opt-in places (`VERSO_SHORTCODES_PLACES`): excerpt and footer text in the default theme, text custom fields, form emails, and the `@shortcodes($text, 'place')` directive for themes.
- Usage index `embed_usages` refreshed on `EntrySaved`; `EmbedInvalidator` clears only the cached pages that embed a changed form, product or newsletter list. "Used in" on the form and product edit pages. `php artisan verso:shortcodes [--usage]`.
- Admin: `CopyShortcode` (Clipboard API, status message, selects the text when denied, Blade snippet in a disclosure) on forms list and edit, product edit and newsletter settings. Editor: a shortcode pasted alone becomes its block; inline ones are chips with a field popover, saved as text.
- Importer: `[contact-form-7]`, `[audio]`, `[video]` and Gutenberg shortcode blocks mapped; unknown shortcodes listed in the import summary.
- Not done: enclosing shortcodes are not shown as chips in the editor; theme customizer settings other than the footer text need `@shortcodes` in the theme view.

## What remains

Open items across all modules, checked against the code on 2026-11-18. Each module section below keeps its own "Still open" list with more detail; usability ideas and audit leftovers are in docs/QA.md.

Release and infrastructure:

- The GitHub Actions workflows (CI and release) still stop after a few seconds: GitHub refuses the runs because of the account billing or spending limit, so the CI badge shows failing. No release or tag exists yet, so a real end-to-end core update was never tried; all feed and download calls are faked in tests.
- A core update runs inside the old PHP process after the response; bundled plugins in `plugins/` are kept and so never updated by a core update. mysqldump and pg_dump backups were only tested with faked processes.
- The new indexes and migrations were not run on MySQL or PostgreSQL outside the test suite (CI covers them once it runs).
- `npm run check` (vp check) fails on the intentionally broken fixture `tests/Fixtures/plugins/broken-json/verso.json`; it is not one of the required checks.
- Some intermediate commits on the merged branches do not pass tests on their own (noted by each module); every merge tip does.

Not tried with real services:

- The AI Assistant against the real Anthropic API (needs a key), and the Stripe payment path in a browser.
- S3 storage and Redis (optional packages `league/flysystem-aws-s3-v3`, phpredis or `predis/predis`), and the WordPress importer with a real WXR file.

Plugins and themes:

- Plugin admin components come in through a Vite glob at build time: a plugin installed from a zip shows its React slot components only after `npm run build`.
- The sandbox route clash check only works when the sandbox runs. When updating an enabled plugin, content type and taxonomy clashes are skipped.
- With "delete data", a plugin's settings are found by the `<slug>.` key prefix only.
- The manifest cache can miss an in-place edit within the same second that keeps the file size, until the entry expires.
- Theme checks: the preview is an iframe of the rendered HTML, not a real screenshot, and theme fonts and images do not load in it. A Blade error points to the view file but not the line. The sandbox renders home, entry and archive only.
- The Themes pages do not show the theme screenshot image; the check report only names the file.
- There is no download or export of a theme folder as a zip in the admin (for example after "Create child theme"); it has to be zipped on the server (see docs/THEME-GUIDE.md).
- The text in the views of the default and Ashgrove themes (like "Skip to content" or "Recent posts") is English only; their `lang/` files translate only the admin labels from `theme.json`. Themes can translate views with `__('<slug>::Text')`.
- The default theme has no styles for the `button`, `columns` and `gallery` content blocks (Ashgrove has them).
- A child theme made from the Site Editor keeps the part style choices as saved settings, not in its `theme.json`; menus and the logo are not copied.
- Ashgrove post cards print the author name without a link.

Editor and builder:

- The editor canvas is a list of block cards; the zoomed out view scales it and does not show the real page. Layout and library revisions can be restored but not compared.
- The mobile menu, header and popup CSS use fixed widths (641 and 1025 px); changed `VERSO_BREAKPOINT_*` values do not move them.
- The countdown has no `Intl.DurationFormat`; the mega menu has no fallback for anchor positioning beyond a fixed place under the header.
- `editor.units` only limits the pickers. The column `width` and image width and height stay plain numbers. Custom CSS values are not checked for type mixing (like `calc(1px * 1px)`).
- Icons on menu items are not supported.

SEO:

- "Slow pages" measures server time only. There is no FAQ block (FAQ schema reads `details` blocks and blocks named `faq`). The link check does not read site parts, term descriptions or widgets. The 404 path column is not case sensitive on MySQL. The snippet preview cuts by characters, not pixel width.

Translations:

- Translations were written by language models with a glossary and automatic checks; a native review per language is still worth doing. New builder terms (style preset, synced block, theme builder) are not in the glossary yet.
- Log entries, console output, shop order history notes and content people typed stay in the language they were written in. The Builder Example block category and the AI Assistant alt text suggester name are not translated. The date picker week always starts on Monday. Mixed direction sentences built on the server do not isolate inserted names.

Accessibility and browsers:

- Not checked with real screen readers (VoiceOver, NVDA), at 200% text zoom, in Windows forced colors mode, or in Firefox and Safari; Chromium with axe-core, Vitest and Pest cover the paths. Phone width was not checked on the custom fields, templates, plugin detail and Updates pages, the Patterns popover and the template debug panel.
- Radix still drives the keyboard of menus, tooltips and the fallback select. Browser validation errors show no text under the field until submit. Paths in Arabic tables show the slash on the wrong end.
- QA leftovers (docs/QA.md): a busy state is missing on some buttons, some removals run at once instead of offering undo, a few search fields have no focus ring of their own, block drag handles do nothing on Enter or Space, the product inventory page puts the status badge inside the `h1`, the checkout country field accepts any two letters, and the extension upload keeps "Check package" disabled until a file is chosen.
- Roles: the audit lines show old and new roles as data, and role page members link to access pages the viewer may not manage. The public REST API is read only, with no authenticated API yet.

## Later phases

Everything below is merged into main. Each part lists what it did and what is still open.

### Site Editor (feat/site-editor)

Done on the branch, one commit per step:

1. Override layer for template parts (`site_parts`, revisions, draft and published copies, "Restore original", whole page cache flush on publish).
2. Universal block styles (`attrs.design`, responsive, scoped classes, admin custom CSS), the `section` layout block and site blocks (logo, site name, tagline, menu, search, social links, link columns, copyright, announcement bar).
3. Site Editor admin: parts list, full screen editing with the block editor and a live preview at computer, tablet and phone widths, autosaved drafts, Undo, Publish changes, Restore original.
4. Header and footer styles from theme.json (Ashgrove: Classic and Centered header, Simple and Four columns footer) with a preview.
5. Global styles (colors, fonts, spacing, corners, widths) from theme.json, linked to the customizer values.
6. Menus: theme locations, mega menu flag, rel words, visibility by sign in state, drag and drop.
7. Templates view and export and import of the customization as JSON.

Upgrade checklist: run `php artisan migrate` for `2026_11_17_500000_create_site_parts_table` and `2026_11_17_500100_add_display_options_to_menu_items_table`. Cached menu trees from before the upgrade are skipped because the menu cache key carries `MenuCache::FORMAT`, and the renderer reads the new link keys (`mega`, `rel`, `visibility`) with defaults.

Done later on feat/pending: the dark mode switch block, "Create child theme from these changes" and the per template choice of parts (see "Pending items (feat/pending)" below).

Still open: icons on menu items. Full templates, dynamic blocks, synced blocks and the Styles panel are done (see Site Editor phase 2).

### Easy mode: anyone can build a simple site without technical knowledge

Status (feat/easy-mode): done and merged. The journey test, timings and usability ideas are in docs/QA.md "Easy mode journey".

Goal: a person with no technical knowledge builds a simple site to their taste in under 30 minutes, without reading documentation.

1. Guided start after install (and from the dashboard any time): "What is your site for?" (blog, business, portfolio, restaurant, shop, event, personal page). Each answer installs a complete starter site: pages (Home, About, Services, Contact...), menus, sections with sample text and images, settings and the right plugins turned on.
2. "Make it yours" step: upload the logo and get a color palette taken from it (with 3 alternatives), pick a font pair from visual samples, pick a style (calm, bold, playful, elegant), see the real site change live.
3. Visual editing on the page itself: an "Edit page" button in the admin bar opens the page with click-to-edit text, click-to-replace images, and "Add section" between sections with a gallery of ready sections (hero, services, gallery, testimonials, pricing, team, FAQ, contact form, map, call to action) that adapt to the site style. Move, duplicate, hide and delete sections with large buttons.
4. Simple admin mode (default for new sites, switchable per user): hides advanced menus and controls, uses plain language everywhere, shows a short "Launch checklist" on the dashboard (logo, colors, pages, contact details, social links, SEO basics, privacy page, go live), and a help panel with short videos or illustrated steps.
5. Optional AI helper (uses the AI Assistant plugin when a key is set): write the texts of a section from a short description of the business, suggest headlines, translate the site, generate alt text. Always as a preview the user accepts.
6. Safety nets: undo everything, automatic drafts, "Restore this page", previews on phone and tablet, plain error messages, nothing breaks the site when the user experiments.
7. Accessibility and mobile: the whole flow works on a tablet; contrast checks warn the user when chosen colors are hard to read.
8. Tested with Playwright as a full user journey: from a fresh install to a finished small business site using only the easy mode, at 1440 and 390 px, plus usability notes.

### Builder part 1: drag and drop, any HTML element, code editor (feat/builder)

Done on the branch, one commit per step:

1. Registries on the server (block categories, style properties, drop rules, editor features) and the client (blocks, categories, style properties, inspector panels, toolbar buttons, insert sources, drop rules, code serializers, shortcuts, breakpoints). Core registers through the public API; client modules are found in `plugins/*/resources/js/builder.ts(x)` and `themes/*/resources/js/builder.ts(x)` and only run for enabled plugins and the active theme. "Editor settings" page and `verso.builder.features` config turn tools on per role and content type and limit blocks per content type; theme.json `editor` limits blocks, colors, fonts and spacing.
2. Styles panel in the Block tab of the editor sidebar for every style option, per screen size, with theme color swatches and copy, paste and reset of styles.
3. "Add blocks" side panel (blocks, sections, patterns, plugin items, search, categories, recently used) with drag and drop into the page and into sections and columns, drag to move, an outline tree with drag and drop, and keyboard moves (menus and Alt+Shift+arrows) announced to screen readers. The section block is in the entry editor.
4. "HTML element" block for any element of an allow list, with attributes and nested blocks, validated by one element policy and limited to the `element_block` feature. The raw HTML block keeps its capability.
5. Visual / Code switch with CodeMirror 6 (loaded only in the Code view), block markers, per block serializers, errors with their line, and pasted HTML or WordPress markup converted by the importer converter. Round trip tests for every block type.
6. One undo history for the whole document and copy and paste of blocks as JSON between pages and tabs.
7. Custom CSS and JavaScript site wide (Settings, Custom code) and per page (dialog in the entry editor), admins only, CSS cleaned, JavaScript served as an external file.
8. docs/EXTENDING.md documents every registry with its contract version; `plugins/builder-example` adds a block, a style property, an inspector panel and a drop rule.

Upgrade checklist: run `php artisan migrate` for `2026_11_18_100000_create_custom_code_table`. New env keys are in `.env.example` (`VERSO_BUILDER_*`, `VERSO_CUSTOM_*`). Entry pages now print the styles of styled blocks; the block cache key carries `BlockOutputCache::FORMAT`, so cached entry HTML from before the upgrade is not reused.

Canvas fixes (fix/canvas): the Add blocks and Outline panels take their own column from 1024px and are a sheet below; the canvas shows block styles live for the chosen screen size from one serializer shared with the site (checked by `tests/Fixtures/block-styles.json` in Pest and Vitest); multi select with Shift+click, Cmd/Ctrl+click and Shift+Arrow (move, duplicate, copy, delete, group in a section); the canvas uses the full screen width with a "Content width" choice; Ashgrove keeps a stable scrollbar gutter.

Still open for part 2: template conditions in a registry and the Easy mode spec.

Modularity rules for the whole builder (apply to every part):

- Everything is a registry, nothing is a hardcoded list: blocks, block categories, style properties and their controls, inspector panels, toolbar buttons, insert panel sources (blocks, patterns, synced blocks, plugin items), drop rules (which block accepts which children), code serializers and parsers per block, keyboard shortcuts, device breakpoints and template conditions. Core registers its own items through the same public API that plugins and themes use.
- Every block is a self-contained module: PHP definition (fields, styles it supports, children rules, capability, renderer, serializer) plus an optional client module (editor component, inspector controls, icon), discovered automatically from core, plugins/*/ and themes/*/.
- Features can be turned off per site, per role and per content type (for example no code editor for authors, no custom JS anywhere, only some blocks for a content type), from config and from an admin "Editor settings" page.
- Themes can lock or limit the design system (allowed colors, fonts, spacing scale, which blocks and patterns appear) through theme.json, so non technical users stay inside a consistent design.
- Stable, versioned contracts documented in docs/EXTENDING.md with one example plugin that adds a custom block, a style property, an inspector panel and a drop rule.

### Site Editor phase 2: a full builder, no extra plugins needed

Goal: nobody needs a page builder, header/footer builder, mega menu or popup plugin. Builds on the Site Editor (feat/site-editor).

1. Universal block styles: spacing, size, flex/grid layout, typography, colors and gradients, background images with focal point and overlay, borders, radius, shadows, sticky position, z-index, visibility per device; every value responsive (base, tablet, mobile); rendered as scoped classes; copy and paste styles between blocks; style presets ("Card", "Hero") saved globally.
2. Layout blocks: container/section with nesting, grid, columns, stack, spacer, divider shapes.
3. Content and interactive blocks without plugins: tabs, accordion, toggle, slider/carousel, image comparison, lightbox gallery, video background, icon (bundled icon set), icon list, counter, progress bar, testimonial, pricing table, team member, timeline, call to action, alert, map (OpenStreetMap embed without tracking), countdown, social share, table of contents, modal/popup trigger. All accessible and built with Modern Web Guidance patterns, minimal JS loaded only when the block is on the page.
4. Theme builder: edit full templates (single per type, archive, search, 404, page templates) with dynamic blocks (title, featured image, content, excerpt, date, author, terms, custom fields, reading time, breadcrumbs) and a query loop block (filters, order, pagination, layouts). Conditions to apply a template (by type, term, author, page).
5. Header and footer builder: sticky and transparent headers, header on scroll changes, mega menus with blocks inside, mobile menu designer, multiple headers by condition.
6. Popups and banners: modal, slide-in, top bar and cookie notice built with blocks; triggers (time, scroll, exit intent, click), frequency, conditions by page, device and role; no tracking by default.
7. Reusable synced blocks and a design library: save any selection as a synced block or a pattern; import and export as JSON; theme and plugin patterns in one searchable library.
8. Editor experience: undo/redo across the whole document, list view (outline tree with drag and drop), breadcrumb of the selected block, keyboard shortcuts, multi select, copy/paste blocks between pages, device preview with per-device editing, zoomed out view, revisions with visual diff.
9. Performance and safety: generated CSS per page cached and invalidated with the usage index, no layout shift, no inline style injection, sanitized custom CSS for admins only, capabilities for who can edit templates, styles and custom CSS.
10. Many tests (Pest, Vitest) and a Playwright review building a real landing page from scratch at 1440, 1024 and 390 px.

Status (feat/site-editor-2): done, except the items under Still open. One or more commits per step.

Done:

1. Universal block styles, leftovers: style presets (`attrs.preset`; core Card, Hero and Pill, theme presets in `editor.presets`, saved presets in the Styles panel for people with `edit_styles`), gradients (linear or radial, 2 to 5 stops with tokens), background pictures with a focal point (picker and percents) and repeat, size and scrolling, overlay over pictures, `position.zIndex`. Sticky and visibility per screen size were already there. One fixture (`tests/Fixtures/block-styles.json`) keeps PHP and the canvas equal, presets included.
2. Layout blocks: `grid` (fits columns by a smallest width), `stack`, `spacer`, `shape-divider` (wave, curve, slope, triangle, zigzag, arrow, clouds). Section and columns were there. Builder blocks are self contained modules (`App\Verso\Blocks`, one class each, assets in `resources/blocks/<asset>` loaded only where used).
3. Interactive blocks: tabs, accordion (one at a time option), toggle, slider (scroll snap, dots, autoplay with pause), image comparison, lightbox for galleries, video background (pause, reduced motion), icon (bundled Lucide set) and icon list, counter, progress bar, timeline, alert, countdown (site time zone), share links (no tracking), table of contents, modal. Testimonials, pricing, team, FAQ, call to action and map come from the Easy mode ready sections, now a "Sections" tab of the Add panel, and the existing map block. New field types: picture, icon, color, date and time; blocks can name parents, child types and starter children.
4. Theme builder (Design, Theme builder): templates with dynamic blocks (title, featured image, content, excerpt, date, author, terms, custom field, reading time, breadcrumbs, list title) and a query loop (page entries or its own filters by type, term and author, order, offset, count, page links, grid or list). Conditions are a registry (everywhere, kind of page, content type, one entry, term, author, visitor, role), with priority and "except" rules. Drafts, publish, revisions, turn off, preview on a chosen page.
5. Header and footer builder: header and footer layouts by condition, sticky, see through, on scroll solid, smaller or hidden; mega menu block (popover panel with any blocks, hover on wide screens); mobile menu block (drawer dialog designed with blocks, per screen size).
6. Popups and banners: modal, slide-in, top bar (above the header, hidden before paint when closed) and cookie notice (decline as easy as accept, `verso:consent` event), triggers (right away, delay, scroll, leaving the page, click), frequency (every page, once per visit, every few days, until closed), screens, conditions by page and role; popup button block. Nothing is sent anywhere.
7. Synced blocks and design library: "Save to the library" in every block menu and the selection bar (synced block or pattern), Library tab in the Add panel, Library page (search, theme and plugin patterns too, edit, delete, used in, JSON import and export).
8. Editor: breadcrumb of the selected block, zoomed out view, revision comparison side by side with added, removed, changed and moved blocks.
9. Performance and safety: static builder blocks stay in the block cache, with the preset styles in the cache key; the usage index records presets and synced blocks, so changes empty only the pages that use them; block files carry a hash and are cached for a year; no block prints a `style` attribute; capabilities `edit_templates`, `edit_styles`, `edit_popups`, `manage_library` (implied by `edit_site`; editors get `manage_library`) and `edit_custom_css` (implied by `manage_settings`, now the default custom CSS capability).
10. Tests: Pest (style presets and values, caching, builder blocks, dynamic blocks, theme builder, headers and popups, library, revision comparison), Vitest (presets, style controls, library, layouts, breadcrumb, comparison) and `tests/e2e/builder-landing.spec.ts`, which builds a landing page from scratch at 1440, 1024 and 390 px (each journey now has its own install).

Upgrade checklist:

1. Run `php artisan migrate` for `2026_11_23_100000_create_site_layouts_table` and `2026_11_23_100100_create_library_items_table`.
2. Run `npm run build`. Block assets are served from `resources/blocks` by `/verso-blocks/...`; nothing to copy.
3. `VERSO_EDITOR_MAX_DEPTH` now defaults to 8 (was 4) for nested builder layouts. Sites that set it keep their value.
4. Custom CSS on blocks now needs `edit_custom_css` (`VERSO_EDITOR_CUSTOM_CSS_CAPABILITY`); `manage_settings` gives it, so admins keep it. Custom roles that should edit templates, styles, popups or the library need the new capabilities (or `edit_site`).
5. Cached entry HTML from before is not reused (`BlockOutputCache::FORMAT` 3). New env keys are in `.env.example` (`VERSO_STYLE_PRESETS_MAX`, `VERSO_BLOCK_ASSET_CACHE_SECONDS`, `VERSO_LAYOUTS_*`, `VERSO_READING_WORDS_PER_MINUTE`, `VERSO_QUERY_LOOP_*`, `VERSO_LIBRARY_*`).
6. Plugins with a block named like a new core block (`grid`, `tabs`, `slider`, `counter`, `breadcrumbs`...) replace the core block when they load; rename the plugin block to keep both.

Still open:

- The editor canvas stays a list of block cards; the zoomed out view scales it, it does not show the real page. The preview frames of the Site Editor and the theme builder show the real page.
- Revision comparison works for entries; layout and library revisions can be restored but not compared yet.
- The tablet and phone sizes of the mobile menu, the header and the popups use fixed widths (641 and 1025 px) in their CSS, matching the default breakpoints; changed `VERSO_BREAKPOINT_*` values do not move them.
- The countdown shows days to seconds with plain numbers, without Temporal or `Intl.DurationFormat`; the mega menu has no fallback for anchor positioning beyond a fixed place under the header.
- Translations were written with the glossary by language models; new terms (style preset, synced block, theme builder) are not in the glossary yet.
- Not tried with a real screen reader; checked with axe-core in the browser journey and in unit tests.

Checks: 5249 Pest tests (parallel), pint, phpstan, types:check, 1854 Vitest tests, build, legacy-scan and 5 Playwright journeys pass.

### Theme verification before install

Upload a theme zip (already possible), then run a verification step with the same engine as plugins before the consent screen:

1. Requirements: valid theme.json (name, slug, version, description, author, requires Verso and PHP versions), required views present (layouts/app, home, entry, archive, taxonomy, search, 404) unless a parent theme provides them, parent theme installed when "parent" is set, screenshot image present and within size limits, settings schema valid.
2. Safety scan of Blade and PHP: @php blocks and PHP files with dangerous calls (same rules as plugins), unescaped output {!! !!} of request or user data, inline event handlers, external scripts and styles not declared in theme.json, remote fonts (privacy), forms posting to external hosts.
3. Quality checks: uses <x-verso-seo>, the head and footer filters (so plugins like analytics work), skip link and lang attribute present, images with width and height, no render blocking scripts.
4. Preview: render home, an entry and an archive with the theme in a sandbox process against demo data and report errors; show screenshots of the result when possible.
5. Report with risk level and findings (file, line), stored and shown on the review screen and theme detail page. Missing required views or a broken render blocks the install; high risk needs typing the slug to confirm.

Status (feat/theme-verify): done, except the items under Still open.

Done:

- `ThemeVerifier` reuses the plugin engine: `CodeScanner` (shared file walk with the PHP, Blade and JS scanners, also used by `PluginVerifier`), `VerificationReport` scoring and risk levels, `SandboxRunner` and the `plugin_verifications` table.
- Requirements (`ThemeRequirementsCheck`): name, slug and version (invalid stops the install), description and author, `requires` (Verso) and `php` constraints, slug equal to the folder, parent installed, required views from the theme or its parents (`ThemeManager::missingRequiredViews`), screenshot (`theme.json` "screenshot" or screenshot.png/webp/jpg) type, file size and pixel size, settings schema.
- Safety (`ThemeScanner` plus the plugin PHP and Blade rules): dangerous calls in `@php` blocks and Blade, PHP files outside views, `{!! !!}` of request or visitor data (high) and other raw output (info), inline event handlers, scripts and styles from hosts not in the theme.json `network` list, remote font services (privacy), forms posting to another host.
- Quality: `<x-verso-seo>`, `<x-verso-head>` and `<x-verso-footer>` in the theme or its parents, skip link, `lang` on html, images without width and height, scripts without defer or async.
- Preview: `verso:sandbox-theme` runs in the sandbox process with a temporary SQLite database, writes demo posts with the benchmark seeder, activates the theme and renders home, a post and the archive through the HTTP kernel. Status, time, size and HTML (cut at a limit) are stored; errors point to the view file. The admin shows each page in a sandboxed iframe with a CSP that blocks scripts and all network requests.
- Report: stored with `kind = theme`; `details.blocking` lists rules that stop the install (`theme.manifest-invalid`, `theme.missing-view`, `theme.parent-missing`, `theme.render-failed`). High risk themes need the slug typed; the "block high risk plugins" switch stays plugin only. Shown on the review screen, on the new theme detail page `admin/themes/{theme}` (with "Run checks again") and as a risk badge on both theme lists.
- `php artisan verso:verify-theme <folder|zip> [--json] [--no-sandbox]`, which fails on high risk or blocking findings.
- Limits in `config/verso.php` (`extensions.verification.themes.*`) and System settings: screenshot KB, width and height, demo posts, preview HTML KB; font hosts by env.
- Bundled themes declare `requires` and `php`. `default` and `ashgrove` pass with low risk and no blocking findings (covered by tests).
- Tests: fixture themes in `tests/Fixtures/themes` (harbor-light, saltmarsh, driftwood, broken-pier, half-built, orphan-tide), Pest and Vitest.

Migration: `2026_11_20_100000_add_kind_to_plugin_verifications_table` (adds `kind`, default `plugin`, index on kind and slug).

Still open:

- Real screenshots of the rendered pages (needs a headless browser on the server); the iframe preview stands in for now. Theme fonts and images do not load in the preview, because the theme is not installed yet and the CSP blocks requests.
- A Blade error points to the view file but not the line.

### Modern Web Guidance pass on the frontend (feat/mwg-pass)

Status: done, except the items under Still open. Each pattern followed its guide from `npx -y modern-web-guidance@latest` (skill version 2026_11_10-47b4c80e), with the guide fallbacks for features that are not Baseline yet. Also covers the easy mode screens.

Done:

- Popover and dialog (`components/ui/top-layer.tsx`): `Dialog` and `Sheet` are a native `<dialog>` opened with `showModal()` (inert page, focus kept inside, Escape, top layer), `closedby="any"` with a pointer fallback, the closing transition runs before `close()`, focus goes back to the opener and `onCloseAutoFocus` still works. `Popover` is a native `popover="auto"` (light dismiss, Escape) placed with CSS anchor positioning and flip fallbacks; without anchor positioning the script placement stays, now RTL aware and flipping to the other edge. The open animation and `transition-none` on position are kept. Radix menus, selects and tooltips show their position wrapper as a manual popover in the top layer. Floating UI inside a dialog or popover is portaled into it (`usePortalContainer`), so the modal never makes it inert. Scroll under a modal is contained with `overscroll-behavior`, not `overflow: hidden` on the body. Sheet sides are logical, so the navigation sheet opens on the right and the help panel on the left in Arabic.
- Date and time picker, selects, forms: the date picker trigger is a combobox that reads the picked date, arrow keys follow RTL, the time stays hours first, the time fields gain Home, End, Page Up and Page Down. `Select` renders a customizable `<select>` (`appearance: base-select`, `resources/css/select.css`) where supported and the Radix select elsewhere; the picker opens below first. `SearchableSelect` keeps its search with full combobox semantics. Inputs show errors only after use (`:user-invalid`), `lib/user-invalid.ts` keeps `aria-invalid` in step and announces new errors, server errors stay first. Autocomplete tokens, `inputmode`, `enterkeyhint`, `required` and length limits match the server rules; focus moves to the first error after a failed submit.
- Dark mode: admin color tokens keep `-light` and `-dark` values and use `light-dark()` with the dark class as fallback; the server writes the appearance to the `color-scheme` meta tag, which CSS maps to `color-scheme`, so the first paint is right and the system choice works without script. The client reads the cookie first, wraps storage in try/catch, keeps the meta tag in step and follows other tabs; unknown cookie values fall back to light. Themes: dark values also work in browsers without `light-dark()` or `:has()`.
- Data tables: `scope="col"`, sortable headers are buttons with `aria-sort`, stacked cards switch on the table container width (under 40rem), the sticky header works with page scroll.
- View transitions and animations: admin page changes cross fade with a short same document view transition (`lib/page-transitions.ts`; not for searches, filters, partial reloads or reduced motion), the side bar and top bar keep their names. Default and Ashgrove use cross document transitions with the card title moving into the entry title, and menu popovers animate with `@starting-style`; all respect `prefers-reduced-motion`.
- Images and Core Web Vitals in themes: the LCP image is eager with `fetchpriority="high"`, others lazy, with width, height, srcset and sizes (already in place, now covered by tests); `content-visibility: auto` with a remembered size for long card lists, comments and related posts; size matched fallback fonts for Ashgrove.
- HTML sanitization and accessibility: `SafeHtml` shows server sanitized HTML (release notes) and checks it again with `setHTML()` where the Sanitizer API exists; the server sanitizer stays the source of truth. Forced colors mode gets real outlines for focus, the current nav item and selected options, and borders on badges; dialog close buttons are 24px targets with a focus-visible ring; skeleton pulse and smooth scroll respect reduced motion; theme footer links are 24px tall.
- `legacy-scan resources/js themes plugins` reports no findings.

Guides applied: viewport-aware-dropdowns, dialog, light-dismiss-a-dialog, animate-to-from-top-layer, prevent-page-scroll-under-modal-dialog, menu, interest-triggered-tooltips, capture-location-agnostic-data, branded-select-styling, custom-select-picker-layouts, select-menu-interaction, validate-input-after-interaction, accessible-error-announcement, forms, autofill-sign-in-form, autofill-sign-up-form, dark-mode, dark-mode-toggle, color, responsive-table, sortable-table, same-document-transitions, cross-document-transitions, consistent-cross-document-transitions, animate-element-entry-exit, motion, optimize-image-priority, performance, defer-rendering-heavy-content, visually-stable-font-fallbacks, sanitize-untrusted-html, accessibility.

Still open:

- Radix still drives the keyboard of menus, tooltips and the fallback select; a native focusgroup menu can replace it once focusgroup is Baseline.
- Popover light dismiss in browsers without the Popover API uses the old script fallback; no polyfills are loaded.
- Errors found by the browser before submit show a red border and are announced, but no text appears under the field until submit.
- The native select picker has no open fade, because the CSS minifier rejects the selector.
- Not checked in Firefox, Safari or with a real screen reader; Chromium, Vitest and Pest cover the new paths. Full page screenshots without scrolling show deferred theme lists as blank space.
- Paths in Arabic tables show the slash on the wrong end (older issue).

Checks: 5136 Pest tests (parallel), pint, phpstan, types:check, 1821 Vitest tests, build, `verso:lang-extract --check` and legacy-scan (0 findings) pass. Checked with Playwright in Chromium at 3440, 1440 and 390, light and dark, and in Arabic for the admin primitives.

### Admin configuration (feat/admin-config)

Done: inventory of every operational parameter with classes (a) to (e) and the PHP and web server limit study in `docs/CONFIGURATION.md`; configuration registry and boot overlay (env priority, config:cache safe, invalid values reported); System settings page (groups, plugin groups, search, units, reset, changed filter, effective upload limits, PHP limit snippets and a consented Apply to `.user.ini` or `.htaccess`, audit log); Server health page (PHP, extensions, ini scopes, folders, modes, risky states, dry run and safe fixes); runtime memory and time raise in imports, image work, updates and exports; `manage_system` capability.

Ideas for later: show the effective upload limit on the media, import and extension upload screens; a `verso:system` command to read and set registry values from the shell; an HTTP self check that `.env` and `storage` return 403 or 404.

## Admin menu and wide lists (feat/menu)

Status: done

Menu:

- Six groups: Dashboard (Analytics is a tab on the dashboard) | Content: Posts (Categories, Tags), Pages, Media, Comments, then plugin content types (Products with Product categories) | Design: Site Editor, Themes, Menus, Custom code | Tools: one item per plugin (Shop > Orders, Products, Settings; Forms > Forms, Messages, Settings; Newsletter > Campaigns, Subscribers, Settings; Members > Members, Plans) | Administration: Users (Invitations), Plugins and themes, Import, Updates, System (System settings, Server health) | Settings: General, Permalinks and SEO (Redirects), Editor, Custom fields, AI Assistant.
- `NavItem` has `parent`, `keywords`, `plugin` and `fragment`; `NavGroup` has the six groups. Items check their capability and the `can:` rules of their route; links to missing routes are never sent.
- Plugins declare `admin` in `verso.json` (group, label, icon, keywords, items), checked by `PluginManifest`. Core places the items (`PluginNavPlacement`). Bundled plugins and the `verso:make-plugin` stubs use it. Inventory is now Products.
- Sidebar: sections and parents open and close, remembered per user in localStorage (try/catch), current section and parents always open, long labels wrap, the current link scrolls into view. Mobile sheet uses the same tree.
- Discoverability: toast "<Plugin> is ready. You will find it in Tools > <Plugin>" with Open, the item is highlighted, "New" badges per user (server side, `users.admin_preferences`, `verso.admin.nav.new_badge_days`), Cmd+K searches items, children and keywords without accents and shows the path ("pedidos" finds Tools > Shop > Orders), plugin cards say "Where it is: Tools > Shop".
- Plugins and Extensions are one page, Plugins and themes, with tabs Installed plugins, Installed themes, Add new, Browse. `/admin/extensions` redirects.

Lists:

- `DataTable` columns can be optional with a default width (container queries on `@container/table`), a View menu picks columns and density, saved per user (`PUT admin/list-views/{list}`). The title keeps a sensible width.
- Posts, pages and products get featured image, taxonomies, comments, words, last modified and SEO status; products also price, stock and SKU (`AdminEntryColumnsFilter`). Comments, users, form messages and shop products have optional columns too.
- The entries list still runs 5 queries (budget 6): eager terms and images, `withCount`, and the status counts give the total.

Checks: 4133+ Pest tests, pint, phpstan, types:check, 938+ Vitest tests and build pass. Screenshots with the `menu-` prefix are in `.verso-state/screenshots`.

## Pending items (feat/pending)

Status: done. One or more commits per item, pushed to `feat/pending`.

1. Dark mode switch: site block `color-scheme-toggle` ("Dark mode switch") in `SiteServiceProvider::SITE_BLOCKS`, so it is in the block registry and the Site Editor. Two state toggle from the Modern Web Guidance pattern: no saved value follows the system, the choice is kept in `localStorage` (`verso-color-scheme`) and set on `<meta name="color-scheme">`, a tiny head script (only when a published part holds the block, `SitePartStore::uses()`) applies it before the first paint, the button is hidden without JavaScript and shows the action with text for screen readers. Default and Ashgrove map the meta tag to `color-scheme` with `light-dark()` and a fallback; default theme customizer colors now write `--bg-light`, `--text-light` and `--accent-light`, so dark mode keeps its own colors.
2. "Create child theme" in the Site Editor (`ChildThemeFromChanges`, `POST admin/site-editor/child-theme`): theme.json with `parent`, changed customizer values and tokens as defaults plus `assets/theme.css`, published parts as `parts/<key>.json` rendered by `<x-verso-part-blocks>`, the parent screenshot and the part styles in use. The theme is checked on creation; a real child of Ashgrove passes `php artisan verso:verify-theme` with no findings.
3. Header and footer per template: `templates.<name>.parts` in theme.json, a choice per page template on Site Editor, Templates (`theme.template_parts.<slug>`) and per entry (`entries.meta.parts`, selects in the editor Template card). `TemplatePartChoices` applies them while an entry renders. Default and Ashgrove gain a `minimal` header and a `landing` page template.
4. Public profile: bio, photo (uploaded to the media library, Glide sizes) and social links on Account settings, Profile; users get a stable `slug`; `/author/{slug}` (views `author-{slug}`, `author`, `archive`) lists the published posts of an author and is a 404 for people without posts. Default and Ashgrove show the author box with social links and link author names. Limits in `verso.users.profile.*` and the System settings registry.
5. QA leftovers: Remove on the plugin detail page with the shared dialog (back to the list after removal), focus moves to the new page heading after Inertia visits to another path (query only visits like live search keep focus), newsletter signup title level h2 by default with h3 and h4 to pick.
6. `default` and `ashgrove` ship a 1200x900 `screenshot.png` of their home page (Playwright, demo content), so theme verification has no screenshot finding.

Also: `phpunit.xml` sets `memory_limit` to 512M, because the parallel runner ran out of the default 128 MB while loading the suite.

Migration: `2026_11_16_140300_add_author_page_to_users_table` (adds `users.social_links` and a unique `users.slug`, filled from the names of existing users). New env keys: `VERSO_PROFILE_BIO_MAX`, `VERSO_PROFILE_SOCIAL_LINKS_MAX`, `VERSO_PROFILE_AVATAR_MAX_KB`, `VERSO_AUTHOR_BASE`.

Still open:

- The child theme keeps the part style choices as saved settings of the new theme, not in its theme.json. When a part has a non default style and only the base part was edited, the child shows the edited blocks inside that style file, which can differ from the parent.
- Menus are not copied into a child theme (they are shared by every theme). Draft parts are left out on purpose.
- The dark mode switch was checked in a real browser on Ashgrove (switch, saved choice, reload without flash); not on old browsers without `light-dark()`, where the CSS fallback is used.
- Ashgrove post cards still print the author name without a link; only the author box and the default theme meta link to the author page.
- Screen readers were not used for the new focus behaviour; it is covered by Vitest only.

Checks: 4259 Pest tests, pint, phpstan, types:check, 992 Vitest tests, build and legacy-scan pass.


## CSS units (feat/css-units)

Status: done

Done:

- One shared check for every size value: `App\Verso\Core\Css\CssValueParser` in PHP and `resources/js/lib/css-value.ts` in TypeScript, with `tests/Fixtures/css-values.json` (unit lists and 130 cases) read by Pest and Vitest. It knows every CSS length unit (absolute, text relative, screen with `sv*`, `lv*`, `dv*`, container, `%`), angle and time units, the keywords `auto`, `fit-content`, `min-content`, `max-content`, `none`, `normal`, plain numbers where allowed, and custom values made of `calc()`, `clamp()`, `min()`, `max()` and `var(--name)` only (200 characters, 6 levels). `url()`, other functions, words, `;`, `{`, `}` and comments are refused. Values are stored as clean strings; old plain numbers on a length read as pixels.
- `CssValueRule` per property (units, keywords, unitless, negative, custom). Every size `StyleProperty` (kinds `length`, `angle`, `time`) carries one, from `CssValueRule::forProperty()` or given by the plugin, and sends it in the editor manifest. Line height is now a `length` with plain numbers and `normal`. New core options: height, min width, max height, right, bottom and left.
- `LengthInput` (`resources/js/components/verso/ui/length-input.tsx`): number field plus a searchable unit picker (common units first, groups, keywords, No unit, Custom), keeps the number when the unit changes, arrow keys step per unit with Shift for ten, Escape reverts, errors after leaving the field with `aria-invalid` and `aria-describedby`. `SearchableSelect` got `triggerClassName` and `showSelectedDescription`.
- Used in the block Styles panel (entries and Site Editor parts), Site Editor size tokens (global styles), theme customizer `size` fields and the new `length` field type (optional `units`, `keywords`, range), block fields of the new `BlockFieldType::Length`, and the logo height. Customizer whole numbers in the field unit stay numbers, so saved data and theme.json files keep working.
- Theme limit `editor.units` in theme.json narrows every unit picker; `editor.spacing` now takes any safe size.
- Docs: "CSS sizes and units" in `EXTENDING.md`, block styles and customizer in `ARCHITECTURE.md`, `editor` key in `THEMES.md`. The builder example plugin adds an angle property (`effects.rotate`).
- Checked in a browser: Styles panel unit picker, Shift+arrow steps, search by plain name ("dynamic height" picks dvh), clamp() in Custom mode, error on `url(x)`, saved and printed on the public page as `padding-top:12dvh;font-size:clamp(1.5rem, 1rem + 2vw, 3rem);line-height:1.15;letter-spacing:-0.02em`. Customizer has no horizontal scroll at 390 px.

Still open:

- `editor.units` only limits the pickers; the server accepts any allowed unit, so a theme switch never breaks saved content.
- The column block `width` (whole percent, no editor control) and image width and height (pixel sizes of the file) were left as numbers.
- Custom values are checked for safety and units, not for type mixing (`calc(1px + 1deg)` in a length with angle units off is refused, but `calc(1px * 1px)` is not).
- In inspector panels narrower than about 19rem the Styles panel shows one size per row.

## Roles and permissions (feat/roles)

Status: done, except the items under Still open.

Done:

- Capability registry (`App\Verso\Access`): every capability with label, help text, group (Content, Design, Tools, Administration, Settings, plugin name), section and risk. Sources: core enum, seven capabilities per content type (`entries.<type>.create|edit_own|edit_others|publish|delete_own|delete_others|read_private`), plugins (`capabilities` in `verso.json`) and the active theme (`theme.json`). New core capabilities: `read_private_entries`, `edit_others_media`, `edit_site`, `manage_menus`, `list_users`, `create_users`, `edit_users`, `promote_users`, `delete_users`, `manage_roles`, `install_plugins`, `activate_plugins`, `manage_general_settings`, `manage_permalinks`, `manage_editor_settings`, `manage_fields`. Old names still work; umbrellas imply the new ones. Plugins: `forms.manage`, `forms.messages`, `newsletter.manage`, `memberships.manage`, `analytics.view`.
- Roles: built-in roles (Administrator locked, Editor, Author, Contributor, Subscriber, Shop manager when the Shop is on) and plugin roles can be changed, cloned and reset but not deleted; custom roles can be created, changed, cloned and deleted. Built-in roles store only their changes, so new plugin defaults still arrive.
- Users have a main role, extra roles and single grants or denies. Administrators keep everything. Nobody gives what they do not have, nobody raises their own access (taking away your own deny counts), the last administrator (main or extra role) stays.
- Admin: Administration > Users > Roles with the matrix (grouped, searchable, group filter, "only differences", tri-state "select group", sticky header row and first column, unsaved changes bar), a page per role (members, defaults diff, change log, clone, reset, delete, view as), compare view, and a user access page (main role, extra roles, grant or deny per capability with a live preview). The users list shows every role and follows `can`.
- Enforcement: every admin route has a `can:` middleware (allow list: dashboard, list views, leaving a preview), checked by a test over all routes, also the bundled plugin routes. Policies use the fine capabilities (entries per type, media, users). Nav items, Cmd+K (built from the nav), dashboard stats, panels, notices and slot widgets are filtered by capability, gate ability or closure. Inertia shares `can` and `viewAs`. Entry lists hide unpublished entries of others without `read_private`. Editor tools, field rules and the analytics role filter look at every role of a user; a cloned role gets the editor tools of its source.
- Audit log: role and access changes (who, when, what) go to the existing `system_config_changes` table and show on the role and user access pages; the System settings log hides them.
- View as role: administrators preview the admin as a role, read only, with a banner and an exit button.
- Docs: EXTENDING.md "Capabilities and roles", ARCHITECTURE.md "Roles and capabilities", CONFIGURATION.md (`verso.access.*`, forms and analytics defaults).
- Tests: 131 Pest tests in `tests/Feature/Access` (registry, roles, permissions, escalation, every route, dashboard and nav per capability, content types, plugin and theme manifests, view as, migration), 54 Vitest tests for the roles UI. Checked with Playwright as admin and editor at 3440, 1440 and 390.

Migration: `2026_11_21_100000_create_roles_tables` (`roles`, `role_user`, `user_capabilities`).

Upgrade checklist:

1. Run `php artisan migrate`. Nothing is copied: `users.role` stays the main role, the new tables start empty, so every user keeps the access they had.
2. Editors keep forms, newsletter and media of others through the new plugin defaults. Memberships and Analytics stay admin only (`memberships.manage`, `analytics.view`).
3. Sites that set `FORMS_CAPABILITY` or `ANALYTICS_CAPABILITY` keep their value. `FORMS_MESSAGES_CAPABILITY` (default `forms.messages`) is new.
4. Custom code that checked `Capability::ManageUsers` or `ManageSettings` directly still works for admins; prefer the fine capabilities (`list_users`, `manage_general_settings` ...).
5. Code that reads `$user->role` still gets the main role. Use `$user->roleSlugs()`, `hasRole()` or `User::withRole()` to include extra roles.

Still open:

- Strings are plain English, waiting for the i18n pass.
- The audit lines of a user show old and new roles and overrides as data; a friendlier "what changed" text could be added.
- Role page members link to their access page even when the viewer may not manage them (the server answers 403).
- The public REST API is read only and shows published content only, so it needs no capability; there is no authenticated API yet.

## Basic SEO (feat/seo-basics)

Status: done, merged into main.

Done:

- SEO overview (Settings > Permalinks and SEO > SEO overview, capability `seo.view`): a site score from 0 to 100, counts per severity, the traffic lights of published entries and 14 checks with plain words, a "See the list" page per check (filter by type, search, pages) and fixes: one click for the sitemap and the "discourage search engines" switch (also needs `manage_permalinks`), links to the right screen for the rest. Checks: search engines discouraged, sitemap off, no site description, broken internal links, missing meta description, title too long or short, duplicate titles, duplicate descriptions, images without alt text, hidden (noindex) pages in menus, posts without a featured or share image, orphan pages, slow pages, 404s. The checks are a registry (`SeoCheckRegistry`), so plugins add theirs.
- Link check: queued batches read every published entry (button, `php artisan verso:seo-check-links`, weekly schedule) and store internal links with their status, link counts and the time the content takes to build. Broken links, orphan pages and slow pages come from it.
- Bulk "Add alt text" screen with suggestions from the AI Assistant through the new `AltTextSuggesterFilter`; a suggestion is a preview until "Use it" and Save.
- 404 monitor (Settings > Permalinks and SEO > 404 monitor): public GET 404s are counted after the response; only the path, hits, dates and the referrer host are kept (no IP, a hashed IP is only a cache rate limit key), with ignore patterns, per visitor and site limits, a row cap and daily pruning. Rows show the closest published page and "Create redirect" uses the existing redirects.
- Editor SEO card: focus keyphrase, a score ring, SEO and readability checks (keyphrase in title, introduction, slug, description and subheadings, density, lengths, links, image alt, single H1, sentence and paragraph length, subheadings, heading levels, passive voice in English, transition words in English and Spanish) that run in the browser after typing stops, Google previews for computer and phone, Facebook and X share previews, share title and description, canonical, noindex, nofollow and sitemap switches. The client checks are a registry (`registerAnalysisCheck`). The score is saved with the entry after a change (opening an entry never marks it changed), and the entries list SEO column shows it as a traffic light.
- Structured data: posts are `BlogPosting` (`verso.seo.schema_types`), the author is a `Person` linked to the author page with bio and social profiles, author pages print a `ProfilePage`, `details` element blocks (and any `faq` block with `items`) give a `FAQPage`. Breadcrumbs and `WebPage` stay. Tests decode the JSON-LD and check types, ids and positions.
- Capabilities `seo.view` and `seo.manage` in the enum and `CoreCapabilities` (editors and admins by default).
- Every limit lives in `config/verso.php` (`verso.seo.analysis`, `audit`, `crawl`, `not_found`, `alt_text`) with env vars in `.env.example`; the main ones are in System settings, SEO.

Migrations: `2026_11_16_131000_create_seo_not_found_table`, `2026_11_16_131100_create_seo_link_check_tables` (`seo_page_reports`, `seo_links`).

Still open:

- "Slow pages" measures the server time to build the content, not real visitor speed: the Analytics plugin keeps no timings. A plugin check could add field data later.
- Verso has no FAQ block yet; FAQ schema reads `details` element blocks and any block named `faq`. A friendly FAQ block would make it easier to use.
- The link check only follows links in entry content and menus; links in site parts (header and footer blocks), term descriptions and widgets are not read, so a page linked only from the footer can show as an orphan.
- The editor reads the slug of the saved entry, not the slug field while it is being changed.
- The 404 path column has no binary collation on MySQL, so `/About` and `/about` share one row there.
- Strings are plain English, waiting for the i18n pass. The Spanish word lists of the analysis are small.
- The snippet preview cuts by characters, not by pixel width like Google.

Upgrade checklist:

1. Run `php artisan migrate`.
2. Make sure a queue worker runs (the link check is queued) and the scheduler runs (weekly link check, daily 404 clean up).
3. Run "Check links now" once on the SEO overview, or `php artisan verso:seo-check-links`.
4. Custom roles: give `seo.view` and `seo.manage` where needed (editors and admins get them by default).
5. Optional: set `VERSO_404_IGNORE`, `VERSO_404_RETENTION_DAYS` or `VERSO_SEO_CRAWL_SCHEDULE` (empty turns the automatic check off).

Checks: 4625 Pest tests (parallel), pint, phpstan, types:check, 1406 Vitest tests, build and legacy-scan pass. Overview, 404 monitor and editor SEO card checked in a browser at 3440, 1440 and 390 px (no horizontal scroll; opening the editor does not mark it changed).

## Admin i18n (feat/i18n)

Status: done, except the items under Still open. Guide: docs/I18N.md. Glossary: docs/I18N-GLOSSARY.md. Plugins and themes: "Translations" in docs/EXTENDING.md.

Done:

- 15 admin languages: English (source), Spanish, Simplified Chinese, Hindi, Arabic (right to left), Brazilian Portuguese, Bengali, Russian, Japanese, French, German, Indonesian, Turkish, Korean and Italian, with about 4,470 strings each (core, the 8 bundled plugins and both themes). `VERSO_ADMIN_LOCALES` limits the list.
- One system for server and client: `lang/<code>.json` (keys are the English text) read by Laravel `__()` and by the browser bundle `/i18n/<code>/<version>.json` (core plus enabled plugins and the active theme, versioned by a file hash, cached for a year, preloaded, loaded before the first render, never in the JS bundle). `t()` and `msg()` with ICU arguments, plurals (Intl.PluralRules) and select; `Trans` for links inside sentences; PHP `Translate::icu()` with the same ICU rules and CLDR plurals without intl. Dates, numbers, money, percents, relative times, lists and file sizes use Intl in the admin language (date time picker, tables, stats, charts).
- Laravel validation, auth, password and pagination messages in every language (Laravel Lang, MIT; translated validation messages use the inline form shown under each field).
- Per user admin language (`users.admin_locale`, migration `2026_11_22_100000`), default the site language, else English. Switchers in the user menu, Settings > Profile (with "Same as the site"), the login screens and the installer (browser language detected, picked language kept at sign in). Mail and notifications use the receiver's admin language. The site language stays separate; the editor canvas uses the site language and its direction.
- RTL: `<html lang dir>` follows the admin language, Radix gets the direction, physical utilities became logical (`ms`, `pe`, `start`, `border-s`, `text-end`), sheets slide from the start or end, direction icons are mirrored, user titles are isolated with `<bdi>`.
- Plugins and themes ship `lang/<code>.json`, namespaced by slug (`shop::Orders`); registry labels (nav, dashboard stats, System settings, capabilities, roles, blocks, content types, theme.json labels) are translated when sent, plugin key first, then core. Cmd+K matches translated and English words.
- Every visible admin string is extracted (pages, components, nav, Cmd+K, toasts, empty states, errors, aria labels, titles, flash and validation messages, mails, verification findings, SEO checks). Cached reports (SEO audit, plugin verification) store English sentences with values and translate them on display.
- Checks: `php artisan verso:lang-extract` (`--check`, `--missing`, `--prune`), `node scripts/i18n/hardcoded-strings.mjs` with an allow list, Vitest `hardcoded-strings.test.ts` (fails on English text outside `t()`), Pest `LangFilesTest` (every language has every key, same ICU arguments, `:placeholders` and tags, complete plurals, Laravel files, catalogs in step with the code).
- Browser check (Playwright) of the dashboard, the posts list, the entry editor and General settings in es, de, ar and ja at 1920 and 390 px, and ja at 3440 px: right `lang` and `dir`, no horizontal scroll, no untranslated admin text, main area uses the full width.

Checks: 4757 Pest tests (parallel), pint, phpstan, types:check, 1451 Vitest tests, build and legacy-scan pass.

Still open:

- Translations were written by language models with a glossary and automatic checks; a native review per language is still worth doing (each glossary lists the choices that were unsure).
- Log entries and console output stay English. Update step messages and import warnings are saved in the language of the person or worker that ran them; order history notes of the shop stay English.
- Content that people typed (default texts of new form and signup blocks, `Form::DEFAULT_SETTINGS`, the memberships teaser default) is not translated.
- The plugin block category "Examples" of the Builder Example plugin and the alt text suggester name of the AI Assistant are not translated yet.
- The week in the date picker always starts on Monday; it could follow `Intl.Locale.getWeekInfo()`.
- Mixed direction sentences built on the server (like "Marco Bellini on A Slow Weekend in Porto" in Arabic) do not isolate the inserted names yet.

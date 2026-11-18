# Extending Verso

Plugins change Verso in two typed ways:

- **Filters** change a value on the server (a query, some HTML, a list of tags). Each filter is a class in `app/Verso/Plugins/Filters` with a typed static `apply()` method.
- **Admin slots** add React components to admin screens without editing core files. A slot item is described in PHP and the component is registered in JavaScript.

Run `php artisan verso:make-plugin "Harbor Weather"` to get a plugin that already shows one filter, one slot and an admin page under Tools > Harbor Weather.

## Admin menu

Declare where your admin pages go in the `admin` part of `verso.json`. Core checks it and places the items, so every plugin looks the same in the sidebar:

```json
"admin": {
    "group": "tools",
    "label": "Shop",
    "icon": "shop",
    "keywords": ["store", "tienda"],
    "items": [
        { "key": "orders", "label": "Orders", "route": "admin.shop.orders.index", "icon": "orders", "capability": "shop.manage", "keywords": ["sales", "pedidos"] },
        { "key": "settings", "label": "Settings", "route": "admin.shop.settings.edit" }
    ]
}
```

Rules:

- `group` is one of `dashboard`, `content`, `design`, `tools`, `administration`, `settings`. Plugins cannot add groups, and all items of a plugin go to the one group they declare.
- In `tools`, and for any plugin with more than one item, core adds one parent item (`plugin.<slug>`, with `label` or the plugin name and `icon`) and puts the items inside it. It links to the first item the user may open. A single item in another group is a plain item of that group (the AI Assistant is Settings > AI Assistant). `dashboard` items become tabs on the dashboard (Analytics).
- Content types you register with `registerContentType()` always go in Content, after Media and Comments, with their taxonomies inside.
- Each item has a `key` (lowercase words with dashes; the nav key is `<slug>.<key>`), a `label`, a `route` (an `admin.*` route name), an optional `icon`, `capability` and `keywords` (search words and synonyms for Cmd+K, also in other languages). The item is shown only when the user has the capability and passes every `can:` middleware of the route, and only when the route exists.
- Limits: `verso.admin.nav.max_plugin_items` items and `verso.admin.nav.max_keywords` words per item.
- Items you still add in PHP with `$this->addNavItem()` are moved to the declared place (under Tools > your plugin when there is no `admin` part).
- After an admin enables your plugin, the toast says where it is ("Shop is ready. You will find it in Tools > Shop") with an Open button, the item is highlighted and shows a "New" badge until each user opens it. The plugin card shows "Where it is: Tools > Shop".

## Filters

Add a callback in the `boot()` method of your plugin provider:

```php
use App\Verso\Plugins\Filters\BodyClassFilter;

$this->addFilter(BodyClassFilter::class, fn (array $classes): array => [...$classes, 'with-weather']);
```

Rules:

- A callback gets the value first, then the context, and returns a value of the same type. A wrong type throws `InvalidFilterResult`.
- Callbacks run by priority (third argument of `addFilter()`, default 10). Lower runs first. Same priority runs in the order they were added.
- Filters that change public pages (head tags, body classes, blocks, SEO) end up in the page cache. Never make them depend on the visitor.
- Outside a plugin provider, use the facade: `Filters::add(BodyClassFilter::class, $callback)`.

### Public pages

| Filter | Callback | Applied |
|---|---|---|
| `EntryQueryFilter` | `fn (Builder $query, ArchiveContext $context): Builder` | Entry query of the home list, archives, term pages and search, before paging |
| `SearchResultsFilter` | `fn (LengthAwarePaginator $results, string $search): LengthAwarePaginator` | The page of public search results |
| `BlockHtmlFilter` | `fn (string $html, array $block, ?Entry $entry): string` | HTML of each top level block and plugin child block |
| `EntryContentFilter` | `fn (string $html, Entry $entry): string` | HTML of the whole entry |
| `HeadTagsFilter` | `fn (array $tags, Request $request): array` | Tags printed by `<x-verso-head />` in the `<head>` (list of `HeadTag`) |
| `BodyClassFilter` | `fn (array $classes, Request $request, ?Entry $entry): array` | Classes of `<body>` (the `$bodyClass` layout variable) |
| `ThemeFooterFilter` | `fn (string $html, Request $request): string` | HTML printed by `<x-verso-footer />` before `</body>` |
| `SeoMetaFilter` | `fn (SeoPage $seo, ?Entry $entry): SeoPage` | Title, description, robots, Open Graph, Twitter and schema of a page |
| `SitemapEntriesFilter` | `fn (array $urls, string $sitemap, int $page): array` | Rows (`SitemapUrl`) of one sitemap file, like `post` or `taxonomy-category` |
| `FeedItemsFilter` | `fn (array $items, string $feedUrl): array` | Items (`FeedItem`) of the RSS feed |
| `PublicRoutesFilter` | `fn (Router $router): Router` | Adds public routes before the theme routes, with the public middleware |
| `TemplateCandidatesFilter` | `fn (array $candidates, TemplateContext $context, ?object $subject): array` | Theme views tried for a page, in order (see the template hierarchy in THEMES.md) |
| `PrivatePathsFilter` | `fn (array $patterns): array` | Public paths that belong to one visitor, like `/checkout` or `/orders/*`. The Analytics plugin never counts them |

`ArchiveContext` has `kind` (`ArchiveKind::Home`, `Archive`, `Taxonomy` or `Search`), `contentType`, `taxonomy`, `term` and `search`.

```php
use App\Verso\Plugins\Filters\EntryQueryFilter;
use App\Verso\Theme\Enums\ArchiveKind;
use App\Verso\Theme\Support\ArchiveContext;
use Illuminate\Database\Eloquent\Builder;

// Hide sponsored posts from the blog archive.
$this->addFilter(EntryQueryFilter::class, fn (Builder $query, ArchiveContext $context): Builder => $context->is(ArchiveKind::Archive)
    ? $query->where('meta->sponsored', '!=', true)
    : $query);
```

```php
use App\Verso\Plugins\Filters\HeadTagsFilter;
use App\Verso\Plugins\View\HeadTag;

// HeadTag escapes attributes. Allowed elements: meta, link, script, style.
$this->addFilter(HeadTagsFilter::class, fn (array $tags): array => [
    ...$tags,
    HeadTag::meta(['name' => 'theme-color', 'content' => '#0f766e']),
    HeadTag::script('https://maps.example.org/embed.js', ['defer' => true]),
]);
```

```php
use App\Verso\Plugins\Filters\SeoMetaFilter;
use App\Verso\Site\Seo\SeoPage;

// SeoPage is read only. The with...() methods return a changed copy.
$this->addFilter(SeoMetaFilter::class, fn (SeoPage $seo, ?Entry $entry): SeoPage => $entry?->type === 'product'
    ? $seo->withTitle($entry->title.' | Harbor Shop')
    : $seo);
```

```php
use App\Verso\Plugins\Filters\SitemapEntriesFilter;
use App\Verso\Site\Sitemap\SitemapUrl;

$this->addFilter(SitemapEntriesFilter::class, fn (array $urls, string $sitemap): array => $sitemap === 'page'
    ? [...$urls, new SitemapUrl(url('/events'), now()->toAtomString())]
    : $urls);
```

```php
use App\Verso\Plugins\Filters\FeedItemsFilter;
use App\Verso\Theme\Support\FeedItem;

$this->addFilter(FeedItemsFilter::class, fn (array $items): array => [
    ...$items,
    new FeedItem('Podcast: Walking the Rota Vicentina', url('/podcast/12'), now(), '<p>Episode 12.</p>'),
]);
```

Public routes: use the helper, which wraps `PublicRoutesFilter`.

```php
use Illuminate\Routing\Router;

$this->addPublicRoutes(function (Router $router): void {
    $router->get('events/{slug}', ShowEvent::class)->name('theme.events.show');
});
```

Name public routes `theme.*` so a missing page shows the theme 404 view.

### Mail, admin and permissions

| Filter | Callback | Applied |
|---|---|---|
| `OutgoingMailFilter` | `fn (OutgoingMail $mail): OutgoingMail` | Before every mail is sent |
| `AdminNavFilter` | `fn (array $items, ?User $user): array` | Sidebar items (`NavItem`, flat, children have `parent`) one user sees, before parents and children are linked |
| `AdminEntryRowFilter` | `fn (array $row, Entry $entry, User $user): array` | Data of one row of the admin entries table |
| `AdminEntryColumnsFilter` | `fn (array $columns, ContentType $type): array` | Optional columns (`ListColumn`) of the admin entries table of one content type |
| `EntryCapabilityFilter` | `fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool` | Every entry permission check (`viewAny`, `create`, `update`, `publish`, `delete`, `restore`), after the per content type capabilities |
| `UploadRulesFilter` | `fn (array $rules, UploadedFile $file, ?User $uploader): array` | Validation rules of the `file` field of a media upload |
| `ApiEntryFilter` | `fn (array $data, Entry $entry): array` | JSON of an entry in the public API |
| `AltTextSuggesterFilter` | `fn (?AltTextSuggester $suggester, User $user): ?AltTextSuggester` | Offers alt text suggestions on SEO > Add alt text (the AI Assistant uses it). The endpoint gets `POST {"media_id": 12}` and answers `{"result": {"alt": "..."}}`; suggestions are only a preview |

```php
use App\Verso\Plugins\Filters\OutgoingMailFilter;
use App\Verso\Plugins\Mail\OutgoingMail;

// Change the Symfony Email in place, or return $mail->cancel() to stop it.
$this->addFilter(OutgoingMailFilter::class, function (OutgoingMail $mail): OutgoingMail {
    $mail->message->getHeaders()->addTextHeader('X-Site', 'Harbor Lane');

    return in_array('blocked@example.org', $mail->recipients(), true) ? $mail->cancel() : $mail;
});
```

```php
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Plugins\Filters\EntryCapabilityFilter;

// Authors may not delete published entries.
$this->addFilter(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $ability === EntryAbility::Delete && $entry?->isPublished() && ! $user->hasCapability(Capability::DeleteEntries)
    ? false
    : $allowed);
```

```php
use App\Verso\Plugins\Filters\UploadRulesFilter;

$this->addFilter(UploadRulesFilter::class, fn (array $rules): array => [...$rules, 'dimensions:max_width=4000']);
```

## Admin slots

A slot is a place in an admin screen. Items are shared as the `versoSlots` prop, only on the screen of the slot and only with users who have the item capability.

| Slot (`AdminSlot`) | Name | Screen | Component context |
|---|---|---|---|
| `EntrySidebar` | `entry-sidebar` | Entry editor side panel | `EntryEditorContext` |
| `EntryPublish` | `entry-publish` | End of the Publish card | `EntryEditorContext` |
| `EntryToolbar` | `entry-toolbar` | Entry editor header, before Save | `EntryEditorContext` |
| `EntriesColumns` | `entries-columns` | Entries table column (label = header) | `EntryRowContext` |
| `EntriesRowActions` | `entries-row-actions` | Row "..." menu of the entries table | `EntryRowContext` |
| `DashboardWidgets` | `dashboard-widgets` | Dashboard, under the stats | `{}` |
| `DashboardTop` | `dashboard-top` | Dashboard, above the stats (Easy mode puts the launch checklist here) | `{}` |
| `SettingsTabs` | `settings-tabs` | Tab on the site settings page (label = tab name, `?tab=key`) | `{}` |
| `ProfileSections` | `profile-sections` | End of the user profile page | `{}` |
| `PageEditor` | `page-editor` | Easy mode page editor; the component is mounted but shows nothing by itself (use it for dialogs and to pass server props to your builder module) | `{}` |

Core cards of the entry side panel use these orders: Publish 0, Page attributes 20, taxonomies 30, plugin panels 40, Revisions 100. The AI assistant uses 10 and the SEO card 50.

### 1. Describe the item in PHP

```php
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\SlotItem;
use Illuminate\Http\Request;

$this->addAdminSlot(new SlotItem(
    slot: AdminSlot::EntrySidebar,
    key: 'reading-time',
    component: 'reading-time.panel',
    label: 'Reading time',
    capability: Capability::EditOwnEntries,
    order: 45,
    // An array, or a closure that gets the request. Return null to hide the item.
    props: fn (Request $request): ?array => ['wordsPerMinute' => 220],
));
```

On the entry editor, `$request->route('entry')` is the `Entry` being edited (null on the create page).

### 2. Register the component in JavaScript

Create `plugins/<slug>/resources/js/index.tsx`. Vite loads every such file with a glob import, so run `npm run build` after you add or change one.

```tsx
import type {
    EntryEditorContext,
    SlotComponentProps,
    SlotRegistry,
} from '@/components/verso/slots';
import { SectionCard } from '@/components/verso/ui';

function ReadingTimePanel({ props, context }: SlotComponentProps<EntryEditorContext>) {
    const words = JSON.stringify(context.blocks).split(/\s+/).length;
    const minutes = Math.ceil(words / Number(props.wordsPerMinute));

    return (
        <SectionCard title="Reading time" icon="clock">
            <p className="text-sm text-muted-foreground">About {minutes} min.</p>
        </SectionCard>
    );
}

export default function register(registry: SlotRegistry): void {
    registry.registerSlotComponent('reading-time.panel', ReadingTimePanel);
}
```

Use the admin design system: `SectionCard`, `EmptyState`, `StatusBadge` and the other blocks of `@/components/verso/ui`, shadcn components and the Verso icons.

### Entry editor context

`EntryEditorContext` gives `entry`, `contentType`, `title`, `setTitle`, `excerpt`, `setExcerpt`, `blocks`, `setBlocks`, `replaceBlocks` (also remounts the block editor), `meta`, `setMeta(key, value)`, `updateMeta(key, update)` and `activeEditor` (the focused Tiptap editor or null). Values in `meta` are saved with the entry. A component can seed its meta key when the editor opens:

```tsx
registry.registerSlotComponent('reading-time.panel', ReadingTimePanel, {
    initialMeta: (meta) => ({ reading_time: meta.reading_time ?? null }),
});
```

### Table columns and row actions

Optional columns that people can show or hide with the View menu of the list are described in PHP. Give the column, then put its value in `$row['columns'][key]`:

```php
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Plugins\Filters\AdminEntryColumnsFilter;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;

// kind: text, number, date, image, tags, seo, money or status.
// defaultFrom: the list width from which the column shows by default (always, md, lg, xl, 2xl, 3xl, never).
// with and withCount: relations the list loads or counts in one query, so there is no query per row.
$this->addFilter(AdminEntryColumnsFilter::class, fn (array $columns, ContentType $type): array => $type->name !== 'event' ? $columns : [
    ...$columns,
    new ListColumn('venue', 'Venue', 'text', 'lg', with: ['venue']),
]);

$this->addFilter(AdminEntryRowFilter::class, fn (array $row, Entry $entry): array => [
    ...$row,
    'columns' => [...$row['columns'], 'venue' => $entry->venue?->name],
]);
```

The Shop adds Price, Stock and SKU to products this way. Slot columns (below) are for columns that need their own React component.

A column component gets `{ row, contentType }`. Add the data it needs to the row with `AdminEntryRowFilter`.

A row action is either built in JavaScript:

```tsx
registry.registerRowAction('duplicate.action', ({ row }) => ({
    label: 'Duplicate',
    href: `/admin/duplicate/${row.id}`,
    method: 'post',
}));
```

or fully described in PHP, with no JavaScript, when no builder is registered for the component key:

```php
new SlotItem(
    slot: AdminSlot::EntriesRowActions,
    key: 'archive',
    component: 'archive.link',
    label: 'Send to archive',
    props: ['href' => '/admin/archive/{id}', 'method' => 'post', 'destructive' => true],
);
```

## Capabilities and roles

Every permission in Verso is a capability: a short key like `upload_files` or `forms.messages`. All of them live in one registry, `App\Verso\Access\Capabilities\CapabilityRegistry`, which the Roles page, the gate and the client `can` map read.

### Where capabilities come from

| Source | Example keys | How |
|---|---|---|
| Core | `edit_own_entries`, `manage_menus`, `promote_users`, `manage_permalinks` | `Capability` enum and `Access\Capabilities\CoreCapabilities` (label, help text, group, risk) |
| Content types | `entries.post.publish`, `entries.product.delete_others` | Made for every registered type: `create`, `edit_own`, `edit_others`, `publish`, `delete_own`, `delete_others`, `read_private` |
| Plugins | `forms.messages`, `newsletter.manage` | `capabilities` in `verso.json`, registered when the plugin is on |
| Themes | `ashgrove.edit_banner` | `capabilities` in `theme.json`, registered while the theme is active |

Each capability has a `label`, a `description`, a `group` (Content, Design, Tools, Administration, Settings, or the plugin name), an optional `section` (a sub heading like "Users" or "Pages"), a `risk` (`low`, `medium`, `high`, `critical`) and `implies` (keys it also gives). Old umbrella capabilities stay and imply the new fine grained ones, so nothing breaks: `manage_users` gives `list_users`, `create_users`, `edit_users`, `promote_users` and `delete_users`; `manage_settings` gives the settings areas; `manage_plugins` gives `install_plugins` and `activate_plugins`; `manage_themes` gives `edit_site` and `manage_menus`.

For entries, the capability for every content type (`edit_any_entry`) or the one for a single type (`entries.page.edit_others`) is enough. `EntryPolicy` checks both, then runs `EntryCapabilityFilter`.

### Declare capabilities and roles in a plugin

```json
"capabilities": [
    {
        "key": "harbor-bookings.manage",
        "label": "Manage bookings",
        "description": "Accept, move and cancel table bookings.",
        "risk": "medium",
        "section": "Bookings",
        "roles": ["editor"],
        "implies": ["harbor-bookings.view"]
    },
    { "key": "harbor-bookings.view", "label": "See bookings", "risk": "low" }
],
"roles": [
    {
        "slug": "front_desk",
        "name": "Front desk",
        "description": "Takes bookings at the door.",
        "capabilities": ["harbor-bookings.manage", "upload_files"]
    }
]
```

Rules:

- A plugin capability key must start with the plugin slug and a dot (`harbor-bookings.`). Core keys and the `entries.*` pattern cannot be declared. Limits: 50 capabilities and 10 roles per manifest.
- `group` defaults to the plugin name. `risk` defaults to `medium`.
- `roles` lists built-in roles (`admin`, `editor`, `author`, `contributor`, `subscriber`, `shop_manager`) that get the capability by default. Administrators always have every capability.
- Plugin roles are like built-in roles: admins can change, clone and reset them, but not delete them. Their slug cannot be a built-in role slug.
- An `admin.items.*.capability` may name a core capability, one of your own, or one another plugin declared.
- `permissions` stays what it was: the core capabilities your code uses (checked by the plugin verification).
- A theme declares the same two parts in `theme.json`. Its keys should start with the theme slug.

Check them in your code like any capability:

```php
Route::middleware('can:harbor-bookings.manage')->group(...);
$user->hasCapability('harbor-bookings.view');
new SlotItem(slot: AdminSlot::DashboardWidgets, key: 'bookings-today', component: 'bookings.today', capability: 'harbor-bookings.view');
```

`NavItem`, `DashboardStat`, `DashboardPanel`, `DashboardNotice` and `SlotItem` take a `Capability`, a capability key, any gate ability (with `abilityArguments` on `NavItem`), or a closure `fn (User $user): bool`.

In React, read the shared `can` map with `useCan()`: `const can = useCan(); can('harbor-bookings.manage')`. It only hides buttons; the server checks again.

### Every admin route names a capability

Admin routes must have a `can:` middleware (a capability, a gate ability like `AccessAbilities::INSTALL_EXTENSIONS`, or a policy ability like `can:viewAny,App\Verso\Content\Models\Entry,contentType`). `tests/Feature/Access/AdminRouteCapabilitiesTest.php` loops over every admin route, also the bundled plugin routes, and fails for a route without one. Only the dashboard, list view choices and leaving a role preview are on the allow list.

### Roles, users and safety

- Roles: built-in (`Role` enum), plugin roles and custom roles. `App\Verso\Access\Roles\Roles` merges the roles from code with the changes admins saved (`roles` table: a built-in role keeps only its added and removed capabilities, so new plugin defaults still arrive).
- A user has a main role (`users.role`), extra roles (`role_user`) and single grants or denies (`user_capabilities`). `UserPermissions` joins the roles, adds grants, removes denies (and what they imply). Administrators always have everything; denies do not apply to them.
- Nobody can give a capability or role they do not have (`EscalationGuard`). People may lower their own access but never raise it; taking away your own deny counts as raising it. The last administrator can never lose the role (`AdminGuard`, also for extra roles).
- Changes are written to the audit log (`system_config_changes`, actions `role.*` and `user.*`) and shown on the role and user access pages.
- Events: `Access\Events\RoleCreated` and `RoleDeleted`. The editor tools copy the tools of the source role to a cloned role.

## SEO checks and editor analysis

The SEO overview (Settings > Permalinks and SEO > SEO overview) runs every check in `App\Verso\Seo\Audit\SeoCheckRegistry`. Add your own in `boot()`:

```php
use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Checks\BaseCheck;
use App\Verso\Seo\Audit\SeoCheckRegistry;
use App\Verso\Seo\Audit\Severity;

$this->app->make(SeoCheckRegistry::class)->add(new class extends BaseCheck
{
    public function key(): string { return 'harbor-bookings-hours'; }
    public function label(): string { return 'Opening hours are missing'; }
    public function description(): string { return 'Visitors and search engines look for your opening hours.'; }
    public function severity(): Severity { return Severity::Notice; }

    public function run(AuditContext $context): CheckResult
    {
        // $context->entries() gives the published entries once for every check (EntrySnapshot).
        return CheckResult::passed();
    }
});
```

- `CheckResult::of($items, $total, $context->maxItems)` for lists (`IssueItem` with a title, a detail and an edit link), `failed('...')` for a site wide problem, `pending('...')` when data is missing (left out of the score).
- `fix()` returns an `IssueFix::link()` to the screen that fixes it. Implement `FixableCheck` (`fixCapability()`, `applyFix()`) for a safe one click fix.
- The score: each check has the weight of its severity (critical 5, warning 3, notice 1) and loses the share of items that have the issue. A check that throws is shown as pending and logged.

The editor SEO card runs its checks in the browser (`resources/js/lib/seo-analysis`). Add one from `plugins/<slug>/resources/js/index.tsx`:

```tsx
import { registerAnalysisCheck } from '@/lib/seo-analysis';

registerAnalysisCheck({
    id: 'harbor-bookings-phone',
    group: 'seo',
    label: 'Phone number',
    run: ({ content }) =>
        /\+?\d[\d\s]{7,}/.test(content.text)
            ? { status: 'good', message: 'The page shows a phone number.' }
            : { status: 'ok', message: 'Add a phone number so people can call you.' },
});
```

A check returns `null` when it does not apply, may set `needsKeyphrase`, `languages` (`en`, `es`) and `weight`, and gets the parsed content (text, sentences, paragraphs, headings, links, images) and a keyphrase matcher that ignores case, accents and simple plurals. A check with the id of a core check replaces it.

## Shortcodes and embeds

A plugin block with fields (`HasBlockFields`) is also a shortcode with the same name, like `[form slug="contact"]`, and a Blade embed, like `<x-verso-embed name="form" :attrs="['slug' => 'contact']" />`. Both use the block fields for validation and the block `render()` for the HTML. Implement `EmbeddableBlock` to turn the shortcode off, give attr aliases (`['slug' => 'form']`), the key attr for the usage index, and a capability.

When your item changes, clear the pages that embed it:

```php
app(EmbedInvalidator::class)->changed('form', $oldSlug, $form->slug);
```

Themes can run shortcodes in plain text settings with `@shortcodes($themeSettings['footer_text'], 'footer')`.

## Site Editor blocks and menu locations

- Site blocks (logo, menu, search, footer links...) implement the plugin block contracts and are listed in `App\Verso\Site\Blocks\SiteBlockRegistry`. A plugin adds one for the Site Editor with `app(SiteBlockRegistry::class)->register($block)` plus `app(BlockRegistry::class)->register(new PluginBlockAdapter($block))` so it can be saved and rendered.
- Every block can carry `attrs.design` (responsive style options, see ARCHITECTURE.md) and, for admins, `attrs.customCss`. Block renderers do not handle them; `BlockRenderer` adds the scoped class.
- A plugin adds a menu location with `app(MenuLocations::class)->register('shop', 'Shop menu')`.

## Builder registries (contract version 1)

Everything in the block editor comes from registries. Core fills them with the same calls as plugins and themes. The contract version is `App\Verso\Editor\Builder\Registry::CONTRACT_VERSION` in PHP and `builder.version` (`BUILDER_API_VERSION`) in JavaScript. It changes only with a breaking change: a removed registry, a removed or renamed item field, or a changed meaning. New optional fields do not change it. Check the version when your plugin depends on a newer field.

The full example is `plugins/builder-example`: a custom block with its own category, a style property, an inspector panel and a drop rule, on the server and in the editor.

### Server registries

Resolve them with `$this->app->make(...)` in your plugin service provider `boot()`.

| Registry | Item | Contract |
| --- | --- | --- |
| `Editor\BlockRegistry` | `Contracts\Block` (core) or `registerBlock(PluginBlock)` (plugins) | Block types: attrs rules, allowed children, capability, renderer. Plugin blocks may add `HasBlockFields`, `HasChildBlocks`, `HasBlockCategory` (group in the Add panel) and `Editor\Contracts\RequiresFeature` (only roles with an editor feature may add the block). |
| `Builder\BlockCategoryRegistry` | `BlockCategory(key, label, order)` | Groups of the Add panel, sorted by `order` then label. Core: text 10, media 20, layout 30, site 40, advanced 50, plugins 60. |
| `Builder\StylePropertyRegistry` | `StyleProperty(path, cssProperty, kind, label, group, control, value)` | Options of the Styles panel. `path` is where the value lives in `attrs.design` (one to three camelCase words). `kind` is a core value kind (`length`, `angle`, `time`, `color`, `overlay`, `number`, `weight`, `columns`, `sticky`, `hidden`, `token` or a keyword list like `justify`). Size kinds (`length`, `angle`, `time`) carry a value rule, `value` (see [CSS sizes and units](#css-sizes-and-units)); without one it comes from the CSS property. The style schema validates and the compiler renders new properties, responsive per screen size, so they work on the site at once. |
| `Builder\DropRuleRegistry` | `register(key, DropRule)`; `DropRule::allows(parent, child): ?bool` | Which block may go inside which. The last rule with an answer wins; with no answer the parent block `acceptsChild()` decides. Used when saving (`ValidBlocks`) and rendering. |
| `Layouts\Conditions\ConditionRegistry` | `LayoutCondition` (`key`, `label`, `weight`, `options`, `matches`) | Where theme builder layouts show. See "Layout conditions" below. |
| `Editor\Assets\BlockAssetRegistry` | `register(key, css, js, blocks, json)` | CSS and JavaScript loaded only on pages that show the blocks. |
| `Editor\Styles\StylePresets` | `StylePreset(key, label, design)` | Style presets every block can use. |
| `Builder\EditorFeatureRegistry` | `EditorFeature(key, label, description, roles)` | Tools an admin turns on or off per role and content type on Settings, Editor settings. Core: `add_panel`, `list_view`, `styles_panel`, `element_block`, `code_editor`, `custom_css`, `custom_js`. |

`Builder\EditorAccess` answers `enabled($feature, $user, $type)` and `allowedBlocks($type)` (the saved list per content type, limited by the theme). `Builder\EditorManifest::for($user, $type)` is sent as the `editor` prop of every editor page: categories, style properties, breakpoints, features, allowed blocks, theme limits, Add panel and undo settings, element tags and `modules` (folders whose client modules may run).

The element block uses one policy, `Editor\Support\ElementPolicy`, for its tags and attributes. Scripts, iframes, styles, `on*` handlers and unsafe URLs are never allowed there; they stay in the raw HTML block and its capability.

### Client registries

Export a default function from `plugins/<slug>/resources/js/builder.ts` (or `.tsx`), or `themes/<slug>/resources/js/builder.ts`. The file is found when the assets are built. In the editor it runs after core, only when the plugin is enabled or the theme is active, and an error in it is reported and skipped.

```tsx
import type { BuilderApi } from '@/builder';

export default function register(api: BuilderApi) {
    api.categories.register({ name: 'bookings', label: 'Bookings', order: 25 });
    api.dropRules.register({
        name: 'bookings/no-forms-in-columns',
        allows: (parent, child) => (parent === 'column' && child === 'booking-form' ? false : null),
    });
}
```

Every registry has `register(item)` (returns a function that removes that item), `unregister(key)`, `get(key)`, `has(key)`, `all()` (sorted by `order`, then the order they were added), `clear()` and `subscribe(listener)`. Registering the same key again replaces the item in its place. Item types are in `resources/js/builder/types.ts`.

| Registry | Key | Item fields |
| --- | --- | --- |
| `blocks` | `type` | `label`, `description`, `icon`, `keywords`, `create()`, `category`, `order`, `primary` (toolbar button), `edit` (fields component), `textEdit` (rich text), `acceptsChildren`, `childTypes`, `parents`, `hidden`, `feature`. Server plugin blocks are added for you; a module may register a richer client block with the same type first. |
| `categories` | `name` | `label`, `order`. The server list replaces the labels when the editor loads. |
| `styleProperties` | `path` | `kind`, `label`, `group`, `control` (`length`, `color`, `number`, `select`, `toggle`, `token`), `options`, `value` (the value rule of size kinds). Filled from the server; register the PHP property to make it work. |
| `inspectorPanels` | `name` | `title`, `order`, `feature`, `appliesTo(block)`, `component({ block, onChange, device, limits })`. Shown in the Block tab; `device` is the screen size picked there. Core: `core/styles` (order 20). |
| `toolbarButtons` | `name` | `label`, `icon`, `order`, `feature`, `run({ blocks, selectedId, setBlocks })`. |
| `insertSources` | `name` | `label`, `order`, `feature`, `items({ blocks, patterns })` returning `{ key, label, description, icon, category, keywords, create(), load? }`. `load()` returns a promise of blocks built on the server. Each source is a tab of the Add panel. Core: `core/blocks`, `core/sections`, `core/library`, `core/patterns`. |
| `dropRules` | `name` | `order`, `allows(parent, child)`: `true`, `false` or `null` for no answer. The page itself is the parent `root`. Rules with a higher order are asked first; with no answer the drop is refused. Keep them the same as the server rules. |
| `codeSerializers` | `name` (block type) | `serialize(block)` gives the inner code; `parse(inner, attrs)` gives the block back. Blocks without one use their content as it is. Core: heading, list, quote, code. |
| `shortcuts` | `name` | `keys` like `Mod+Shift+D` (`Mod` is Ctrl, or Cmd on a Mac), `label`, `feature`, `inFields`, `run({ blocks, selectedId, setBlocks, move, undo, redo })`. Core: undo and redo, Alt+Shift+arrows to move the selected block. |
| `breakpoints` | `name` | `label`, `maxWidth`, `order`. Filled from `verso.editor.breakpoints`. |
| `pageEditorButtons` | `name` | Top bar of the Easy mode page editor: `label`, `icon`, `order`, `run(context)`. |
| `pageSectionActions` | `name` | Toolbar of every section in the page editor, after Move, Duplicate, Hide and Delete: `label`, `order`, `shows(section)` (false leaves it out), `run({ ...context, section })`. |

### Code view format

The Code view writes each block between markers: `<!-- verso:heading#b_k2j3 {"level":2} -->` ... `<!-- /verso:heading -->`, or `<!-- verso:separator#b_x1 /-->` for a block with no inner code. The id after `#` and the JSON attrs are optional. Code outside markers (pasted HTML or WordPress block markup) is turned into blocks by the importer converter (`POST admin/editor/convert`).

### Theme limits

Themes can limit the editor in `theme.json`, so people stay inside the theme design:

```json
"editor": { "blocks": ["paragraph", "heading", "image"], "colors": ["#1f6f5c", "#f4efe6"], "fonts": ["body", "heading"], "spacing": ["0.5rem", "1rem", "clamp(1rem, 3vw, 2rem)"], "units": ["px", "rem", "%", "vw"] }
```

`blocks` limits what every content type may hold (saving also checks it). `colors` turns the color controls into swatches, `fonts` and `spacing` fill the suggestions of the Styles panel. `units` limits the unit picker of every length input (Styles panel, Site Editor sizes); custom values may then only use those units. It is an editor limit only: saved values in other units stay valid, so a theme switch never breaks old content.

### CSS sizes and units

Every place that edits a size (Styles panel, Site Editor sizes, theme customizer size and length fields, block fields of type `length`) uses one control and one check, so plugins get all CSS units for free.

**The check.** `App\Verso\Core\Css\CssValueParser::normalize($value, $rule)` (PHP) and `normalizeCssValue(value, rule)` from `@/lib/css-value` (TypeScript) give the clean CSS value or null. They accept:

- a number with a unit: every length unit (absolute `px cm mm Q in pc pt`, text relative `em rem ex rex cap rcap ch rch ic ric lh rlh`, screen `vw vh vi vb vmin vmax` and their `sv*`, `lv*`, `dv*` forms, container `cqw cqh cqi cqb cqmin cqmax`, and `%`), or angle (`deg rad grad turn`) or time (`s ms`) units for those types. Units are matched without case and written the CSS way (`1q` becomes `1Q`);
- the keywords the rule allows (`auto`, `fit-content`, `min-content`, `max-content`, `none`, `normal`);
- plain numbers when the rule is `unitless` (line height), `0` on any length, and old plain numbers on a length as pixels (`24` becomes `24px`);
- custom values: one `calc()`, `clamp()`, `min()`, `max()` or `var(--name[, fallback])` call made only of numbers, units, `+ - * /`, brackets and commas, at most 200 characters and 6 levels deep. `url()`, other functions, words, quotes, `;`, `{`, `}` and comments are refused, so the value is safe inside the scoped `vs-<hash>` rule. The output is written in one form (`clamp(1rem,2vw+1rem,3rem)` becomes `clamp(1rem, 2vw + 1rem, 3rem)`).

`tests/Fixtures/css-values.json` holds the unit lists and the cases both sides must pass; add a case there when you change the parser.

**The rule.** `CssValueRule(type, units, keywords, unitless, negative, custom)` says what one property takes. `type` is a `CssValueType` (`Length`, `Angle`, `Time`, `Number`); `units` null means every unit of the type. `CssValueRule::forProperty('max-width')` gives the rule core uses for a CSS property (for example `max-width` takes `none`, `margin-*` takes `auto` and negatives, `padding-*` refuses negatives, `line-height` takes plain numbers and `normal`). `toArray()` is what the editor receives.

**Declaring units for a style property.** The kind picks the type; pass `value` to change the units or keywords:

```php
use App\Verso\Core\Css\CssValueRule;

$styles->register(new StyleProperty(
    path: 'effects.outlineOffset',
    cssProperty: 'outline-offset',
    kind: 'length',
    label: 'Outline offset',
    group: 'effects',
    control: 'length',
    value: new CssValueRule(units: ['px', 'rem', 'em'], keywords: [], negative: true),
));

$styles->register(new StyleProperty('effects.rotate', 'rotate', 'angle', 'Rotate', 'effects', 'length'));
```

A unitless only property (like a `z-index` or `flex-grow`) uses `kind: 'length'` with `value: new CssValueRule(CssValueType::Number)`.

**Block fields.** `new BlockField('gap', 'Gap', BlockFieldType::Length, '1rem', value: new CssValueRule(negative: false))` validates with the parser and shows the length input. `$field->css($attrs['gap'] ?? null)` gives the clean value to print.

**The control.** `LengthInput` from `@/components/verso/ui` is a text field with a unit picker:

```tsx
import { LengthInput } from '@/components/verso/ui';

<Label htmlFor="card-gap">Card gap</Label>
<LengthInput
    id="card-gap"
    label="Card gap"
    value={gap}
    onChange={(next) => setGap(next)}
    rule={{ keywords: ['normal'], negative: false }}
    units={limits.units}
    suggestions={['1rem', '2rem']}
/>
```

- `value` is the stored value (a CSS string or an old number); `onChange` gets the clean CSS string, or `undefined` when the field is emptied. Only allowed values are sent.
- `rule` is a full or partial value rule (every length unit by default). `units` applies a theme limit. `tokens` also accepts design tokens like `var:spacing.md`. `size="sm"` fits inspector panels.
- The unit picker is a searchable list: common units first (`px rem em % vw vh`), then the other groups, the keywords of the rule, "No unit" for unitless rules and "Custom" for a full expression. Units can be found by plain name ("dynamic screen height").
- Changing the unit keeps the number. Typing a full value like `3vmin` or `auto` switches the picker when the field is left. ArrowUp and ArrowDown step by a size that fits the unit (1 for px, %, deg and ms; 0.1 for rem, em and s; 0.05 for turn), Shift makes ten steps, Escape goes back to the saved value.
- Errors show only after the field is left, with `aria-invalid` and a message linked by `aria-describedby`.

### Settings and custom code

Default roles of each tool come from `config/verso.php` (`builder.features`, env `VERSO_BUILDER_*_ROLES`), until an admin saves the Editor settings page. Custom CSS and JavaScript (site wide on Settings, Custom code, and per page in the editor) need the `custom_css` or `custom_js` feature and a capability (`verso.editor.custom_css_capability`, `verso.builder.custom_code.js_capability`). CSS is cleaned by `CustomCode\StylesheetSanitizer`; JavaScript is served as its own file (`/verso-code/site.js`, `/verso-code/entry-{id}.js`) and never printed inline.

## Builder blocks, theme builder and library (contract version 1)

### A builder block

Extend `App\Verso\Blocks\Support\ModuleBlock` (it implements the plugin block contracts) and add it with `BlocksServiceProvider::add($block)` in your provider `boot()`:

```php
use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// Opening hours of the bakery, with an icon.
class OpeningHoursBlock extends ModuleBlock implements CacheableBlock
{
    public function name(): string { return 'harbor-hours'; }
    public function label(): string { return __('harbor::Opening hours'); }
    public function description(): string { return __('harbor::When the shop is open.'); }
    public function assets(): array { return ['harbor-hours']; }

    public function fields(): array
    {
        return [
            new BlockField('icon', __('harbor::Icon'), BlockFieldType::Icon, 'clock'),
            new BlockField('hours', __('harbor::Hours'), BlockFieldType::Textarea, ''),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        return '<div class="harbor-hours">'.$this->icon($this->text($block, 'icon', 'clock')).e($this->text($block, 'hours')).'</div>';
    }
}
```

- `assets()` names folders of `resources/blocks` (core) or, for plugins, call `app(BlockAssetRegistry::class)->register('harbor-hours', css: __DIR__.'/../assets/hours.css', js: null, blocks: ['harbor-hours'])`. The files load only on pages that show the block: stylesheets before it, `<script type="module">` after it. Never print `style` attributes; set values from classes or with `element.style.setProperty()` in the script.
- Optional contracts (`App\Verso\Plugins\Contracts`): `HasChildBlocks` (takes children), `RestrictsChildBlocks` (only these child types, the editor offers only them), `HasParentBlocks` (may only sit inside these types, like a tab inside tabs), `HasStarterBlocks` (children it starts with), `RendersWithRenderer` (draws with the block renderer; `ModuleBlock` has it), `CacheableBlock` (its HTML depends only on its attrs and children, so pages keep it in the block cache). The server drop rule `core/parents` and the client drop rule of the same name read the same lists.
- Field types: `text`, `textarea`, `number`, `length`, `url`, `toggle`, `select`, `media` (a picture, stored as `{id, url, alt, width, height}`), `icon` (a name of `IconLibrary`), `color` (hex or `var:color.*`) and `datetime` (`Y-m-d\TH:i` in the site time zone). The editor draws a field for each.
- Dynamic blocks read the page from `App\Verso\Editor\Support\RenderContext` (`entry()`, `page()`, `subject()`, `data('entries')`); extend `Blocks\Dynamic\DynamicBlock` and implement `forEntry()`.
- Icons: `app(IconLibrary::class)->register('harbor-boat', '<path d="..."/>')` (plain SVG shapes only).

### Style presets

`app(StylePresets::class)->register(new StylePreset(key: 'harbor-card', label: 'Harbor card', design: [...]))`. Blocks point to a preset with `attrs.preset`; their own styles go on top. A theme adds presets in theme.json (`editor.presets`, see THEMES.md). People with `edit_styles` save, change and delete presets from the Styles panel.

### Layout conditions

The theme builder asks `App\Verso\Layouts\Conditions\ConditionRegistry` where each layout shows. Add a rule:

```php
$conditions->register(CoreConditions::make(
    key: 'harbor_member',
    label: 'Members of the harbor club',
    weight: 40,
    options: null,
    matches: fn (PageContext $page, string $value): bool => $page->viewer?->hasCapability('harbor.member') ?? false,
));
```

`weight` says how exact the rule is (core: everywhere 0, visitor 5, kind of page 10, role 20, content type 30, author 50, term 60, one entry 100). `options` returns `[{value, label}]` for a select, or null for rules without a value.

### Template overrides and the editor manifest

- `app(TemplateOverrides::class)->page($key, fn (TemplateCandidates $candidates, array $data): ?string => ...)` draws a whole page instead of the theme view; `partOverride($key, fn (string $name, array $data, Closure $theme): ?string => ...)` draws a part (or wraps the theme part, `$theme()`). The theme builder uses both.
- `EditorManifest::extend(fn (?User $user, ?string $type): array => [...])` adds keys to the `editor` prop of every editor (Easy mode adds `sections`, the library adds `library`).

### Synced blocks

The `synced` block (`attrs.ref` = library item id) draws a library item. It is an embeddable block with key attr `ref`, so the usage index knows where each item is used; `LibraryChanged` empties the cached pages that use it.

## Easy mode registries

Easy mode (module `App\Verso\Easy`) helps people with no technical knowledge build a site. Everything it lists is a registry: core registers its own items with the same calls a plugin or theme uses, in its provider `boot()`.

Labels, descriptions, help steps and keywords stay in English when you register them. Every registry takes an optional plugin slug (`register($item, 'harbor-tours')`, which `registerSection()`, `registerStarterSite()` and the other `PluginServiceProvider` helpers pass for you) and translates them with that prefix first when it sends them to the browser. Use named arguments (`label:`, `description:`, `title:`, `summary:`, `steps:`, `keywords:`) so `verso:lang-extract` finds them. Sample content of starter sites and sections is site content in the site language: it is not translated, and it sits between `// i18n-ignore-start` and `// i18n-ignore-end` lines so it stays out of the admin catalogs.

### Ready sections (`Easy\Sections\SectionLibrary`)

A section is a group of core blocks that looks good with any theme: a welcome banner, services, a gallery, prices, a contact area. Starter pages are made of sections, and the "Add section" gallery of the visual editor lists them.

- `register(SectionTemplate)`, `has($key)`, `get($key)` (throws for unknown keys), `find($key)`, `all()`, `available()` (only sections whose plugin is on), `isAvailable($section)`, `build($key, $content, ?SectionKit)` and `toArray()` (the available sections for the gallery: `key`, `label`, `description`, `icon`, `category`, `keywords`, `plugin`, `defaults`).
- `SectionTemplate(key, label, description, builder, icon, category, keywords, plugin, defaults)`. `key` is `a-z`, `0-9` and `-`. `builder` is `fn (SectionContent $content, SectionKit $kit): array` and returns a list of blocks. `plugin` is the slug of a plugin the section needs (null for none). `defaults` is a `SectionContent` used for every empty part.
- `SectionContent(heading, text, items, images, button, extra)`: `text` is plain text (empty lines start a new paragraph), `items` are rows like `['title' => ..., 'text' => ..., 'meta' => ..., 'price' => ..., 'features' => [...], 'image' => [...], 'highlight' => true]`, `images` are image attrs (`mediaId`, `src`, `alt`, `width`, `height`; `src` is needed), `button` is `['label' => ..., 'url' => ...]`, `extra` holds values only one section reads (`address`, `phone`, `email`, `hours`, `latitude`, `longitude`, `zoom`, `label`). `SectionContent::from(array)` reads a plain array.
- `SectionKit` makes blocks with fresh ids that never repeat inside one kit, so build every section of one page with the same kit: `section()`, `grid()`, `card()`, `heading()`, `paragraph()`, `paragraphs()`, `list()`, `button()`, `image()`, `gallery()`, `columns()` and `block($type, $attrs, $content, $children)`. `hasPlugin($slug)` and `accepts($type, $attrs)` (the block exists and the attrs pass its rules) let a section use plugin blocks only when they work. Style sections with the theme tokens in `SectionKit` (`var:spacing.x-large`, `var:spacing.large`, `var:spacing.medium`, `var:spacing.small`, `var:radius.base`, `var:color.accent`, `var:color.surface`, `var:color.background`), never with fixed colors, so they follow the site style. A theme should define these tokens in `theme.json` `styles`.
- Core sections: `hero`, `about`, `services`, `gallery`, `testimonials`, `pricing`, `team`, `faq`, `contact` (adds the Forms `form` block when Forms is on and the form `verso.easy.contact_form` exists, otherwise only the details), `map` and `cta`. The Shop plugin adds `shop-products` and the Newsletter plugin adds `newsletter`.

```php
use App\Verso\Easy\Sections\SectionContent;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionTemplate;

// In a plugin provider boot(). PluginServiceProvider has the helper; themes call app(SectionLibrary::class)->register().
$this->registerSection(new SectionTemplate(
    key: 'opening-hours',
    label: 'Opening hours',
    description: 'The days and hours you are open.',
    builder: fn (SectionContent $content, SectionKit $kit): array => [$kit->section([
        $kit->heading($content->heading),
        $kit->list(array_map(fn (array $row): string => (string) $row['title'], $content->items)),
    ], label: $content->heading)],
    icon: 'clock',
    category: 'contact',
    keywords: ['hours', 'horario'],
    plugin: 'bookings',
    defaults: new SectionContent(heading: 'Opening hours', items: [['title' => 'Monday to Friday, 8:00 to 18:00']]),
));

// Build a section from PHP: missing content takes the defaults.
$blocks = app(SectionLibrary::class)->build('services', ['heading' => 'What we do', 'items' => [['title' => 'Repairs', 'text' => 'Same day.']]]);
```

The `map` block (`Easy\Blocks\MapBlock`, fields `latitude`, `longitude`, `zoom`, `label`) is registered like a plugin block, so the entry editor shows it. It prints an OpenStreetMap frame (`verso.easy.map.embed_url`, height `verso.easy.map.height`) and a plain link (`verso.easy.map.link_url`). It is not a shortcode.

### Starter sites (`Easy\Starters\StarterSiteRegistry`)

The guided start ("What is your site for?", `admin.easy.start`) lists the starter sites. Picking one runs `Easy\Actions\InstallStarterSite`.

- `register(StarterSite)`, `has($key)`, `get($key)`, `find($key)`, `all()` (sorted by `order`, then label), `keys()` and `toArray()`.
- `StarterSite(key, label, description, icon, pages, primaryMenu, footerMenu, settings, plugins, stylePreset, images, order)`. `pages` is a list of `StarterPage(title, slug, sections, front, type, excerpt)` with `StarterSection(key, content)` items (the content of `SectionContent::from()`; `images` and item `image` name a picture). One page with `front: true` becomes the home page; without one the latest posts stay there. `type: 'post'` makes a post. Menu items are `StarterMenuItem::page($slug, $label)`, `::archive($type, $label)`, `::route($name, $label)` (skipped when the route is missing) and `::link($label, $url)`. `settings` go through `UpdateSiteSettings` (known keys only, like `site.tagline`). `plugins` are turned on when they are installed and skipped otherwise. `stylePreset` names a style preset (`calm`, `bold`, `playful`, `elegant`). `images` are `StarterImage(name, alt, style, palette)` painted with `DemoImagePainter` and saved as media under `verso.easy.media_folder`.
- Core starters: `blog`, `business`, `portfolio`, `restaurant`, `shop`, `event`, `personal`.
- `InstallStarterSite::handle($user, $key)` returns a `StarterInstallReport` (`created`, `updated`, `skipped`, `pluginsEnabled`, `pluginsMissing`, `frontPageId`, `message()`). Pages are published with `CreateEntry`, marked with the `easy_starter` meta key and use the first wide page template of the theme (`verso.easy.wide_templates`). A page with the same slug that a starter made is updated with `UpdateEntry`; a page the site made itself is kept and reported as skipped. Menus are named "<label> main menu" and "<label> footer menu", reused on a second run, and take over the `primary` and `footer` locations. Plugins turned on now add their demo content (`SeedsDemoContent`), which gives Forms its contact form. The settings `easy.starter` and `easy.started_at` record the choice.

```php
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

$this->registerStarterSite(new StarterSite(
    key: 'yoga-studio',
    label: 'Yoga studio',
    description: 'Classes, teachers and a timetable.',
    icon: 'calendar',
    order: 45,
    plugins: ['bookings'],
    settings: ['site.tagline' => 'Slow classes in the old town'],
    pages: [
        new StarterPage('Home', 'home', [new StarterSection('hero', ['heading' => 'Breathe in, slow down'])], front: true),
        new StarterPage('Timetable', 'timetable', [new StarterSection('opening-hours')]),
    ],
    primaryMenu: [StarterMenuItem::page('home'), StarterMenuItem::page('timetable')],
));
```

### Style presets (`Easy\Styles\StylePresetRegistry`)

"Make it yours" (`admin.easy.style`) lists style presets as cards. A preset is a set of global style tokens (see "styles" in docs/THEMES.md), like corners and spacing, plus the font pair picked with it. A theme only gets the tokens it declares and accepts (a token linked to a customizer field also has to pass that field, like the font options or the size range).

- `register(StylePreset)`, `has($key)`, `get($key)` (throws for unknown keys), `find($key)`, `all()` (sorted by `order`, then label), `keys()` and the static `sorted($presets)`.
- `StylePreset(key, label, description, tokens, fontPair, order)`. `tokens` is token id => value, like `['radius.base' => '12px', 'spacing.large' => '2rem']`. `fontPair` is the key of a font pair, or null.
- Core presets: `calm`, `bold`, `playful`, `elegant` (tokens `radius.base`, `spacing.small`, `spacing.medium`, `spacing.large`, `spacing.x-large`). A starter site names one in `stylePreset`; the page picks it until the person saves another one (`easy.style_preset`).

### Font pairs (`Easy\Styles\FontPairRegistry`)

- `register(FontPair)`, `has($key)`, `get($key)`, `find($key)`, `all()`, `keys()` and `sorted($pairs)`.
- `FontPair(key, label, heading, body, sample, order)`. `heading` and `body` are keys of the safe font list `config('verso.themes.fonts')`. `sample` is the sentence the card shows in the pair's fonts.
- On a theme, the heading font goes to `font.heading` (or `font.ui` when the theme has no heading token) and the body font to `font.body`. A pair shows only when the theme accepts both fonts.
- Core pairs: `classic`, `modern`, `friendly`, `editorial`, `studio`, `modern-serif`, `warm-contrast`.

```php
use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\StylePreset;

// In a plugin provider boot(). Themes call app(StylePresetRegistry::class)->register() or use theme.json.
$this->registerStylePreset(new StylePreset(
    key: 'retro',
    label: 'Retro',
    description: 'Round corners and wide gaps, like an old diner menu.',
    tokens: ['radius.base' => '20px', 'spacing.large' => '2.25rem'],
    fontPair: 'diner',
    order: 50,
));
$this->registerFontPair(new FontPair('diner', 'Diner', heading: 'humanist', body: 'georgia', sample: 'Pancakes all day, coffee refills on the house.'));
```

A theme can add or change presets and pairs in `theme.json` without PHP: `"easy": {"presets": {"calm": {"tokens": {"radius.base": "4px"}}, "harbor-night": {"label": "Harbor night", "tokens": {...}}}, "fontPairs": {"captain": {"label": "Captain", "heading": "system", "body": "georgia"}}, "fontFiles": {"newsreader": "fonts/newsreader-roman.woff2"}}`. A theme preset with a core key keeps the missing parts of the core one. `fontFiles` points font keys at files in `assets/`, so the font cards in the admin use the real fonts. `Easy\Styles\ThemeStyleOptions` merges all of this for one theme: `presets($theme)`, `fontPairs($theme)`, `fontPairTokens($theme, $pair)`, `colorRoles($theme)` (the palette roles `accent`, `background`, `text`, `muted`, `surface` the theme declares as `color.<role>`), `fontFaces($theme)` and `fits($theme, $tokens, $id, $value)`.

### Visual page editor

The page editor (`admin.easy.pages.edit`) shows the real page in a frame. Two client registries of the builder API let a plugin add tools to it from its builder module (`resources/js/builder.ts`), like an AI helper:

- `api.pageEditorButtons.register({ name, label, icon, order, run(context) })`: a button in the top bar.
- `api.pageSectionActions.register({ name, label, order, shows(section), run(context) })`: a button in the toolbar of each section; `context.section` is the top level block.

Buttons and actions may be registered or removed while the editor is open; it shows the current list. A button `icon` is any SVG component (a Verso icon or a Lucide icon). `context` (`PageEditorContext` in `resources/js/lib/easy/page-editor-registry.ts`) holds `entry` (`id`, `title`, `type`), the current `blocks`, `change(blocks, message)` (one undo step, the message is read to screen reader users) and `announce(message)`. Keep block ids unique (`withFreshIds()` and `insertSections()` in `resources/js/lib/easy/page-blocks.ts` help) and only use blocks the server accepts: saving goes through `ValidBlocks`.

```ts
import { Sparkles } from 'lucide-react';
import type { BuilderApi } from '@/builder';

export default function (api: BuilderApi) {
    api.pageSectionActions.register({
        name: 'bakery/to-top',
        label: 'Move to the top',
        shows: (section) => section.type === 'section',
        run: ({ blocks, section, change }) => change([section, ...blocks.filter((block) => block.id !== section.id)], 'Section moved to the top'),
    });
    api.pageEditorButtons.register({ name: 'bakery/tips', label: 'Writing tips', icon: Sparkles, run: ({ announce }) => announce('Short sentences work best.') });
}
```

For dialogs and server data, add a `page-editor` admin slot item: its component is mounted in the page editor with the item props. The AI Assistant plugin works this way: the slot component stores the props and renders the open dialog, and its builder module adds the buttons only while the props say the helper is ready.

Sections a plugin registers in `SectionLibrary` show in the "Add section" gallery when their plugin is on; `admin.easy.sections.build` builds them on the server with fresh ids.

Everything `change()` does is one undo step, and the editor keeps it in the automatic draft, so a plugin tool never loses the person's work. If a plugin block throws while it renders, the page still draws: visitors see nothing in its place and the editor shows a short note the person can delete (the error is reported with `report()`). Use `resources/js/lib/easy/plain-errors.ts` (`messageForStatus()`, `plainVisitErrors()`) for the messages of plugin dialogs, so errors read the same everywhere.

### Simple admin mode (`Easy\Simple\SimpleModeNav`)

Simple mode shows a short sidebar with plain names (and the same short list in the Cmd+K search). Pages that are not in it still open by their address. It is on for a person when `users.admin_preferences.simple_mode` says so (`AdminPreferences::simpleMode()`, `setSimpleMode()`), otherwise the site default applies: the setting `easy.simple_mode_default` (saved as `verso.easy.simple_mode.new_sites` by the install and the first starter site), or `verso.easy.simple_mode.default` on sites that never saved one. `Easy\Simple\SimpleMode::isOn($user)` answers it; the admin UI reads the shared prop `simpleMode`.

- `allow(...$keys)`, `rename($key, $label)` (also allows it; the old name stays a search keyword through `NavItem::renamed()`), `disallow($key)`, `allows($key)`, `label($key)`, `keys()` and `filter($items)`. Core runs it as an `AdminNavFilter` with priority 1000, so items plugins add with lower priorities are filtered too.
- Core keys: `dashboard`, `entries.post` ("Blog posts"), `entries.page`, `media` ("Photos and files"), `comments`, `easy-start`, `easy-style`, `site.editor` ("Header and footer"), `site.menus`, `easy-details`, `site.settings` ("Site settings"). A child needs its own key; when its parent is hidden it moves up. The Forms plugin allows `plugin.forms` and `forms.messages`.
- In simple mode the dashboard keeps only stats that open a page of the menu (and panels that do, or have no link), with plain names (`resources/js/lib/easy/simple-dashboard.ts`), and the page list gets an "Edit on the page" row action.

```php
// In a plugin provider boot(): keep the orders page in simple mode.
$this->allowInSimpleMode('bakery.orders');
```

### Launch checklist (`Easy\Launch\LaunchChecklist`)

A card at the top of the dashboard (slot `dashboard-top`) for people who manage the settings, in simple mode or after a starter site, until they hide it (only possible when every step is done; `AdminPreferences::dismiss('launch-checklist')`).

- `register(LaunchCheck)`, `unregister($key)`, `has($key)`, `get($key)`, `find($key)`, `all()` (steps that apply, by `order`, then label), `toArray()` (`items`, `done`, `total`) and `isComplete()`.
- `LaunchCheck(key, label, description, href, done, order, action, method, applies)`. `href` is a string or a closure (closures run late, so `route()` works). `done` is `fn (): bool`. `method` is `get` (a link) or `post` (a button that posts to `href`). `applies` (optional `fn (): bool`) leaves the step out, like the logo on a theme without a logo field. `action` is the button text (default "Do it").
- Core steps (orders 10 to 80): `logo` (the theme customizer logo), `colors` (`easy.styled_at` or a color token that is not the default), `readable-colors` (order 25: the saved palette passes `ContrastChecker`, only on themes with text and background colors), `pages` (a starter site, or `verso.easy.launch.min_pages` published pages), `contact`, `social` and `search` (see "Your site details" below), `privacy` (a published page saved in `easy.privacy_page` or with the slug `verso.easy.privacy.slug`; "Create a privacy page" posts to `admin.easy.launch.privacy`, which runs `Actions\CreatePrivacyPage` and opens the new page in the page editor) and `go-live` (`easy.launched_at` is set and `reading.discourage_search` is off; "Go live" posts to `admin.easy.launch.go-live`, which runs `Actions\GoLive`).

"Your site details" (`admin.easy.details`, menu Settings) saves the contact details in `site.contact.email`, `site.contact.phone`, `site.contact.address` and `site.contact.hours`, the social profiles in `seo.schema_same_as` and the search basics in `site.tagline` and `seo.default_description` (`Actions\SaveSiteDetails`, `Details\SiteDetails`). A new contact section from the "Add section" gallery starts with these contact details, and a `social-links` block without its own addresses shows the social profiles.

```php
$this->registerLaunchCheck(new LaunchCheck(
    key: 'bread-menu',
    label: 'Add your bread menu',
    description: 'List what you bake each day.',
    href: fn (): string => route('admin.bakery.menu'),
    done: fn (): bool => Bread::query()->exists(),
    order: 75,
));
```

### Help panel (`Easy\Help\HelpTopics`)

The "Help" button of the admin top bar opens a side panel with short illustrated steps, a search box and the simple mode switch. Topics load from `GET admin.easy.help` when the panel opens; topics whose `routes` hold the current route name (shared prop `routeName`) come first.

- `register(HelpTopic)`, `unregister($key)`, `has($key)`, `get($key)`, `find($key)`, `all()` (by `order`, then title) and `forUser($user)`.
- `HelpTopic(key, title, summary, steps, illustration, routes, keywords, order, video, capability)`. `steps` are short plain sentences. `illustration` is a drawing key of `resources/js/components/verso/easy/help-illustrations.tsx` (`starter`, `style`, `edit-page`, `add-section`, `menu`, `blog-post`, `photos`, `go-live`; unknown keys get a calm default). `video` is a link to a short video; `VERSO_EASY_HELP_VIDEOS` (`key=url,key=url`) sets or replaces videos by topic key. `capability` hides a topic from people who cannot act on it.
- Core topics: `choose-starter`, `make-it-yours`, `edit-page`, `add-section`, `change-menu`, `write-post`, `upload-photos`, `go-live`.

```php
$this->registerHelpTopic(new HelpTopic(
    key: 'bread-orders',
    title: 'Take bread orders',
    summary: 'Let people order bread for tomorrow morning.',
    steps: ['Open "Orders".', 'Check the orders for tomorrow.', 'Press "Ready" when the bread is packed.'],
    routes: ['admin.bakery.orders.index'],
    order: 65,
));
```

### Contrast checks (`Easy\Styles\ContrastChecker`)

`ratio($a, $b)` (WCAG contrast ratio), `luminance($hex)`, `passes($fg, $bg, $minimum)`, `review($colors)` (warnings for a palette by role: text and quiet text on the background need 4.5, the accent needs 3, text on panels needs 4.5) and `fix($color, $other, $minimum)` (moves a color toward black or white until it passes). `resources/js/lib/easy/contrast.ts` does the same in the browser; `tests/Fixtures/contrast.json` holds the cases both test suites check, so add a case there when you change a rule.

## Translations

The admin is translated into 15 languages (see docs/I18N.md). Plugins and themes ship their own strings.

1. Write English in your code and wrap it, with your slug as prefix:
   - PHP: `__('harbor-weather::Tide table')`, `__('harbor-weather::Updated :time.', ['time' => $time])`, plurals with `\App\Verso\I18n\Translate::icu('harbor-weather::{count, plural, one {# station} other {# stations}}', ['count' => $count])`.
   - React: `import { t, msg } from '@/lib/i18n';` then `t('harbor-weather::Tide table')`, `t('harbor-weather::{count, plural, one {# station} other {# stations}}', { count })`. Common admin words can use the core key: `t('Save')`.
   - Labels you declare in `verso.json` (name, description, `admin` labels and keywords), in `theme.json` (setting labels, help, templates, menu locations, part styles) and in labels you register in PHP (nav items, dashboard stats, System settings, blocks and block fields, content types) stay in English: Verso translates them with your prefix first and the core key second when it shows them.
2. Add `lang/<code>.json` files to your plugin or theme folder (`plugins/harbor-weather/lang/es.json`). Keys have no prefix; Verso adds `<slug>::` when it loads them:

```json
{
    "Tide table": "Tabla de mareas",
    "{count, plural, one {# station} other {# stations}}": "{count, plural, one {# estación} other {# estaciones}}"
}
```

3. `php artisan verso:lang-extract` writes `lang/en.json` of your plugin or theme from the code and manifests and lists missing keys per language (`--missing`).

How it loads:

- The files of enabled plugins and of the active theme (with its parents) are merged into the per language bundle the admin downloads (`/i18n/<code>/<version>.json`). The version changes when a file changes, so browsers always get fresh strings. A disabled plugin adds nothing, except its name and description on the Plugins page, which are read from its own lang file.
- A missing translation falls back to your `en.json`, then to the text without the prefix.
- Public theme views use the same keys (`__('<slug>::Text')`). On public pages the site language applies (`ApplySiteLocale`), not the admin language of the visitor. See docs/I18N.md, "Themes".
- Messages use the ICU format (`{name}`, `plural`, `select`) in React and `:name` placeholders or ICU (`Translate::icu`) in PHP. Keep the same placeholders in every language; `tests/Feature/I18n/LangFilesTest.php` shows how the core checks them.
- Use logical CSS (`margin-inline-start`, Tailwind `ms-`, `ps-`, `start-`, `text-start`) so your screens work in Arabic, which is shown right to left.

## Testing

- PHP: `Filters::add(...)` in a test, then call the page or the filter class. `Tests\Support\SharedSlots::item($response, AdminSlot::DashboardWidgets, 'key')` reads a shared slot item.
- Permissions: `tests/Feature/Access/Helpers.php` has `userWith([...capabilities])`, `customRole()`, `giveExtraRoles()` and `overrideCapabilities()`.
- JavaScript: `registerSlotComponent()` and `setPageProps({ versoSlots: { ... } })`, then render `<Slot name="..." context={...} />`. Call `clearSlotRegistry()` after each test.

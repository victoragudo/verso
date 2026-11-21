# Verso Themes

A theme is a folder in `themes/<slug>/` with a `theme.json` file and Blade views. This guide covers child themes, the template hierarchy, page templates, template parts, block patterns and the template debug panel.

This is the reference. For a step by step guide (no code, a child theme by hand, or a theme from scratch), a complete example theme, the block classes to style, the verification findings and packaging, read [THEME-GUIDE.md](THEME-GUIDE.md).

## Folder layout

```
themes/harbor/
  theme.json              name, version, parent, templates, settings
  assets/theme.css        printed inline by the layout (parents first)
  views/
    layouts/app.blade.php
    home.blade.php  entry.blade.php  archive.blade.php  taxonomy.blade.php  search.blade.php  404.blade.php
    entry-page.blade.php            optional, any name from the hierarchy below
    templates/landing.blade.php     page templates
    parts/header.blade.php          template parts
    parts/footer-minimal.blade.php  a part variant
  patterns/
    call-to-action.json   block patterns
```

Every view is loaded through the `theme::` namespace, like `@extends('theme::layouts.app')`. Never point at a theme folder by path.

## theme.json

```json
{
    "name": "Harbor Dusk",
    "slug": "harbor-dusk",
    "parent": "harbor",
    "version": "1.0.0",
    "description": "Evening colors for Harbor.",
    "author": "Linnea Berg",
    "templates": {
        "landing": {
            "label": "Landing page",
            "description": "No sidebar, a big hero.",
            "view": "templates.landing",
            "types": ["page"]
        }
    },
    "settings": {}
}
```

| Key | Meaning |
| --- | --- |
| `name`, `version` | Required. `version` looks like `1.0.0`. |
| `slug` | Optional. Verso uses the folder name; a different `slug` is reported by the checks. |
| `description`, `author` | Recommended. The checks report them when missing. |
| `requires`, `php` | Verso and PHP version constraints, like `^0.1` and `^8.4`. Recommended. |
| `screenshot` | Path of the screenshot inside the theme. Optional; `screenshot.png`, `.webp`, `.jpg` or `.jpeg` at the top is found without it. |
| `network` | Outside hosts the theme loads scripts, styles or files from, like `["cdn.example.org"]`. Optional. |
| `parent` | Slug of the parent theme. Optional. |
| `templates` | Page templates, keyed by name (see below). Optional. |
| `settings` | Customizer fields (see the theme customizer section in `ARCHITECTURE.md`). Size and length fields take any CSS unit. Optional. |
| `parts`, `styles`, `menus`, `easy` | Part styles, design tokens, extra menu locations and Easy mode extras (see Site Editor below). Optional. |
| `editor` | Limits of the block editor: `blocks`, `colors`, `fonts`, `spacing` (size suggestions) and `units` (the units every length input offers, like `["px", "rem", "%"]`). See `EXTENDING.md`. Optional. |

## Child themes

Set `parent` to make a child theme. The child only ships what it changes.

- **Views**: Verso looks for a view in the child, then in the parent (and its parent, and so on), then in the default theme (`verso.themes.fallback`), then in plugin view folders.
- **CSS**: `assets/theme.css` of every theme in the chain is printed, oldest parent first, so child rules win. When no theme in the chain has a stylesheet, the default theme CSS is used.
- **Other assets**: `ThemeManager::locateFile($theme, 'assets/logo.svg')` finds a file in the child first and then in the parents.
- **Settings**: the customizer schema of the parents is merged in. A child field with the same key replaces the parent field. Values are saved for the child (`theme.<child-slug>.<key>`).
- **Page templates and block patterns**: inherited. A child entry with the same name replaces the parent one.
- **Loops**: a chain that points back to itself stops at the first repeat.
- **Missing parent**: the Themes page shows a warning and the theme cannot be activated. The parent of the active theme cannot be removed.

Create one with:

```bash
php artisan verso:make-theme "Harbor Dusk" --parent=harbor
```

It writes `theme.json` (with `parent`), `assets/theme.css` and a short `README.md`. Copy a view from the parent into the child to change it.

### From the Site Editor

"Create child theme" on the Site Editor page (`POST admin/site-editor/child-theme`, `manage_themes`) writes `themes/<slug>` from the active theme (`Site\Parts\ChildThemeFromChanges`):

- `theme.json` with `parent`, the parent `requires` and `php`, changed customizer values as the new field defaults and changed design tokens as the new `styles` defaults.
- `assets/theme.css` with the same values as CSS variables, because a default alone prints nothing.
- For each published Site Editor part: `parts/<key>.json` (`{"blocks": [...]}`) and `views/parts/<key>.blade.php` with `<x-verso-part-blocks part="header" key="header" />`, which renders those blocks like an edited part. Drafts are left out.
- The parent screenshot and a short `README.md`. The header and footer styles in use are saved as the choices of the new theme.

The theme is checked right away (the report shows on its detail page) and can be made active in the same step. Menus are shared by every theme, so they are not copied.

## Template hierarchy

For each public page Verso builds an ordered list of view names (the candidates) and renders the first one that exists in the theme chain. The lists are built by `App\Verso\Theme\Templates\TemplateHierarchy` and are typed by `TemplateContext`.

| Page | Context | Candidates, in order |
| --- | --- | --- |
| One entry, like `/blog/ferry-routes` | `Entry` | page template view (when chosen), `entry-{type}-{slug}`, `entry-{type}-{id}`, `entry-{type}`, `entry` |
| The chosen front page at `/` | `FrontPage` | page template view (when chosen), `front-page`, then the entry list above |
| Latest posts at `/` | `Home` | `home` |
| A term, like `/category/sailing` | `Taxonomy` | `taxonomy-{taxonomy}-{slug}`, `taxonomy-{taxonomy}`, `taxonomy`, `archive` |
| A content type archive, like `/blog` | `Archive` | `archive-{type}`, `archive` |
| Search results | `Search` | `search` |
| Not found | `NotFound` | `404` |

Examples: `entry-post-ferry-routes.blade.php`, `entry-page-12.blade.php`, `entry-product.blade.php`, `taxonomy-category-sailing.blade.php`, `archive-recipe.blade.php`.

Data given to the views:

- entry views: `$entry`, `$content` (rendered blocks)
- taxonomy views: `$taxonomy`, `$term`, `$entries`
- archive views: `$contentType`, `$entries`
- `home`: `$entries`; `search`: `$search`, `$searching`, `$entries`

A theme that uses `archive` as the last fallback for terms should check `isset($term)`.

### Changing the candidates from a plugin

```php
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\TemplateCandidatesFilter;
use App\Verso\Theme\Templates\TemplateContext;

Filters::add(TemplateCandidatesFilter::class, function (array $candidates, TemplateContext $context, ?object $subject): array {
    if ($context === TemplateContext::Entry && $subject instanceof Entry && ($subject->meta['featured'] ?? false)) {
        array_unshift($candidates, 'entry-featured');
    }

    return $candidates;
});
```

`$subject` is the `Entry`, the `Term`, the `ContentType`, or null for home, search and 404. Names that are not safe view names (letters, digits, `_`, `-`, parts split by `.` or `/`) are dropped after the filter.

## Page templates

A theme lists templates under `templates` in `theme.json`:

| Field | Default | Meaning |
| --- | --- | --- |
| key | | Template name, lowercase letters, digits, `-` and `_`. Saved in `entries.meta.template`. |
| `label` | name as a headline | Shown in the editor select. |
| `description` | empty | Searchable help text. |
| `view` | `templates.<name>` | Theme view to render. |
| `types` | `["page"]` | Content types that can use it. |
| `parts` | none | Part styles the template uses, like `{"header": "minimal"}` (see `parts` under Site Editor). |

The entry editor shows a "Template" select (a searchable select) when the active theme offers templates for the content type. Saving checks that the name is offered for the type; a value already stored is kept even after a theme change. When the saved template is not offered by the active theme, or its view file is missing, the normal hierarchy is used.

Header and footer styles per page: the site wide style (Site Editor), then the template `parts` from theme.json, then the choice saved for that template on Site Editor, Templates, then the choice saved on the entry (`entries.meta.parts`, the "Header style" and "Footer style" selects in the Template card of the editor). The last one wins; styles the active theme does not offer are ignored. `TemplatePartChoices` applies them while `ThemeResponse::entry()` renders.

The default theme ships `full-width` and `landing` (full width with the `minimal` header) for pages. Ashgrove ships `wide` and `landing`.

## Template parts

Parts are views in `views/parts/`. A variant is a file named `<part>-<variant>`. Include one with the `@templatePart` directive:

```blade
@templatePart('header')
@templatePart('footer', 'minimal')   {{-- parts/footer-minimal, else parts/footer --}}
@templatePart('footer', $themeSettings['footer_style'] ?? null)
```

The part gets the data of the view that includes it. A missing part prints nothing. Parts follow the same child, parent, default order, so a child theme can replace only `parts/header.blade.php`. The default theme layout uses `@templatePart('header')` and `@templatePart('footer')`.

## Site Editor

Admins change parts in Site, Site Editor. Theme files are never written; edits live in `site_parts` and win over the file (see "Site Editor and block styles" in ARCHITECTURE.md). A theme can help in four places of `theme.json`:

```json
{
    "menus": { "social": "Social links" },
    "parts": {
        "header": {
            "label": "Header",
            "default": "classic",
            "variants": {
                "classic": { "label": "Classic", "description": "Logo on the left." },
                "centered": { "label": "Centered", "description": "Logo in the middle." }
            }
        }
    },
    "styles": {
        "color": { "accent": { "label": "Accent", "setting": "accent_color", "use": "--accent" } },
        "radius": { "base": { "label": "Corners", "default": "3px", "css": "--radius" } }
    }
}
```

- `menus`: extra menu locations, added to the ones from config and plugins.
- `parts`: styles of a part. The default style uses `parts/<name>`, another style uses `parts/<name>-<style>`; a style is offered only when its file exists. `@templatePart('header')` uses the chosen style; an explicit variant in the view still wins. A part without styles can still have a `label`, like `"author-box": {"label": "Author box"}`, so the Site Editor shows its name in the admin language.
- `styles`: design tokens in the groups `color`, `font`, `text`, `spacing`, `radius` and `width`. Each one prints `--verso-<group>-<key>` (blocks use it as `var:<group>.<key>`). `css` is the theme variable an edit writes, `use` is the variable read (for light and dark values), `setting` links the token to a customizer field so both stay in step. Fonts come from the safe self-hosted list in `verso.themes.fonts`.
- `editor.presets` (optional): style presets the theme offers to every block, like `"presets": {"quiet-band": {"label": "Quiet band", "design": {"color": {"background": "var:color.surface"}}}}`. Keys use lowercase letters, digits and dashes; `design` has the shape of `attrs.design`. Saved presets with the same key replace them.
- `easy` (optional): extras for Easy mode "Make it yours": `presets` and `fontPairs` add or change style presets and font pairs, `fontFiles` maps safe font keys to font files in `assets/` so the admin shows real samples. See "Easy mode registries" in docs/EXTENDING.md.
- `parts/<key>.json` (outside `views`): starter blocks, `{ "blocks": [...] }`, shown the first time someone edits that part.

## Dark mode switch

The `color-scheme-toggle` site block ("Dark mode switch") lets visitors pick light or dark colors. It follows the Modern Web Guidance pattern:

- With no saved choice the page follows the system setting. The choice is saved in `localStorage` (`verso-color-scheme`, `light` or `dark`); picking the system colors again removes it.
- The block changes `<meta name="color-scheme">`. When a published part holds the block, `<x-verso-head />` prints a tiny script that applies the saved choice before the first paint, so put `<meta name="color-scheme" content="light dark">` before `<x-verso-head />`.
- The button is hidden until its script runs and shows the action it will do (moon for dark, sun for light), with text for screen readers.

To support it, write colors with `light-dark()` and map the meta tag back to the `color-scheme` property, as `default` and `ashgrove` do:

```css
:root {
    color-scheme: light dark;
    --paper: light-dark(var(--paper-light), var(--paper-dark));

    &:has(> head > meta[name='color-scheme'][content='dark']) { color-scheme: dark; }
    &:has(> head > meta[name='color-scheme'][content='light']) { color-scheme: light; }
}
```

Customizer colors should write the light variable (`--paper-light`), so dark mode keeps its own colors.

## Block patterns

A pattern is a JSON file in `patterns/`:

```json
{
    "name": "call-to-action",
    "title": "Call to action",
    "category": "Call to action",
    "description": "A short heading, one line of text and a button.",
    "keywords": ["button", "signup"],
    "blocks": [
        { "type": "heading", "attrs": { "level": 2 }, "content": "Plan your next trip" },
        { "type": "button", "attrs": { "label": "Read the guides", "url": "/blog", "style": "primary" } }
    ]
}
```

- `name` defaults to the file name, `title` to the name as a headline and `category` to `General`.
- `blocks` uses the normal block format. Ids are optional: the editor gives new ids on every insert. Nested `children` are allowed (like `columns`).
- Files that are broken JSON, larger than `verso.themes.pattern_max_kb` (env `VERSO_THEME_PATTERN_MAX_KB`, default 256) or that use unknown block types are skipped.
- Patterns appear in the block editor under the "Patterns" button, grouped by category, with a search box (title, category, description, name and keywords). Blocks that need a capability (like `html`) still need it when the entry is saved.

The default theme ships `call-to-action`, `two-columns` and `pull-quote`. A pattern that uses a plugin block (like `newsletter-signup`) only shows while that plugin is on.

## Theme assets

Files in `assets/` with an allowed extension (`verso.themes.assets.extensions`: fonts and images) are served at `/theme-assets/<slug>/<path>` (route `theme.asset`, prefix `VERSO_THEME_ASSETS_PREFIX`) with a long browser cache (`VERSO_THEME_ASSETS_CACHE`). A child theme finds the file of a parent. Get a URL with a version from `ThemeManager::assetUrl('fonts/serif.woff2')` and print `@font-face` rules inside the layout `<style>` tag, so no stylesheet blocks rendering.

## Blog helpers

`App\Verso\Theme\Support\BlogQueries` gives the previous and next posts in one query (`neighbours()`), related posts by a taxonomy (`related()`) and terms with their number of published entries (`popularTerms()`). `TableOfContents::from($content)` adds ids to h2 headings and lists them (`verso.themes.toc_min_headings`), and `SearchHighlighter` escapes a text and marks the search words. Users have a `bio`, an `avatar` (media) and `social_links`, edited on Account settings, Profile, "Public profile". `$user->socialProfiles()` gives the links with the network name and `$user->authorUrl()` the author page.

## Author page

`/author/{slug}` (base `verso.users.profile.author_base`, env `VERSO_AUTHOR_BASE`) lists the published posts of one person. People without published posts get a 404, so the user list stays private. Views: `author-{slug}`, `author`, then `archive`, with `$author` (a `User`) and `$entries`. The slug is made from the name once and kept when the name changes. Default and Ashgrove ship `author.blade.php`, an author box with social links under posts, and link the author name to the page.

## Ashgrove

`themes/ashgrove` is the editorial blog theme that demo installs use (`verso.demo.theme`, also the theme the install wizard recommends). It is standalone (no parent) and ships Newsreader and Instrument Sans (SIL Open Font License) in `assets/fonts`, a `wide` page template, the parts `header`, `footer` and `author-box`, and the patterns `hero-intro`, `newsletter-call-to-action` (only with the Newsletter plugin), `about-the-author` and `two-column-feature`. Settings: accent color, heading and body font, logo, layout width, author box (a `toggle` field) and footer text.

## Template debug

Add `?verso-template-debug=1` to any public URL while logged in as a user with the `manage_themes` capability (admins). A small panel lists every lookup of the page (the page view and each part): the candidates in order, which one was used (with its file), which were not found and which were not checked. The page is sent with `Cache-Control: no-store, private` and is never stored in the page cache.

| Config | Env | Default |
| --- | --- | --- |
| `verso.themes.template_debug.enabled` | `VERSO_TEMPLATE_DEBUG` | `true` |
| `verso.themes.template_debug.query` | `VERSO_TEMPLATE_DEBUG_QUERY` | `verso-template-debug` |

## Admin bar

Signed in users with the `view_admin_bar` capability (admins only) see a small bar on top of every public page, with links to the dashboard, new entries, the editor of the shown entry and the customizer. Users can turn it off in their profile ("Show admin bar on the site"). Verso adds it right after `<body>` from PHP, so themes need no code for it, and it is never stored in the page cache.

The bar sits at the top of the page and the `<html>` element gets a top padding of the same size, so it pushes the page down. Sticky headers with `top: 0` keep working. A header with `position: fixed` should move below the bar:

```css
.site-header {
    position: fixed;
    top: var(--verso-adminbar-height, 0px);
}
```

`--verso-adminbar-height` is only set on pages that show the bar. The bar adds its own "Skip to content" link to the theme `<main id="...">`, is hidden in print and needs no theme CSS.

| Config | Env | Default |
| --- | --- | --- |
| `verso.admin_bar.enabled` | `VERSO_ADMIN_BAR` | `true` |
| `verso.admin_bar.height` | `VERSO_ADMIN_BAR_HEIGHT` | `36` (pixels) |

## PHP API

| Class | Use |
| --- | --- |
| `ThemeManager::lineage($theme)` | The theme and its parents, child first. |
| `ThemeManager::missingParent($theme)` | Slug of a missing parent, or null. |
| `ThemeManager::locateFile($theme, $file)` | A theme file from the child or a parent. |
| `TemplateHierarchy` | Candidate lists (`forEntry`, `forTerm`, `forArchive`, `home`, `search`, `notFound`). |
| `ThemeResponse::render($candidates, $data)` | Render the first existing candidate. `view()` renders one fixed view. |
| `PageTemplates` | Templates of the active theme (`forType`, `forEntry`, `options`, `rules`). |
| `TemplateParts::view($name, $variant)` | The view name of a part, or null. |
| `BlockPatterns::all($theme = null)` | Patterns of the active theme and its parents. |

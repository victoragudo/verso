# Theme guide

This guide shows how to make a theme for Verso. There are three paths. Pick the one that fits you:

| Path | You need | Time | Result |
| --- | --- | --- | --- |
| [A. No code](#path-a-no-code-site-editor-and-create-child-theme) | The admin and the `manage_themes` capability | 10 to 20 minutes | A child theme with your colors, fonts, header and footer |
| [B. Child theme by hand](#path-b-a-child-theme-by-hand) | A text editor, some CSS and Blade | 30 to 90 minutes | A child theme that changes some views and styles of a parent |
| [C. Theme from scratch](#path-c-a-theme-from-scratch) | Blade, CSS and the command line | Half a day for a simple theme, 2 to 5 days for a polished one | A complete, standalone theme you can share as a zip |

The reference for every `theme.json` key, the template hierarchy, parts and patterns is [THEMES.md](THEMES.md). This guide is the step by step version, with a complete example theme that is tested on every build.

## Contents

- [Words used in this guide](#words-used-in-this-guide)
- [Path A: no code (Site Editor and "Create child theme")](#path-a-no-code-site-editor-and-create-child-theme)
- [Path B: a child theme by hand](#path-b-a-child-theme-by-hand)
- [Path C: a theme from scratch](#path-c-a-theme-from-scratch)
  - [Start with `verso:make-theme`](#start-with-versomake-theme)
  - [Required views and the `theme::` namespace](#required-views-and-the-theme-namespace)
  - [Data every view gets](#data-every-view-gets)
  - [The layout: hooks that plugins need](#the-layout-hooks-that-plugins-need)
  - [Template hierarchy, page templates and parts](#template-hierarchy-page-templates-and-parts)
  - [Block patterns](#block-patterns)
  - [Customizer settings](#customizer-settings)
  - [Design tokens (`styles`)](#design-tokens-styles)
  - [Editor limits](#editor-limits)
  - [Dark mode and the dark mode switch](#dark-mode-and-the-dark-mode-switch)
  - [Styling blocks](#styling-blocks)
  - [Translations and right to left languages](#translations-and-right-to-left-languages)
  - [Accessibility checklist](#accessibility-checklist)
  - [Performance](#performance)
  - [Theme files: assets and screenshot](#theme-files-assets-and-screenshot)
- [The example theme: Lighthouse](#the-example-theme-lighthouse)
- [Check a theme with `verso:verify-theme`](#check-a-theme-with-versoverify-theme)
- [Package, install and update](#package-install-and-update)
- [A child theme of a third party theme](#a-child-theme-of-a-third-party-theme)
- [Common mistakes](#common-mistakes)

## Words used in this guide

- **Theme folder**: `themes/<slug>/`. The folder name is the slug of the theme (lowercase letters, digits, `-` and `_`).
- **Parent and child**: a child theme has `"parent": "<slug>"` in `theme.json`. It only ships what it changes. Verso looks for each view in the child, then the parent (and its parents), then the fallback theme (`default`, set with `VERSO_FALLBACK_THEME`).
- **Part**: a piece of a page, like the header or the footer, in `views/parts/`.
- **Site Editor**: Design, Site Editor in the admin (`/admin/site-editor`). It edits parts with blocks and stores the result in the database, never in the theme files.
- **Customizer**: Design, Themes, then "Customize" on a theme (`/admin/themes/<slug>/customize`). It shows the fields of `settings` in `theme.json`.

## Path A: no code (Site Editor and "Create child theme")

Use this path when you like a theme (for example Ashgrove) and want your own version of it, with your colors, fonts, header and footer, without writing code.

**Time:** 10 to 20 minutes.

1. Make Ashgrove the active theme: Design, Themes, "Activate" on Ashgrove.
2. Change colors and fonts:
   - Design, Themes, "Customize" on Ashgrove for the theme fields (accent color, fonts, logo, layout width, footer text).
   - Design, Site Editor, the "Colors and fonts" panel for the design tokens (colors, fonts, spacing, corners and widths). Choose "Save colors and fonts".
3. Change the header and the footer: Design, Site Editor, "Edit Header" or "Edit Footer", change the blocks and **publish**. Only published parts go into the child theme; drafts are left out.
4. On the Site Editor page, choose **"Create child theme"**. In the dialog:
   - **Theme name**, like "Ashgrove for Harbor Bakery".
   - **Folder name** (optional). Empty makes it from the name, like `ashgrove-for-harbor-bakery`. It must be new.
   - **Use the new theme now**, to switch to it at once.
5. Choose "Create theme". Verso writes the folder, checks it (see [Check a theme](#check-a-theme-with-versoverify-theme)) and opens the theme page with the report.

What Verso writes in `themes/<folder>/` (`App\Verso\Site\Parts\ChildThemeFromChanges`):

| File | Content |
| --- | --- |
| `theme.json` | `name`, `slug`, `version` `1.0.0`, a description with the date, `author` (your name), `requires` and `php` copied from the parent, `parent`, and only the values you changed: changed customizer fields become new `settings` defaults and changed tokens become new `styles` defaults. |
| `assets/theme.css` | The same changed values as CSS variables. A default in `theme.json` alone prints nothing, so the CSS keeps the look right after you switch. |
| `parts/<key>.json` | One file per published Site Editor part, like `parts/header.json`: `{"blocks": [...]}`. |
| `views/parts/<key>.blade.php` | `<x-verso-part-blocks part="header" key="header" />`, which draws those blocks like an edited part. |
| `screenshot.png` | Copied from the parent (also `.webp` or `.jpg`). |
| `README.md` | A short note with the list of parts built with blocks. |

The header and footer styles you use (like the "centered" header of Ashgrove) are saved as the choices of the new theme. Menus are shared by all themes, so they are not copied. The logo (a media field) is not copied either: set it again in the customizer of the new theme.

**Download it or move it to another site.** The admin has no download button for theme folders. On the server, zip the folder:

```bash
cd themes
zip -r ashgrove-for-harbor-bakery.zip ashgrove-for-harbor-bakery -x '*.DS_Store'
```

On the other site, install the parent (Ashgrove ships with Verso) and then upload the zip (see [Package, install and update](#package-install-and-update)). The "Export" button on the Site Editor saves a customization file (parts and styles as JSON) that "Import" reads on another site; use it when you do not need a theme folder at all.

## Path B: a child theme by hand

Use this path to change a few views or styles of a theme and keep everything else from the parent. Updates of the parent still reach your site.

**Time:** 30 to 90 minutes.

1. Create the folder with the generator:

   ```bash
   php artisan verso:make-theme "Harbor Dusk" --parent=ashgrove --author="Marta Ibarra"
   ```

   It writes three files: `themes/harbor-dusk/theme.json`, `assets/theme.css` and `README.md`. The parent must be an installed theme or a folder next to the new one.

2. Open `theme.json`. It looks like this:

   ```json
   {
       "name": "Harbor Dusk",
       "slug": "harbor-dusk",
       "parent": "ashgrove",
       "version": "0.1.0",
       "description": "A child theme of ashgrove.",
       "author": "Marta Ibarra",
       "templates": {},
       "settings": {}
   }
   ```

   Add `"requires": "^0.1"` and `"php": "^8.4"` so the checks know which Verso and PHP versions it works with.

3. Change styles in `assets/theme.css`. Verso prints the CSS of every theme in the chain inline, oldest parent first, so your rules win:

   ```css
   :root {
       --accent-light: #7c2d12;
   }

   .site-header {
       border-block-end: 4px solid var(--accent);
   }
   ```

   Look in the parent `assets/theme.css` for the variable names it uses.

4. Change one view. Copy it from the parent with the same path and edit the copy. For example, to change only the footer of Ashgrove:

   ```bash
   mkdir -p themes/harbor-dusk/views/parts
   cp themes/ashgrove/views/parts/footer.blade.php themes/harbor-dusk/views/parts/footer.blade.php
   ```

   Every other view still comes from the parent. To find which file a page uses, open the page with `?verso-template-debug=1` while signed in as an admin.

5. Optional: add page templates (`templates` in `theme.json` and a view in `views/templates/`), block patterns (`patterns/*.json`) or customizer fields (`settings`). A child field with the same key replaces the parent field. Values are saved per theme (`theme.<child-slug>.<key>`), so the child starts with the defaults.

6. Check it and turn it on:

   ```bash
   php artisan verso:verify-theme themes/harbor-dusk
   ```

   Then Design, Themes, "Activate" on Harbor Dusk. A child whose parent is missing cannot be activated.

## Path C: a theme from scratch

**Time:** half a day for a simple theme like the [example](#the-example-theme-lighthouse); 2 to 5 days for a polished theme with templates, parts, patterns, dark mode and styles for every block.

### Start with `verso:make-theme`

```bash
php artisan verso:make-theme "Harbor" --description="A calm theme for a ferry company." --author="Marta Ibarra"
```

| Argument or option | Meaning |
| --- | --- |
| `name` | Name of the theme. The slug and folder come from it (`Harbor Lights` becomes `harbor-lights`). |
| `--description=` | One line about the theme. Default: "The `<name>` theme for Verso." |
| `--author=` | Author of the theme. |
| `--parent=` | Make a child theme of this installed theme ([path B](#path-b-a-child-theme-by-hand)). |
| `--force` | Replace files that already exist. |

Themes are written to `themes/` (`verso.generators.themes_path`, env `VERSO_THEMES_PATH`). Without `--parent` it writes:

```
themes/harbor/
  theme.json
  views/layouts/app.blade.php
  views/home.blade.php
  views/entry.blade.php
  views/archive.blade.php
  views/taxonomy.blade.php
  views/search.blade.php
  views/404.blade.php
```

The generated theme renders, but it is a starting point. `verso:verify-theme` reports what is missing: no `<x-verso-seo />`, no skip link, no `requires` and `php`, no screenshot. It also has no `assets/theme.css`, so Verso prints the CSS of the default theme until you add one. The sections below fill those gaps, and [Lighthouse](#the-example-theme-lighthouse) shows a complete result.

### Required views and the `theme::` namespace

Every theme (or its parents) must have these seven views. A missing one stops the install (`theme.missing-view`):

| View | Used for |
| --- | --- |
| `views/layouts/app.blade.php` | The HTML skeleton that every page extends. |
| `views/home.blade.php` | `/` when it lists the latest posts. |
| `views/entry.blade.php` | One post, page or other entry. |
| `views/archive.blade.php` | All entries of a content type, like `/blog`. Also the last fallback of author pages. |
| `views/taxonomy.blade.php` | A category, tag or other term. |
| `views/search.blade.php` | `/search`. |
| `views/404.blade.php` | Pages that do not exist. |

Load views through the `theme::` namespace, never by path: `@extends('theme::layouts.app')`, `@include('theme::partials.entry-list')`. The namespace finds the file in the child, the parents and the fallback theme, in that order.

### Data every view gets

| Variable | Where | Content |
| --- | --- | --- |
| `$site` | every view | `name`, `tagline`, `locale` (like `en` or `pt-BR`) and `dir` (`ltr` or `rtl`). |
| `$themeStyles` | the layout and the parts it includes | The theme CSS, the customizer variables and the design tokens. Print it in a `<style>` tag. |
| `$themeSettings` | the layout and the parts it includes | Customizer values by key, already checked. In page views and their partials, read them with `@inject('customizer', 'App\Verso\Theme\Customizer\ThemeCustomizer')` and `$customizer->values(app('App\Verso\Theme\ThemeManager')->active())`, as the Lighthouse `entry-list` partial does. |
| `$themeLogo` | the layout and the parts it includes | The media item of the first `media` setting, or null. |
| `$hasPrimaryMenu`, `$navigation` | the layout and the parts it includes | True when the `primary` menu location has items; otherwise `$navigation` lists top level pages. |
| `$bodyClass` | the layout | Classes for `<body>`, like the content type and template of the entry. |
| `$entry`, `$content` | entry views | The entry and its rendered blocks (safe HTML). |
| `$entries` | home, archive, taxonomy, author, search | A paginator. On search it is null until the search text is long enough (`$searching`). |
| `$contentType` | archive | `pluralLabel`, `label` and more. |
| `$taxonomy`, `$term` | taxonomy | The taxonomy (`label`) and the term (`name`, `description`). |
| `$search`, `$searching` | search | The search text and whether it is long enough to search (`VERSO_SEARCH_MIN_LENGTH`). |
| `$author` | author views (`author-{slug}`, `author`, then `archive`) | The `User`. |

Page views (like `home`) render their sections before the layout, so the layout variables above are not set there yet.

Useful Blade helpers: `@templatePart('header')`, `@shortcodes($text, 'footer')` (expands shortcodes in a text), `@field('price')` (prints a custom field of `$entry`), `<x-verso-menu location="footer" label="Footer" />`, `<x-verso-featured-image :entry="$entry" />`, `<x-verso-comments :entry="$entry" />`.

### The layout: hooks that plugins need

The layout must print these, or plugins and blocks break:

| Tag | Where | Why |
| --- | --- | --- |
| `<html lang="{{ $site['locale'] }}" dir="{{ $site['dir'] ?? 'ltr' }}">` | top | Screen readers and right to left languages. Missing `lang` is `theme.no-lang`. |
| `<x-verso-seo />` | in `<head>` | Title, description, canonical URL, Open Graph, JSON-LD and verification tags. On entry views pass the entry: `<x-verso-seo :entry="$entry" />`. It also takes `:title` and `:description`. Missing it is `theme.no-seo`. |
| `<meta name="color-scheme" content="light dark">` | in `<head>`, **before** `<x-verso-head />` | Dark mode and the dark mode switch. |
| `<x-verso-head />` | end of `<head>` | Tags from plugins (analytics, fonts) and the CSS of the blocks on the page. Missing it is `theme.no-head-hook`. |
| `<style>{!! $themeStyles !!}</style>` | after `<x-verso-head />` | Theme CSS, customizer values and tokens, inline so nothing blocks rendering. |
| `<x-verso-footer />` | just before `</body>` | Scripts of blocks (tabs, slider, menus), popups and plugin scripts. Missing it is `theme.no-footer-hook`. |

Verso adds the admin bar after `<body>` by itself; themes need no code for it.

### Template hierarchy, page templates and parts

For each page Verso tries a list of view names and renders the first one that exists. For one post at `/blog/ferry-routes` it tries `entry-post-ferry-routes`, `entry-post-{id}`, `entry-post`, then `entry`. So a theme can ship `entry-page.blade.php` for pages only, or `taxonomy-category.blade.php` for categories. The full table is in [THEMES.md, Template hierarchy](THEMES.md#template-hierarchy).

**Page templates** let editors pick a layout per page. Declare them in `theme.json` and add the view:

```json
"templates": {
    "wide": { "label": "Wide page", "description": "The text uses the full page width.", "types": ["page"] }
}
```

The view is `views/templates/wide.blade.php` (or set `"view"`). The editor shows a "Template" select when the active theme offers templates for the content type. A template can also pick part styles, like `"parts": {"header": "minimal"}`.

**Parts** are views in `views/parts/`. Include them with `@templatePart('header')`. A variant is a file named `<part>-<variant>`, like `parts/header-centered.blade.php`. To let admins pick a style in the Site Editor, list the variants in `theme.json`:

```json
"parts": {
    "header": {
        "label": "Header",
        "default": "standard",
        "variants": {
            "standard": { "label": "Standard", "description": "Logo on the left." },
            "centered": { "label": "Centered", "description": "Logo in the middle." }
        }
    }
}
```

The default style uses `parts/header`, other styles use `parts/header-<style>`. A style is only offered when its file exists. `parts/<key>.json` (outside `views/`) can hold starter blocks, `{"blocks": [...]}`, shown the first time someone edits that part in the Site Editor.

### Block patterns

A pattern is a JSON file in `patterns/`. It appears in the block editor under "Patterns":

```json
{
    "name": "call-to-action",
    "title": "Call to action",
    "category": "Call to action",
    "description": "A heading, one line of text and a button.",
    "keywords": ["button", "signup"],
    "blocks": [
        { "type": "heading", "attrs": { "level": 2 }, "content": "Get the harbor newsletter" },
        { "type": "button", "attrs": { "label": "Sign up", "url": "/newsletter", "style": "primary" } }
    ]
}
```

Broken JSON, files over `VERSO_THEME_PATTERN_MAX_KB` (256) and patterns with unknown block types are skipped. A pattern with a plugin block only shows while that plugin is on.

### Customizer settings

`settings` in `theme.json` declares the customizer fields (at most 40). Each key uses lowercase letters, digits and `_`. Fields with `css` write a CSS variable on `:root`, but only when the value differs from the default; your stylesheet sets the default.

| Type | Value | Keys besides `type`, `label`, `help`, `default` | Example |
| --- | --- | --- | --- |
| `color` | `#rrggbb` | `css` | `{"type": "color", "default": "#0b5c8a", "css": "--accent-light"}` |
| `font` | a key of the safe font list | `options` (keys of `verso.themes.fonts`), `css` (gets the font stack) | `{"type": "font", "default": "system", "options": ["system", "charter"], "css": "--font-body"}` |
| `media` | a media id | none. The first media field is the logo (`$themeLogo`). | `{"type": "media", "label": "Logo"}` |
| `size` | a whole number in `unit` | `min`, `max` (required, min lower than max), `unit` (default `px`), `units`, `keywords`, `css` | `{"type": "size", "default": 72, "min": 56, "max": 90, "unit": "rem", "css": "--wide"}` |
| `length` | any CSS size: `42rem`, `65ch`, `clamp(36rem, 60vw, 48rem)` | `unit`, `units`, `keywords`, `min` and `max` (optional), `css` | `{"type": "length", "default": "42rem", "unit": "rem", "units": ["rem", "ch", "px"], "css": "--measure"}` |
| `text` | a string | `max_length` (up to `VERSO_THEME_SETTING_TEXT_MAX`, 500) | `{"type": "text", "default": "", "max_length": 200}` |
| `toggle` | `true` or `false` | none | `{"type": "toggle", "default": true}` |

Rules for `size` and `length`:

- `unit` and every item of `units` must be a CSS length unit: `px cm mm Q in pc pt`, `em rem ex rex cap rcap ch rch ic ric lh rlh`, `vw vh vi vb vmin vmax` and their `sv*`, `lv*`, `dv*` forms, `cqw cqh cqi cqb cqmin cqmax`, and `%`.
- When `units` is set, it must contain `unit`. Empty `units` offers every unit.
- `keywords` may use `auto`, `fit-content`, `min-content`, `max-content`, `none` and `normal`.
- People can also type `calc()`, `clamp()`, `min()`, `max()` or `var()`. A size field keeps a whole number in its own unit (`72`) and checks the range; other units and custom values are kept as clean CSS text.
- Fonts must come from `verso.themes.fonts` (`charter`, `georgia`, `system`, `humanist`, `mono`, `newsreader`, `instrument-sans`). A theme cannot add font keys from `theme.json`. To use your own font file, see [Performance](#performance).

A field with an error (unknown type, a bad default, a unit that is not a length) is left out and reported as `theme.settings-invalid`. Read values in views with `$themeSettings['key']`.

### Design tokens (`styles`)

Tokens are the colors, fonts and sizes that blocks and the Site Editor use. Each token prints `--verso-<group>-<key>`, and block styles point to it as `var:<group>.<key>`. Groups: `color`, `font`, `text`, `spacing`, `radius` and `width`.

```json
"styles": {
    "color": {
        "accent": { "label": "Accent", "setting": "accent_color", "use": "--accent" },
        "background": { "label": "Background", "default": "#fdfcfa", "css": "--paper-light", "use": "--paper" }
    },
    "radius": { "base": { "label": "Corners", "default": "6px", "css": "--radius" } }
}
```

- `setting`: links the token to a customizer field, so both stay in step.
- `default`: the value when there is no setting. Colors use `#rgb` or `#rrggbb`.
- `css`: the theme variable an edit writes (like the light color `--paper-light`).
- `use`: the variable the token reads, so the token follows light and dark mode.

The CSS that ships with core blocks reads `--verso-color-accent`, `--verso-color-background`, `--verso-color-text`, `--verso-color-surface`, `--verso-radius-base` and `--verso-spacing-small`, `-medium`, `-large` and `-x-large`, with fallbacks. Declare these tokens and every block matches your theme.

`editor.presets` (style presets for every block) and `easy` (presets, font pairs and font files for Easy mode "Make it yours") are described in [THEMES.md, Site Editor](THEMES.md#site-editor).

### Editor limits

`editor` in `theme.json` keeps people inside your design:

```json
"editor": {
    "blocks": ["paragraph", "heading", "list", "quote", "image", "gallery", "button", "columns", "column", "separator"],
    "colors": ["#0b5c8a", "#1f2328", "#fdfcfa"],
    "fonts": ["body"],
    "spacing": ["0.5rem", "1rem", "clamp(1rem, 3vw, 2rem)"],
    "units": ["px", "rem", "%", "vw"]
}
```

| Key | Effect |
| --- | --- |
| `blocks` | The only block types every content type may hold. Saving checks it too. |
| `colors` | Hex colors. The color controls show only these swatches. |
| `fonts` | Font names suggested in the Styles panel. |
| `spacing` | Size suggestions of the Styles panel. Each value must be a valid CSS size. |
| `units` | Units of every length input (Styles panel, Site Editor sizes). Saved values in other units stay valid, so switching themes never breaks content. |

Leave a key out to keep the editor open. The block type names are the keys of the [block table](#styling-blocks).

### Dark mode and the dark mode switch

Follow the system setting by default, and let the dark mode switch block override it:

1. Put `<meta name="color-scheme" content="light dark">` in `<head>` before `<x-verso-head />`.
2. Keep each color as a light and a dark variable, and build the used color from them. Browsers without `light-dark()` use the media query:

   ```css
   :root {
       --paper-light: #fdfcfa;
       --paper-dark: #15181c;
       --paper: var(--paper-light);

       @media (prefers-color-scheme: dark) {
           --paper: var(--paper-dark);
       }

       @supports (color: light-dark(white, black)) {
           --paper: light-dark(var(--paper-light), var(--paper-dark));
       }

       color-scheme: light dark;

       &:has(> head > meta[name='color-scheme'][content='dark']) { color-scheme: dark; }
       &:has(> head > meta[name='color-scheme'][content='light']) { color-scheme: light; }
   }
   ```

3. Make customizer colors write the light variable (`"css": "--accent-light"`), so dark mode keeps its own colors.

The `color-scheme-toggle` block ("Dark mode switch") saves the choice in `localStorage` (`verso-color-scheme`) and changes the meta tag. When a published part holds the block, `<x-verso-head />` adds a tiny script that applies the choice before the first paint. Style its button: `.verso-scheme-toggle`, `.verso-scheme-toggle__to-dark` (the moon, shown in light mode) and `.verso-scheme-toggle__to-light` (the sun, shown in dark mode). The [Lighthouse stylesheet](#the-example-theme-lighthouse) has the full rules. The default theme also covers browsers without `light-dark()` when the switch forces a mode.

### Styling blocks

Blocks print plain HTML with `verso-` classes. Builder blocks (tabs, accordion, slider and the others marked "ships CSS") load their own CSS and JavaScript only on pages that use them, through `<x-verso-head />` and `<x-verso-footer />`. That CSS reads the design tokens above, so you only need to style them when you want a different look. The other blocks have no CSS of their own: your theme must style them.

**Content blocks: your theme styles these.**

| Block type | HTML and classes |
| --- | --- |
| `paragraph`, `heading`, `list` | `<p>`, `<h1>`...`<h6>`, `<ul>`/`<ol>` |
| `quote` | `<blockquote>` with `<p>` and `<cite>` |
| `code` | `<pre><code>` |
| `separator` | `<hr>` |
| `image` | `figure.verso-image` with `img` and `figcaption` |
| `gallery` | `figure.verso-gallery[data-columns]`, `.verso-gallery__item`; with the lightbox on, `.verso-lightbox__open` links (lightbox CSS ships) |
| `video` | `figure.verso-video` |
| `embed` | `figure.verso-embed.verso-embed--<provider>`, `.verso-embed__frame` (the iframe), `.verso-embed--link` (a plain link) |
| `table` | `figure.verso-table` with `table`, `thead`, `tbody` |
| `button` | `.verso-buttons` with `a.verso-button.verso-button--primary`, `--secondary` or `--outline` |
| `columns`, `column` | `.verso-columns` with `.verso-column` children. A column with a width gets `style="flex-basis: N%"`, so use flexbox. |
| `section` | `.verso-section` (a `section`, `div` or other tag) |
| `element` | `.verso-element` (any safe HTML tag) |
| `html` | The HTML as written. |
| `map` | `figure.verso-map` with an `iframe` |

**Site blocks (Site Editor parts): your theme styles these.**

| Block type | Classes |
| --- | --- |
| `site-logo`, `site-title`, `site-tagline` | `.verso-site-logo` (`.verso-site-logo__name`), `.verso-site-title`, `.verso-site-tagline` |
| `navigation` | `.verso-nav`, `.verso-nav__list`, `.verso-nav__submenu`, `.verso-nav__toggle`, `.verso-nav__more` |
| `search` | `.verso-search` |
| `social-links` | `.verso-social` |
| `link-columns` | `.verso-link-columns`, `.verso-link-columns__group` |
| `copyright` | `.verso-copyright` |
| `announcement-bar` | `.verso-announcement`, `--accent`, `--dark`, `--light` |
| `color-scheme-toggle` | `.verso-scheme-toggle`, `__option`, `__to-dark`, `__to-light`, `__text` |
| `<x-verso-menu>` | `nav.verso-menu.verso-menu--<location>`, `.verso-menu__list`, `--depth-N`, `.verso-menu__item`, `--active`, `--has-children` |
| `<x-verso-comments>` | `.verso-comments`, `__title`, `__list`, `__form`, `__field`, `__submit`, `__notice`, `__error`, and `.verso-comment`, `__author`, `__meta`, `__content`, `__reply`, `__replies` |
| `<x-verso-featured-image>` | `img.verso-featured-image` |

**Builder blocks: CSS ships, style them to change the look.**

| Block type | Root class | Asset folder in `resources/blocks` |
| --- | --- | --- |
| `tabs`, `tab` | `.verso-tabs` (`__list`, `__panel`) | `tabs` |
| `accordion`, `accordion-item`, `toggle` | `.verso-accordion`, `.verso-disclosure` (`__summary`, `__body`) | `disclosure` |
| `slider`, `slide` | `.verso-slider` (`__track`, `__slide`, `__controls`, `__button`, `__dots`) | `slider` |
| `image-compare` | `.verso-compare` | `image-compare` |
| `video-background` | `.verso-video-bg` | `video-background` |
| `icon`, `icon-list`, `icon-list-item` | `.verso-icon-block`, `.verso-icon-list` | `icons` |
| `counter` | `.verso-counter` | `counter` |
| `progress-bar` | `.verso-progress` | `progress-bar` |
| `timeline`, `timeline-item` | `.verso-timeline` | `timeline` |
| `alert` | `.verso-alert` (`__icon`, `__title`, `__body`) | `alert` |
| `countdown` | `.verso-countdown` | `countdown` |
| `social-share` | `.verso-share` | `social-share` |
| `table-of-contents` | `.verso-toc` | `table-of-contents` |
| `modal` | `.verso-modal`, `.verso-dialog` | `modal` |
| `mega-menu` | `.verso-mega` | `mega-menu` |
| `mobile-menu` | `.verso-mobile-menu` | `mobile-menu` |
| `grid`, `stack`, `spacer`, `shape-divider` | `.verso-grid`, `.verso-stack`, `.verso-spacer`, `.verso-shape` | `layout` |
| `breadcrumbs` | `.verso-breadcrumbs` | `breadcrumbs` |
| `query-loop` | `.verso-query` (`__items`, `__item`, `__pages`, `__empty`) | `query-loop` |
| `popup-button` and popups | `.verso-popup` | `popups` |

**Dynamic blocks** (for the theme builder) print simple tags: `.verso-post-title`, `.verso-post-featured`, `.verso-post-content`, `.verso-post-excerpt`, `.verso-post-date`, `.verso-post-author`, `.verso-post-terms`, `.verso-post-field`, `.verso-reading-time` and `.verso-archive-title`.

Pricing tables, feature grids and similar items in Easy mode are **sections**: groups of the blocks above (columns, headings, lists, buttons). Style the content blocks well and they look right.

To see every block on one page, open the editor, insert each block and preview the entry with your theme. The default and Ashgrove stylesheets in `themes/default/assets/theme.css` and `themes/ashgrove/assets/theme.css` are good references.

### Translations and right to left languages

Public pages use the site language (Settings, General). Put translations in `lang/<code>.json` inside the theme, with plain keys:

```json
{ "Skip to content": "Saltar al contenido" }
```

Verso loads the files of the active theme and its parents with the `<slug>::` prefix. In views write `{{ __('lighthouse::Skip to content') }}`. Always ship `lang/en.json` with every key: a key that is found in no file prints with its prefix, like `lighthouse::Skip to content`.

The same files translate the labels in `theme.json` (template, setting, part, token and pattern labels and help texts) in the admin. For those, write the English label as the key; no prefix is needed in `theme.json`.

Right to left languages (like Arabic) get `dir="rtl"` from `$site['dir']`. Use logical CSS properties so the layout flips by itself: `margin-inline-start` instead of `margin-left`, `padding-inline`, `inset-inline-start`, `border-inline-start`, `text-align: start`. Leave room for longer text: German and Russian are often 30% longer than English.

### Accessibility checklist

- [ ] `<html lang dir>` from `$site`.
- [ ] A skip link as the first focusable element: `<a href="#content" class="skip-link">Skip to content</a>`, and `<main id="content" tabindex="-1">`. The check looks for a link to `#...` with `skip` in its class (written after `href`) or text that starts with "Skip".
- [ ] One `<h1>` per page, then headings in order.
- [ ] Landmarks: `<header>`, `<nav aria-label="...">`, `<main>`, `<footer>`. Each `nav` has its own label.
- [ ] A visible focus style on everything people can click: `:focus-visible { outline: 3px solid ...; outline-offset: 2px; }`. Never `outline: none` without a replacement.
- [ ] Click and tap targets of at least 24 by 24 CSS pixels (WCAG 2.2); 44 by 44 for main buttons.
- [ ] Text contrast of at least 4.5:1 (3:1 for large text and icons), in light **and** dark mode.
- [ ] Labels for every form field (the search form too).
- [ ] `@media (prefers-reduced-motion: reduce)` turns off animations and smooth scrolling.
- [ ] `@media (forced-colors: active)` keeps borders on buttons and toggles.
- [ ] Images in your views have `alt` (empty `alt=""` for decoration) and `width` and `height`.
- [ ] Links are underlined, or clearly different from text in another way than color.

### Performance

- **Images**: use `<x-verso-featured-image :entry="$entry" />`. It prints `srcset`, `sizes`, `width`, `height` and lazy loading. Pass `:eager="true"` for the image at the top of a page (it adds `fetchpriority="high"`), and a `sizes` that fits your layout, like `sizes="(min-width: 48rem) 42rem, 100vw"`. In lists, make only the first image eager (`:eager="$loop->first"`). `:skip-lead-image="true"` hides the featured image when the first block already shows it. When an entry has no featured image on top, Verso gives the first image of the content a high priority by itself.
- **Sizes of block images**: `VERSO_IMAGE_BLOCK_SIZES`, `VERSO_IMAGE_GALLERY_SIZES` and `VERSO_IMAGE_FEATURED_SIZES` set the `sizes` attribute. Match them to your text width.
- **CSS**: keep it in `assets/theme.css`. It is printed inline, so it needs no extra request. Keep it small (the default theme is about 1,250 lines).
- **Fonts**: ship `woff2` files in `assets/fonts/` (a remote font service is reported as `theme.remote-font`, because it shares the address of every visitor). Get a URL with a version from the theme manager and print `@font-face` in the layout `<style>` tag, with `font-display: swap`, and preload the main file:

  ```blade
  @inject('themeFiles', 'App\Verso\Theme\ThemeManager')
  <link rel="preload" href="{{ $themeFiles->assetUrl('fonts/body.woff2') }}" as="font" type="font/woff2" crossorigin>
  ```

  Ashgrove does this in `views/partials/fonts.blade.php` and `views/partials/font-faces.blade.php`.
- **JavaScript**: most themes need none. If you add a script, use `defer` or `type="module"` (a blocking script is `theme.render-blocking-script`) and keep it in `assets/` or inline without printed data.
- **Page cache**: public pages are cached. After you edit theme files, use "Clear page cache" on Design, Themes.

### Theme files: assets and screenshot

- `assets/` files with an allowed extension (`woff2`, `woff`, `svg`, `png`, `jpg`, `jpeg`, `webp`, `avif`, `ico`) are served at `/theme-assets/<slug>/<path>` with a long browser cache. Use `ThemeManager::assetUrl('images/wave.svg')`, which adds a version to the URL and finds the file in a parent too.
- `screenshot.png` (or `.webp`, `.jpg`, `.jpeg`, or the file named in `"screenshot"` in `theme.json`, which may also be `.avif`) at the top of the folder. Limits: 1024 KB and 2400 by 1800 pixels (`VERSO_VERIFY_THEME_SCREENSHOT_MAX_KB`, `_MAX_WIDTH`, `_MAX_HEIGHT`). The bundled themes use 1200 by 900 pixels (4:3) of the home page with demo content.

## The example theme: Lighthouse

Lighthouse is a small but complete theme: all required views, a page template, two parts, a pattern, customizer fields of every type, design tokens, dark mode, styles for the content blocks, translations and the accessibility basics. It passes `verso:verify-theme` with a low risk and only information findings.

The same files live in `tests/Fixtures/themes/lighthouse`. `tests/Feature/Docs/ThemeGuideExampleTest.php` checks that every file below matches the fixture, verifies the fixture and renders every page with it, so this example keeps working.

To try it, create each file below in `themes/lighthouse/`, add a `screenshot.png` (1200 by 900 pixels), then run `php artisan verso:verify-theme themes/lighthouse` and activate it on Design, Themes.

```
themes/lighthouse/
  theme.json
  assets/theme.css
  lang/en.json
  lang/es.json
  patterns/call-to-action.json
  screenshot.png                    (add your own image)
  views/layouts/app.blade.php
  views/parts/header.blade.php
  views/parts/footer.blade.php
  views/partials/entry-list.blade.php
  views/templates/wide.blade.php
  views/home.blade.php
  views/entry.blade.php
  views/archive.blade.php
  views/taxonomy.blade.php
  views/search.blade.php
  views/404.blade.php
```

### `theme.json`

<!-- example-file: theme.json -->
```json
{
    "name": "Lighthouse",
    "slug": "lighthouse",
    "version": "1.0.0",
    "description": "A small, fast theme for reading, with light and dark colors.",
    "author": "Marta Ibarra",
    "requires": "^0.1",
    "php": "^8.4",
    "templates": {
        "wide": {
            "label": "Wide page",
            "description": "The text uses the full page width.",
            "types": ["page"]
        }
    },
    "styles": {
        "color": {
            "accent": { "label": "Accent", "setting": "accent_color", "use": "--accent" },
            "background": { "label": "Background", "default": "#fdfcfa", "css": "--paper-light", "use": "--paper" },
            "text": { "label": "Text", "default": "#1f2328", "css": "--ink-light", "use": "--ink" },
            "surface": { "label": "Panels", "default": "#f1efea", "css": "--panel-light", "use": "--panel" }
        },
        "font": {
            "body": { "label": "Text", "setting": "body_font" }
        },
        "spacing": {
            "small": { "label": "Small", "default": "0.5rem" },
            "medium": { "label": "Medium", "default": "1rem" },
            "large": { "label": "Large", "default": "1.5rem" },
            "x-large": { "label": "Extra large", "default": "3rem" }
        },
        "radius": {
            "base": { "label": "Corners", "default": "6px", "css": "--radius" }
        },
        "width": {
            "content": { "label": "Text column", "setting": "text_width" }
        }
    },
    "settings": {
        "accent_color": {
            "type": "color",
            "label": "Accent color",
            "help": "Links and buttons in light mode.",
            "default": "#0b5c8a",
            "css": "--accent-light"
        },
        "body_font": {
            "type": "font",
            "label": "Text font",
            "default": "system",
            "options": ["system", "humanist", "charter", "georgia"],
            "css": "--font-body"
        },
        "text_width": {
            "type": "length",
            "label": "Text width",
            "help": "Line length of posts and pages.",
            "default": "42rem",
            "unit": "rem",
            "units": ["rem", "ch", "px"],
            "css": "--measure"
        },
        "page_width": {
            "type": "size",
            "label": "Page width",
            "default": 72,
            "min": 56,
            "max": 90,
            "unit": "rem",
            "css": "--wide"
        },
        "logo": {
            "type": "media",
            "label": "Logo"
        },
        "show_excerpts": {
            "type": "toggle",
            "label": "Show excerpts in lists",
            "default": true
        },
        "footer_text": {
            "type": "text",
            "label": "Footer text",
            "default": "",
            "max_length": 200
        }
    }
}
```

### `assets/theme.css`

<!-- example-file: assets/theme.css -->
```css
:root {
    --paper-light: #fdfcfa;
    --paper-dark: #15181c;
    --ink-light: #1f2328;
    --ink-dark: #e8e6e1;
    --panel-light: #f1efea;
    --panel-dark: #1f2329;
    --line-light: #d9d5cc;
    --line-dark: #3a3f47;
    --accent-light: #0b5c8a;
    --accent-dark: #7cc4f0;

    --paper: var(--paper-light);
    --ink: var(--ink-light);
    --panel: var(--panel-light);
    --line: var(--line-light);
    --accent: var(--accent-light);

    @media (prefers-color-scheme: dark) {
        --paper: var(--paper-dark);
        --ink: var(--ink-dark);
        --panel: var(--panel-dark);
        --line: var(--line-dark);
        --accent: var(--accent-dark);
    }

    @supports (color: light-dark(white, black)) {
        --paper: light-dark(var(--paper-light), var(--paper-dark));
        --ink: light-dark(var(--ink-light), var(--ink-dark));
        --panel: light-dark(var(--panel-light), var(--panel-dark));
        --line: light-dark(var(--line-light), var(--line-dark));
        --accent: light-dark(var(--accent-light), var(--accent-dark));
    }

    --font-body: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    --measure: 42rem;
    --wide: 72rem;
    --radius: 6px;

    color-scheme: light dark;
    accent-color: var(--accent);

    &:has(> head > meta[name='color-scheme'][content='dark']) {
        color-scheme: dark;
    }

    &:has(> head > meta[name='color-scheme'][content='light']) {
        color-scheme: light;
    }
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--paper);
    color: var(--ink);
    font: 1.0625rem/1.65 var(--font-body);
}

a {
    color: var(--accent);
    text-underline-offset: 0.2em;
}

:focus-visible {
    outline: 3px solid var(--accent);
    outline-offset: 2px;
}

img,
video {
    max-inline-size: 100%;
    block-size: auto;
}

h1,
h2,
h3 {
    line-height: 1.2;
    text-wrap: balance;
}

.wrap {
    inline-size: min(100% - 2rem, var(--wide));
    margin-inline: auto;
}

.skip-link {
    position: absolute;
    inset-inline-start: 1rem;
    inset-block-start: -10rem;
    padding: 0.75rem 1rem;
    background: var(--ink);
    color: var(--paper);
}

.skip-link:focus {
    inset-block-start: 1rem;
}

main:focus {
    outline: none;
}

.site-header,
.site-footer {
    padding-block: 1rem;
    border-block-end: 1px solid var(--line);
}

.site-footer {
    margin-block-start: 4rem;
    border-block-start: 1px solid var(--line);
    border-block-end: 0;
}

.header-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.site-title {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: inherit;
    font-weight: 700;
    text-decoration: none;
}

.site-nav ul {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.site-nav a {
    display: inline-block;
    min-block-size: 24px;
    padding-block: 0.25rem;
}

.entry,
.prose {
    max-inline-size: var(--measure);
}

.entry--wide,
.entry--wide .prose {
    max-inline-size: none;
}

.entry-list {
    display: grid;
    gap: 2rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.meta {
    color: color-mix(in srgb, var(--ink) 70%, transparent);
    font-size: 0.9375rem;
}

.pagination {
    display: flex;
    justify-content: space-between;
    margin-block-start: 2rem;
}

.prose blockquote {
    margin-inline: 0;
    padding-inline-start: 1rem;
    border-inline-start: 4px solid var(--accent);
}

.prose pre {
    overflow-x: auto;
    padding: 1rem;
    background: var(--panel);
    border-radius: var(--radius);
}

.prose hr {
    border: 0;
    border-block-start: 1px solid var(--line);
}

.verso-image,
.verso-gallery,
.verso-video,
.verso-embed,
.verso-table {
    margin-inline: 0;
}

.verso-table {
    overflow-x: auto;
}

.verso-table table {
    border-collapse: collapse;
    inline-size: 100%;
}

.verso-table :is(th, td) {
    padding: 0.5rem;
    border-block-end: 1px solid var(--line);
    text-align: start;
}

.verso-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 12rem), 1fr));
    gap: 0.75rem;
}

.verso-gallery__item {
    margin: 0;
}

.verso-embed__frame iframe {
    inline-size: 100%;
    aspect-ratio: 16 / 9;
    border: 0;
}

.verso-columns {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.verso-column {
    flex: 1 1 14rem;
}

.verso-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.verso-button {
    display: inline-flex;
    align-items: center;
    min-block-size: 2.75rem;
    padding: 0.5rem 1.25rem;
    border: 2px solid var(--accent);
    border-radius: var(--radius);
    background: var(--accent);
    color: var(--paper);
    font-weight: 600;
    text-decoration: none;
}

.verso-button--secondary {
    background: var(--panel);
    border-color: var(--panel);
    color: var(--ink);
}

.verso-button--outline {
    background: transparent;
    color: var(--accent);
}

.verso-section {
    padding-block: 2rem;
}

.verso-scheme-toggle {
    min-block-size: 2.5rem;
    min-inline-size: 2.5rem;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
}

.verso-scheme-toggle[hidden],
.verso-scheme-toggle__to-light {
    display: none;
}

@media (prefers-color-scheme: dark) {
    .verso-scheme-toggle__to-dark {
        display: none;
    }

    .verso-scheme-toggle__to-light {
        display: inline;
    }
}

:root:has(> head > meta[name='color-scheme'][content='light']) .verso-scheme-toggle__to-dark,
:root:has(> head > meta[name='color-scheme'][content='dark']) .verso-scheme-toggle__to-light {
    display: inline;
}

:root:has(> head > meta[name='color-scheme'][content='light']) .verso-scheme-toggle__to-light,
:root:has(> head > meta[name='color-scheme'][content='dark']) .verso-scheme-toggle__to-dark {
    display: none;
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}

@media (forced-colors: active) {
    .verso-button,
    .verso-scheme-toggle {
        border-color: ButtonText;
    }
}
```

### `lang/en.json`

<!-- example-file: lang/en.json -->
```json
{
    "Footer": "Footer",
    "Go to the home page": "Go to the home page",
    "Main": "Main",
    "Newer posts": "Newer posts",
    "Nothing has been published here yet.": "Nothing has been published here yet.",
    "Older posts": "Older posts",
    "Page not found": "Page not found",
    "Pagination": "Pagination",
    "Results for “:search”": "Results for “:search”",
    "Search": "Search",
    "Search the site": "Search the site",
    "Skip to content": "Skip to content"
}
```

### `lang/es.json`

<!-- example-file: lang/es.json -->
```json
{
    "Accent color": "Color de acento",
    "Footer": "Pie de página",
    "Go to the home page": "Ir a la página de inicio",
    "Main": "Principal",
    "Newer posts": "Entradas más recientes",
    "Nothing has been published here yet.": "Todavía no se ha publicado nada aquí.",
    "Older posts": "Entradas anteriores",
    "Page not found": "Página no encontrada",
    "Pagination": "Paginación",
    "Results for “:search”": "Resultados para «:search»",
    "Search": "Buscar",
    "Search the site": "Buscar en el sitio",
    "Skip to content": "Saltar al contenido",
    "Wide page": "Página ancha"
}
```

### `patterns/call-to-action.json`

<!-- example-file: patterns/call-to-action.json -->
```json
{
    "name": "call-to-action",
    "title": "Call to action",
    "category": "Call to action",
    "description": "A heading, one line of text and a button.",
    "keywords": ["button", "signup"],
    "blocks": [
        { "type": "heading", "attrs": { "level": 2 }, "content": "Get the harbor newsletter" },
        { "type": "paragraph", "attrs": {}, "content": "<p>Tide times, ferry news and one good walk, every Friday.</p>" },
        { "type": "button", "attrs": { "label": "Sign up", "url": "/newsletter", "style": "primary" } }
    ]
}
```

### `views/layouts/app.blade.php`

<!-- example-file: views/layouts/app.blade.php -->
```blade
<!DOCTYPE html>
<html lang="{{ $site['locale'] }}" dir="{{ $site['dir'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @hasSection('seo')
        @yield('seo')
    @else
        <x-verso-seo :title="View::hasSection('title') ? trim(View::yieldContent('title')) : null" />
    @endif
    <meta name="color-scheme" content="light dark">
    @stack('head')
    <x-verso-head />
    <style>{!! $themeStyles !!}</style>
</head>
<body class="{{ $bodyClass ?? '' }}">
    <a href="#content" class="skip-link">{{ __('lighthouse::Skip to content') }}</a>

    @templatePart('header')

    <main id="content" tabindex="-1">
        @yield('content')
    </main>

    @templatePart('footer')
    <x-verso-footer />
</body>
</html>
```

### `views/parts/header.blade.php`

<!-- example-file: views/parts/header.blade.php -->
```blade
<header class="site-header">
    <div class="wrap header-inner">
        <a class="site-title" href="{{ url('/') }}">
            @if ($themeLogo)
                <img src="{{ $themeLogo->url() }}" alt="" width="32" height="32">
            @endif
            {{ $site['name'] }}
        </a>

        @if ($hasPrimaryMenu)
            <x-verso-menu location="primary" :label="__('lighthouse::Main')" class="site-nav" />
        @else
            <nav class="site-nav" aria-label="{{ __('lighthouse::Main') }}">
                <ul>
                    @foreach ($navigation as $page)
                        <li><a href="{{ $page->url() }}">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</header>
```

### `views/parts/footer.blade.php`

<!-- example-file: views/parts/footer.blade.php -->
```blade
<footer class="site-footer">
    <div class="wrap">
        <p>{{ $site['name'] }}</p>
        @if (($themeSettings['footer_text'] ?? '') !== '')
            <p>{{ $themeSettings['footer_text'] }}</p>
        @endif
        <x-verso-menu location="footer" :label="__('lighthouse::Footer')" />
        <form role="search" method="get" action="{{ route('theme.search') }}">
            <label for="footer-search">{{ __('lighthouse::Search the site') }}</label>
            <input id="footer-search" type="search" name="q" required>
            <button type="submit">{{ __('lighthouse::Search') }}</button>
        </form>
    </div>
</footer>
```

### `views/partials/entry-list.blade.php`

<!-- example-file: views/partials/entry-list.blade.php -->
```blade
@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
@inject('customizer', 'App\Verso\Theme\Customizer\ThemeCustomizer')
@inject('themes', 'App\Verso\Theme\ThemeManager')
@php($settings = $customizer->values($themes->active()))
@if ($entries->isEmpty())
    <p>{{ __('lighthouse::Nothing has been published here yet.') }}</p>
@else
    <ol class="entry-list" role="list">
        @foreach ($entries as $entry)
            <li>
                <article>
                    <x-verso-featured-image :entry="$entry" :eager="$loop->first" sizes="(min-width: 48rem) 42rem, 100vw" />
                    <h2><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
                    @if ($entry->published_at)
                        <p class="meta"><time datetime="{{ $siteTime->iso($entry->published_at) }}">{{ $siteTime->format($entry->published_at, 'F j, Y') }}</time></p>
                    @endif
                    @if ($settings['show_excerpts'] && $entry->excerpt)
                        <p>{{ $entry->excerpt }}</p>
                    @endif
                </article>
            </li>
        @endforeach
    </ol>
    @if ($entries->hasPages())
        <nav class="pagination" aria-label="{{ __('lighthouse::Pagination') }}">
            @if (! $entries->onFirstPage())
                <a href="{{ $entries->previousPageUrl() }}" rel="prev">{{ __('lighthouse::Newer posts') }}</a>
            @endif
            @if ($entries->hasMorePages())
                <a href="{{ $entries->nextPageUrl() }}" rel="next">{{ __('lighthouse::Older posts') }}</a>
            @endif
        </nav>
    @endif
@endif
```

### `views/templates/wide.blade.php`

<!-- example-file: views/templates/wide.blade.php -->
```blade
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    <article class="wrap entry entry--wide">
        <h1>{{ $entry->title }}</h1>
        <div class="prose">
            {!! $content !!}
        </div>
    </article>
@endsection
```

### `views/home.blade.php`

<!-- example-file: views/home.blade.php -->
```blade
@extends('theme::layouts.app')

@section('content')
    <div class="wrap">
        <h1>{{ $site['name'] }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
```

### `views/entry.blade.php`

<!-- example-file: views/entry.blade.php -->
```blade
@extends('theme::layouts.app')

@section('seo')
    <x-verso-seo :entry="$entry" />
@endsection

@section('content')
    <article class="wrap entry">
        <h1>{{ $entry->title }}</h1>
        <x-verso-featured-image :entry="$entry" :eager="true" :skip-lead-image="true" />
        <div class="prose">
            {!! $content !!}
        </div>
    </article>

    <x-verso-comments :entry="$entry" />
@endsection
```

### `views/archive.blade.php`

<!-- example-file: views/archive.blade.php -->
```blade
@extends('theme::layouts.app')

@section('title', $contentType->pluralLabel)

@section('content')
    <div class="wrap">
        <h1>{{ $contentType->pluralLabel }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
```

### `views/taxonomy.blade.php`

<!-- example-file: views/taxonomy.blade.php -->
```blade
@extends('theme::layouts.app')

@section('title', $term->name)

@section('content')
    <div class="wrap">
        <p class="meta">{{ $taxonomy->label }}</p>
        <h1>{{ $term->name }}</h1>
        @include('theme::partials.entry-list', ['entries' => $entries])
    </div>
@endsection
```

### `views/search.blade.php`

<!-- example-file: views/search.blade.php -->
```blade
@extends('theme::layouts.app')

@section('title', __('lighthouse::Search'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="wrap">
        <h1>{{ $searching ? __('lighthouse::Results for “:search”', ['search' => $search]) : __('lighthouse::Search') }}</h1>
        <form role="search" method="get" action="{{ route('theme.search') }}">
            <label for="page-search">{{ __('lighthouse::Search the site') }}</label>
            <input id="page-search" type="search" name="q" value="{{ $search }}" required>
            <button type="submit">{{ __('lighthouse::Search') }}</button>
        </form>
        @if ($searching)
            @include('theme::partials.entry-list', ['entries' => $entries])
        @endif
    </div>
@endsection
```

### `views/404.blade.php`

<!-- example-file: views/404.blade.php -->
```blade
@extends('theme::layouts.app')

@section('title', __('lighthouse::Page not found'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <div class="wrap">
        <h1>{{ __('lighthouse::Page not found') }}</h1>
        <p><a href="{{ url('/') }}">{{ __('lighthouse::Go to the home page') }}</a></p>
    </div>
@endsection
```

## Check a theme with `verso:verify-theme`

```bash
php artisan verso:verify-theme themes/lighthouse
php artisan verso:verify-theme ~/Downloads/lighthouse.zip
php artisan verso:verify-theme themes/lighthouse --json
php artisan verso:verify-theme themes/lighthouse --no-sandbox
```

It takes a folder or a zip. It checks `theme.json`, the views (of the theme and its installed parents), the screenshot, the settings, every Blade view and stylesheet, then renders the home, entry and archive pages with demo posts in a separate PHP process with a temporary database (the sandbox). `--no-sandbox` skips the render; `--json` prints the full report.

The output is a line like `Lighthouse 1.0.0: Low risk (score 0)` and a table of findings. The command fails (exit code 1) when the risk is high or a finding stops the install.

**Score and risk.** Each finding adds points by severity: info 0, low 2, medium 6, high 25 (`VERSO_VERIFY_WEIGHT_*`). A score from 6 is medium risk, from 25 high risk (`VERSO_VERIFY_MEDIUM_SCORE`, `VERSO_VERIFY_HIGH_SCORE`). Any high finding makes the theme high risk.

**Findings that stop the install**, whatever the score:

| Rule | Meaning | Fix |
| --- | --- | --- |
| `theme.manifest-invalid` | `theme.json` is missing, not valid JSON, or has no `name`, a bad `slug` or no `version` like `1.0.0`. | Fix `theme.json`. |
| `theme.missing-view` | A required view is in neither the theme nor its parents. The file column names it. | Add the view, or install the parent. |
| `theme.parent-missing` | The parent theme is not installed, or the theme names itself as parent. | Install the parent first. |
| `theme.render-failed` | A page failed in the sandbox (a Blade error, an exception or a status other than 200). The message names the view and line. | Fix the view. Run with `--json` to see the full error. |

**Other findings:**

| Rule | Severity | Meaning and fix |
| --- | --- | --- |
| `theme.slug-mismatch` | medium | The `slug` in `theme.json` is not the folder name. Make them equal. |
| `theme.manifest-incomplete` | low | No `description` or `author`. Add them. |
| `theme.requires-missing` | low | No `requires` (Verso versions, like `^0.1`) or `php` (like `^8.4`). Add them. |
| `theme.verso-version`, `theme.php-version` | high or low | This site does not match the constraint (high), or the constraint cannot be read (low). |
| `theme.settings-invalid` | medium | A customizer field is broken. The message says which and why. |
| `theme.screenshot-missing` | low | Add `screenshot.png`. |
| `theme.screenshot-invalid`, `theme.screenshot-too-large` | medium or low | Wrong type, not readable, over 1024 KB (medium) or over 2400 by 1800 pixels (low). |
| `theme.no-seo` | medium | No view uses `<x-verso-seo />`. |
| `theme.no-head-hook`, `theme.no-footer-hook` | medium | `<x-verso-head />` or `<x-verso-footer />` is missing. Plugins and builder blocks need them. |
| `theme.no-skip-link` | low | No skip link found. See the [accessibility checklist](#accessibility-checklist). |
| `theme.no-lang` | medium | `<html>` has no `lang`. |
| `theme.php-file` | high | A `.php` file that is not a Blade view. Themes may only hold Blade views. |
| `theme.raw-output` | info | `{!! !!}` prints HTML without escaping. Fine for `$content` and `$themeStyles`, which Verso made safe. |
| `theme.raw-user-output` | high | `{!! !!}` prints request or visitor data (like `request()` or a comment). Use `{{ }}`. |
| `theme.inline-handler` | medium | An `onclick=` or other `on...` attribute. Use a script file and `addEventListener`. |
| `theme.external-script`, `theme.external-style` | medium | A script, stylesheet or file from another host that is not in `"network"` in `theme.json`. Ship the file, or declare the host: `"network": ["cdn.example.org"]`. |
| `theme.remote-font` | medium | Fonts from a font service like Google Fonts. Ship the font files in `assets/fonts`. |
| `theme.external-form` | medium | A form that sends to another site. |
| `theme.render-blocking-script` | low | A `<script src>` without `defer`, `async` or `type="module"`. |
| `theme.image-size` | low | An `<img>` without `width` and `height`. |
| `blade.inline-script`, `blade.script-injection` | low, medium | An inline script, or data printed inside one. Move the script to a file and pass data with `data-` attributes. |
| `php.*` | varies | Risky PHP inside `@php` blocks (like `eval`, shell calls, file writes, network calls). Move logic out of the theme. |
| `scan.file-too-large` | medium | A file over `VERSO_VERIFY_MAX_FILE_KB` (1024) was not scanned. |
| `sandbox.timeout` | high | Rendering took longer than `VERSO_VERIFY_SANDBOX_TIMEOUT` (60 seconds). |
| `sandbox.error`, `sandbox.skipped` | medium, info | The sandbox could not run, or was turned off. |

The admin runs the same checks: on upload, when "Create child theme" makes a theme, and with "Run checks again" on the theme page (Design, Themes, then the theme).

## Package, install and update

**Make the zip.** Put the theme folder at the top of the zip, or its files directly:

```bash
cd themes
zip -r lighthouse-1.0.0.zip lighthouse -x '*.DS_Store' -x '*/node_modules/*'
php artisan verso:verify-theme lighthouse-1.0.0.zip
```

Package rules: at most 10 MB (`VERSO_EXTENSIONS_MAX_UPLOAD_KB`), 50 MB unpacked, 2,000 files, no hidden files (names that start with `.`), no links, only allowed file types (`json md txt css js mjs svg png jpg jpeg gif webp avif ico woff woff2` and a few more, plus `LICENSE`, `README`, `CHANGELOG`, `NOTICE` and `AUTHORS`), and PHP files only as Blade views inside `views/`. `__MACOSX` folders and `.DS_Store` files are ignored.

**Install it from the admin** as a user with the `manage_themes` capability (admins have it):

1. Administration, Plugins and themes, tab "Add new".
2. Under "Upload a zip file", set **Type** to Theme, pick the **Zip file** and choose "Check package".
3. The review page shows the theme, its version, the files and the verification report: the risk level, the score and each finding with file and line.
   - **Low or medium risk**: choose "Accept and install".
   - **High risk**: you must type the theme slug to confirm.
   - **A finding that stops the install**: the button is refused. Fix the theme and upload it again.
4. Tick "Activate after install" to switch to it at once, or activate it later on Design, Themes.

**Update a theme.** Raise `version` in `theme.json` (like `1.0.0` to `1.1.0`), zip it and upload it the same way. The review page says "Update" and the button "Accept and update". Verso moves the old folder to a backup (it keeps 3, `VERSO_EXTENSIONS_BACKUPS_KEPT`), puts the new one in place and rolls back if the new version fails. An older version is refused; the same version can be uploaded again. Customizer values, Site Editor parts and template choices are stored in the database by slug, so they stay. Never change the slug of a theme people use.

**Remove a theme** on Design, Themes. The active theme and the fallback theme cannot be removed. You choose to keep its settings or delete them.

## A child theme of a third party theme

1. Install the parent theme first (upload its zip). A child without its parent is blocked by `theme.parent-missing`.
2. Create the child with `php artisan verso:make-theme "Harbor Dusk" --parent=<parent-slug>`, or with "Create child theme" in the Site Editor while the parent is active.
3. Override as little as you can. Every view you copy stops getting fixes from the parent. Prefer CSS variables and new parts over copies of big views.
4. When the parent updates, check your copied views against the new parent versions (the template debug panel shows which file each page uses), then run `verso:verify-theme` on the child.
5. `theme.json` has no way to require a parent version. Write the parent version you tested in the child `README.md` and `description`.
6. Ship the child zip on its own. On a new site, install the parent zip, then the child zip.

## Common mistakes

| Mistake | What happens | Fix |
| --- | --- | --- |
| No `assets/theme.css` in a standalone theme | Verso prints the default theme CSS instead. | Add `assets/theme.css`, even a small one. |
| No `<x-verso-head />` or `<x-verso-footer />` | Tabs, sliders, menus, popups and plugin scripts do not work; block CSS is missing. | Add both to the layout. |
| `<meta name="color-scheme">` after `<x-verso-head />` | The dark mode switch flashes the wrong colors on load. | Put the meta tag first. |
| Customizer color writes the used variable (`--accent`) | Dark mode gets the light color. | Write the light variable (`--accent-light`). |
| `slug` in `theme.json` differs from the folder | `theme.slug-mismatch`; Verso uses the folder name. | Keep them equal. |
| Views loaded by path or with `@include('layouts.app')` | The view is not found, or child themes cannot replace it. | Use `theme::`, like `@include('theme::partials.entry-list')`. |
| A `.php` file that is not a Blade view, or a view outside `views/` | The zip is refused, or `theme.php-file` (high). | Keep only `*.blade.php` in `views/`. Logic belongs in a plugin. |
| `{!! !!}` on data from visitors or the request | `theme.raw-user-output` (high) and a script injection risk. | Use `{{ }}`. |
| Google Fonts or another font service | `theme.remote-font`. | Ship `woff2` files in `assets/fonts`. |
| A font key that is not in `verso.themes.fonts` | The setting is left out (`theme.settings-invalid`). | Use a safe key, or a fixed `font-family` in CSS. |
| A `length` field with `units` that do not include `unit` | The setting is left out. | Add the `unit` to `units`. |
| A default that does not pass the field rules (like `"#fff"` for a color) | The setting is left out. | Colors need 6 digits: `#ffffff`. Sizes must be inside `min` and `max`. |
| `__('lighthouse::Text')` without `lang/en.json` | English pages show `lighthouse::Text`. | Ship `lang/en.json` with every key. |
| Skip link written as `<a class="skip-link" href="#content">{{ __('...') }}</a>` | `theme.no-skip-link`, because the check reads `href` first, then `class`, and cannot read translated text. | Write `href` before `class`. |
| Fixed `left` and `right` in CSS | The layout does not flip in Arabic. | Use logical properties. |
| Editing theme files on a live site | Visitors keep seeing cached pages. | "Clear page cache" on Design, Themes. |
| A child copies many parent views | Parent fixes no longer reach the child. | Copy only what you change. |

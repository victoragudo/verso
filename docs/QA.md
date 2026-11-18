# QA pass (November 2026)

A full product check in a real browser (Playwright, Chromium) on branch `feat/qa`. The database was reset with `migrate:fresh --seed`, and all demo plugins (AI Assistant, Analytics, Memberships, Newsletter, Shop) were enabled from the Plugins page. WCAG 2.2 AA was the target, with the `better-accessibility` checklist.

Screenshots of every page are in `.verso-state/screenshots/qa/` (local only): `<page>-1440.png`, `<page>-390.png`, `<page>-dark.png` and `flow-*.png`.

## What was checked

**Admin pages** at 1440px and 390px, in light and dark mode: dashboard, posts, pages, products (list, editor, inventory), categories, tags, media, comments, users, menus, redirects, site settings, themes, customizer, plugins, extensions (upload and browse), importer, AI assistant, analytics, members, plans, campaigns, new campaign, newsletter settings, subscribers, orders, order detail, shop settings and the account settings pages.

For each page a script checked: no horizontal scroll, no console errors, no failed requests, one `h1`, one `main`, a name on every input, button and link, `alt` on every image, no positive `tabindex`, and text contrast against its real background (light and dark).

**Public site** at 1440px and 390px: home, blog, post, page, category, tag, search (with and without results), 404, shop, cart, checkout, order page, log in, sign up, forgot password, feed (`application/rss+xml`) and `sitemap.xml`. Keyboard walk on a post: the skip link is the first stop, moves focus to `main`, and every stop has a visible 3px outline.

**Flows**, all done through the UI:

| Flow | Result |
| --- | --- |
| Post with paragraph, heading, list, quote, uploaded image, separator, published with terms | Works. Public page shows every block, image with srcset. |
| Scheduled post with the date picker (keyboard arrows in the grid, time spinbuttons) | Works. |
| Page with a parent (searchable select, typed search, Enter) | Works, `/contact/opening-hours` answers 200. |
| Menu edit: add a page, nest it, reorder, save | Works, footer menu shows the nested item. |
| Moderate a comment from the row menu | Works, toast in a polite live region. |
| Invite a user (duplicate email error, then success) | Works. |
| Buy a product as a guest with bank transfer (390px) | Works, order ORD-00001 and thank-you page. Server errors are shown inline with `aria-invalid` and `aria-describedby`. |
| Newsletter signup block, subscribe as a guest, confirm with the emailed link | Works (queue run with `queue:work --stop-when-empty`). |
| Plan, member with an end date, entry restricted to the plan | Guest sees the teaser, the member sees the full post. |
| Upload a plugin zip, review, install and enable, then remove | Works. A bad provider class is rolled back with a clear message. |
| Admin keyboard: skip link, mobile nav sheet, Cmd+K | Works after the fixes below. |

## What was fixed

Each fix is its own commit with tests where possible.

| Fix | WCAG / impact | Tests |
| --- | --- | --- |
| Muted text token was 4.42:1 on muted backgrounds (table headers, avatars). Light destructive text was 4.33:1 on muted. | 1.4.3 | `tests/ui/color-contrast.test.ts` checks token pairs in both themes |
| Delete actions used `text-destructive`, which is a dark red background color in dark mode (1.65:1). They now use the destructive text token, lightened for dark mode. | 1.4.3 | contrast test |
| Block editor placeholders, labels and handles used fixed zinc colors (2.6:1 in light, 4.1:1 in dark). They now use the muted token. | 1.4.3, 1.4.11 | contrast test |
| Inactive appearance tabs were 4.35:1. | 1.4.3 | |
| Auth pages had no `main` landmark, and the logo link was named after the page title. | 1.3.1, 2.4.4 | `tests/auth/auth-layout.test.tsx` |
| Log in and sign up used positive `tabindex`. The show password button could not be reached with the keyboard. | 2.4.3, 2.1.1 | auth layout test |
| The admin had no skip link before about 25 nav links. | 2.4.1 | `tests/shell/admin-shell.test.tsx` |
| Closing the mobile nav sheet or the command menu dropped focus on `body`. A `useReturnFocus` hook sends it back to the button that opened it. | 2.4.3 | hook and shell tests |
| Every table row menu was called "Row actions". `DataTable` takes a `rowLabel`, so menus read "Actions for Market Day in Braga". Wired on every admin table. | 2.4.6, 4.1.2 | `tests/ui/data-table.test.tsx` |
| Field errors were not linked to their inputs. `InputError` now marks its field `aria-invalid` and adds itself to `aria-describedby`, for all 84 uses. | 3.3.1, 1.3.1 | `tests/ui/input-error.test.tsx` |
| Menu move up and down buttons were enabled when they could do nothing (first and last item). | usability | `tests/site/menu-tree.test.ts` (first tests for the tree helpers) |
| After "Create", the entry and campaign editors kept the empty create form: a published entry showed "Publish date: Not set" until reload. The form is now keyed by the record id. | bug | `tests/content/entry-edit.test.tsx` (fails without the fix) |
| Membership "Ends on" used a native date input. It now uses the shared `DatePicker`. | design system | |
| The AI permission showed as "Ai.use" on the plugin and review pages. `Capability::label()` and `Capability::labels()` give readable names in one place. | readability | `tests/Unit/Core/CapabilityTest.php`, plugins feature test |

## What remains

- The "unlabeled select" hits in the automated scan are Radix hidden native selects (`aria-hidden`, `tabindex=-1`). The visible comboboxes have names. Not a problem.
- The admin sidebar is wrapped in `aside` (a complementary landmark) and the nav group titles are paragraphs, not labels of their lists. Low priority.
- Fixed on feat/pending: the plugin detail page removes a plugin with the design system dialog (the list page already did).
- The extensions upload keeps "Check package" disabled until a file is chosen. Validation on submit would be friendlier.
- The product inventory page puts the status badge inside the `h1`, so its name reads "Handmade Ceramic Coffee Mug published".
- Fixed on feat/pending: the newsletter signup title is an h2 by default and the editor can pick h3 or h4.
- Fixed on feat/pending: after an Inertia visit to another path, focus moves to the page heading; visits that only change the query (live search, filters) and open dialogs keep their focus.
- The checkout country field accepts any two letters (for example `ZZ`). A country list would be safer.
- Not checked: screen readers (VoiceOver, NVDA), 200% text zoom, Windows forced colors, the Stripe payment path, the AI assistant against the real API, and the WordPress importer with a real WXR file.
- Local setup note: a fresh worktree needs `php artisan storage:link` (already in the README), or uploaded images answer 403.

# Easy mode journey (November 2026)

`tests/e2e/easy-journey.spec.ts` (Playwright, `npm run test:e2e`) plays a bakery owner from a fresh install to a finished site using only Easy mode, at 1440x900 (mouse) and 390x844 (touch, phone). Each size gets its own throwaway install (`scripts/e2e/serve.mjs`). The steps: web install wizard (SQLite, "Harbor Lane Bakery", no demo content), guided start ("Restaurant or cafe"), "Make it yours" (logo upload, "Soft" palette from the logo, "Studio" fonts, "Elegant" style, the preview follows), launch checklist, "Edit page" from the admin bar, inline heading edit, picture swap through the media library, "Questions and answers" from the section gallery, move up, hide a section, undo a duplicate, save, public page check, "Your site details", "Create a privacy page", "Go live", checklist complete. Every Easy screen is checked for sideways scroll, and axe-core (WCAG 2.2 AA) runs on the guided start, "Make it yours", the dashboard, the page editor, the section gallery and "Your site details".

## Timings

Machine time of each step (Apple silicon, PHP built-in server with 4 workers), three runs in a row, both sizes in parallel:

| Step | 1440 | 390 |
| --- | --- | --- |
| Install with the web wizard | 1.8s | 1.8s |
| Guided start: build the restaurant site | 5.3s | 5.3s |
| Make it yours | 1.6s | 1.6s |
| Page editor: heading, picture, new section, move, hide, undo, save | 4.4s | 4.1s |
| Your site details | 0.7s | 0.4s |
| Privacy page and go live | 0.7s | 2.3s |
| Whole journey | 16 to 20s | 17 to 21s |

A person needs about 55 actions (clicks, taps, key presses and filled fields) for the whole journey, 13 of them in the install wizard and 25 in the page editor and the trips to and from it.

## Usability notes

Found and fixed while writing the journey (each with a Pest or Vitest test):

- **Phones could not start editing a text with one tap.** Focusing a text showed the section toolbar above it, the text moved down before the finger was lifted and the tap landed on the section. The phone toolbar now shows for a tapped (selected) section or keyboard focus only.
- **Phones lost the section toolbar after "Add section" or "Move up".** The new or moved section had focus but was not selected, so the first tap on its toolbar hid the toolbar. A section that gets focus after an action is now selected.
- **Phones showed no save state.** "All changes saved" was hidden below 640px and "Save changes" lives in "More actions". The save state now shows under the top bar on phones.
- **The page editor drew the page with the fallback theme's header and footer**, not the active theme, so the editor looked different from the real site. The frame now uses the active theme views.
- **A picked font pair was replaced when a style was picked after it.** The page lists fonts above styles, so people who work top to bottom lost their font choice without a word. A style now only brings its fonts while the person has not picked fonts on the page.
- **A section kept its old name after its heading was edited**, so screen readers announced the old title. Sections named after their heading now follow the new heading text.
- **The privacy page was not linked from anywhere.** "Create a privacy page" now adds it to the footer menu (`verso.easy.privacy.menu_location`).
- **The install server check scrolled sideways on phones** with long folder paths. Paths now wrap.

Still open (ideas, not done):

- Building a starter site is the slowest step (about 5 seconds, most of it painting the sample pictures). The button says "Building your site", but a short list of what is happening would make the wait feel shorter.
- After "Create a privacy page" the page editor opens. Getting back to the checklist takes two steps ("Back to site", then "Dashboard" in the admin bar). A "Back to the checklist" link when the editor was opened from the checklist would save a step.
- On phones a section's tools only show after tapping the section. Nothing tells people that; a one time hint ("Tap a section to move, hide or delete it") would help.
- On phones the page editor says "Computer, full width" above the page, which reads as wrong on a phone. "Full width" alone would be clearer.
- In the page editor frame on phones the theme menu does not fold into its "Menu" button (theme scripts do not run in the frame), so the last menu items run off the edge.
- The starter home page shows the page title "Home" as a big heading above the welcome banner. Hiding the title of a front page made of sections would look more finished.
- "Your site details" says social links "can show in the footer", but they only show where a social links section is added. The text, or a footer default, should match what happens.

# Hallmark audit (November 2026)

An audit of the admin and of the public theme Ashgrove with the Hallmark skill in audit mode, focused on hover states, pointers, focus, accessibility (WCAG 2.2 AA) and UX feedback. Branch `feat/hallmark`. The code was read in full (admin primitives and `components/verso`, 89 admin pages, Ashgrove views and CSS, the block CSS in `resources/blocks`, the plugin public views), then checked in Chromium with Playwright on a seeded site.

Pre-flight: Figtree, OKLCH tokens in `resources/css/app.css`, no motion library (motion is CSS only), Tailwind 4 spacing, Laravel 13 + Inertia 3 + React 19. Ashgrove: Newsreader and Instrument Sans, hex tokens with `light-dark()`. Hallmark kept every existing token and pattern; no redesign.

## Findings

| Area | Category | Found | Fixed |
| --- | --- | --- | --- |
| Admin | Buttons, tabs, menu items, options, switches, checkboxes, `summary` and `label[for]` show the arrow, not a pointer (Tailwind 4 resets `button` to `cursor: default`) | every control | all |
| Admin | Disabled controls hide the "not allowed" cursor with `pointer-events: none` (button, toggle, input, select, dropdown and select items, dialog and sheet close, date picker, label) | 11 primitives | 11 |
| Admin | Interactive elements without a hover state | 24 | 22 |
| Admin | Controls without their own `:focus-visible` style, falling back to a 50% ring outline | 29 | 29 (one global rule) |
| Admin | Outline removed with no replacement (search inputs in Cmd+K and pickers, the title field, the 2FA secret field, the menu drag handle) | 7 | 0 (see open items) |
| Admin | Targets under 24px (16px checkboxes, a shortcode chip) | 7 | 6 |
| Admin | Width and progress transitions without a reduced motion guard | 6 | 6 |
| Admin | Destructive actions without a confirmation or undo | 16 | 7 |
| Admin | Actions without a busy state (double submit possible) | 13 | 6 |
| Admin | Untranslated text: capability section titles ("All content types", "Shop") | 2 | 2 |
| Admin | Icon-only buttons without a name, physical direction classes, missing empty states, fixed width page wrappers | 0 | |
| Ashgrove | Shop and forms plugins read `--text`, `--bg` and `--border`, which Ashgrove did not define: invisible shop buttons, no table borders, white form fields and a 1.07:1 submit button in dark mode | 2 plugins | 2 |
| Ashgrove | Form errors drawn in the green accent instead of the error color | 4 rules | 4 |
| Ashgrove | No `dir` on `<html>`, so right to left site languages were laid out left to right | 2 layouts | 2 |
| Ashgrove | Links without a name (gallery lightbox links on pictures without alt text, icon links without a label) | 2 | 2 |
| Ashgrove | Comment form errors not linked to their fields | 4 fields | 4 |
| Ashgrove | Controls without a hover state (submenu button, nav toggle and more, search button, dark mode switch, social and link column links, block buttons: disclosure, tabs, mega menu, mobile menu, modal, popups, slider, lightbox, video pause, forms submit, shop sort and link buttons) | 21 | 21 |
| Ashgrove | Inconsistent hover: the four column footer menu only underlined, the link list next to it also changed color | 1 | 1 |
| Ashgrove | No base cursor rule (plain `summary`, form choice labels); disabled slider buttons used the arrow | 3 | 3 |
| Ashgrove | Targets under 24px (category links 22px, reply and cancel links, pagination, "more" nav button, shop "Remove") | 6 | 6 |
| Ashgrove | Physical properties that break right to left (submenu, table of contents and quote padding, share tooltip, mobile drawer slide, dialog header, shop table and notice) | 9 | 9 |
| Ashgrove | Skip link, landmarks, nav labels, `aria-current`, focus ring, reduced motion guards, theme contrast (muted 6.2:1 light, 8.0:1 dark) | pass | |

Totals: 58 admin findings (51 fixed), 52 Ashgrove findings (52 fixed). By Hallmark severity: 4 critical (invisible shop buttons and dark form fields, missing `dir`, nameless links, campaign send could run twice), 31 major, the rest minor. No structural slop: the admin and Ashgrove already follow their own design systems and none of the anti-AI-template tells (centered hero with three cards, gradient blobs, italic display) apply.

## What was fixed

| Fix | Where | Tests |
| --- | --- | --- |
| Global cursor rule: pointer on `button`, `[role=button|tab|menuitem*|option|switch|checkbox|radio|combobox]`, `summary`, `label[for]`, `select`, checkbox, radio, color, file and range inputs and the file button; "not allowed" on `:disabled`, `[aria-disabled=true]` and `[data-disabled]`. Wrapped in `:where()` so components still win. | `resources/css/app.css` | Playwright (below) |
| Disabled primitives keep pointer events, so the "not allowed" cursor shows; hovers only apply when enabled (`not-disabled:hover:`). | button, toggle, input, select, dropdown menu, dialog, sheet, label, date picker | |
| Global keyboard focus ring (2px `--ring`, offset 2px) for links, buttons, `summary`, tabs, options and focusable regions that do not draw their own. | `resources/css/app.css` | Playwright keyboard walk |
| Checkbox: 24px hit area (`after:-inset-1`) and a hover border. Select trigger: a light mode hover. User menu, Visual/Code switch, add panel sources, outline rows, focal point, color swatches, media picker tiles, segmented labels, breakpoint picker, setting choices, install plugin cards, auth logo, submission rows and "used in" links: hover states. | ui and verso components | |
| Reduced motion: import and update progress bars, recovery codes reveal, sort icon rotation, pulse placeholders. | | |
| Confirmations before: removing the AI API key, unsubscribing a newsletter subscriber, resetting a system setting, removing the profile photo, deleting a style preset, turning off 2FA, making new recovery codes. Same `window.confirm` pattern as the rest of the admin. | | `system/pages`, `users/public-profile-card` |
| Busy states: sending a campaign (no double send), saving and cloning a role, activating a theme and clearing the page cache. | | `newsletter/campaign-send`, `theme/themes-index` |
| Capability section titles are extracted and translated (`section:` is now a label key for `verso:lang-extract`). | `StringExtractor` | `LangFilesTest` |
| Ashgrove exposes `--text`, `--bg` and `--border` for plugin styles; forms errors use `--error`. | `theme.css`, forms `assets.blade.php` | Playwright dark mode |
| Public `<html dir>` follows the site language (`Locales::directionOf`), in Ashgrove and the default theme. | `ThemeComposer` | `Unit/Theme/SupportTest` |
| Gallery lightbox links on pictures without alt text are named "Open picture 2"; icon links without a label are named after the site they open ("instagram.com"). | `GalleryBlock`, `IconBlock` | `Feature/Blocks/BuilderBlocksTest` |
| Comment form errors set `aria-invalid` and `aria-describedby`. | `comments.blade.php` | `Feature/Comments/CommentsComponentTest` |
| Ashgrove base cursor rule, hover states, 24px targets and logical properties listed above; block CSS hovers and right to left fixes. | `theme.css`, `resources/blocks/*`, `shop.css` | Playwright |

New visible strings (8 core, 1 AI Assistant, 1 Newsletter) are translated into all 15 languages.

## Browser check

- Admin, 8 pages (dashboard, posts, roles, themes, menus, system settings, media, profile) at 3440, 1440 and 390 px, light and dark: 48 renders, every visible control has `pointer` (or `not-allowed` when disabled, `grab` on drag handles), no horizontal scroll, `main` is 3200 px wide at 3440.
- Keyboard walk on the posts list: every stop shows a 2px outline or a 3px ring, including the brand link that had none.
- Arabic admin (5 pages at 1440 and 390): `dir="rtl"`, sidebar on the right, no horizontal scroll, all pointers right.
- Ashgrove, 4 pages (home, post, search, category) at 3440, 1440 and 390, light and dark: no horizontal scroll, no control under 24px, every link and button has a pointer. With the site language set to Arabic, `<html dir="rtl">` and the header mirrors.

## What remains

- Moving entries and comments to the trash, removing a 404 log row, cancelling a membership and discarding a staged extension upload still run at once. They can all be undone (Trash tab, "Make active again", upload again), so an undo toast would be nicer than a confirm. Local removals in editors (menu items, form fields, product variants, logo) are unsaved until Save.
- Busy states are still missing on: SEO "Count again" and fix buttons, bulk actions on entries and comments, the inline role select on Users, and "Add to menu". The global progress bar shows that something runs.
- Search inputs in Cmd+K, the searchable select and the pattern picker have no ring of their own (`outline-none`); they are the only field in their popover, so the caret shows focus. The entry title field is the same. The menu drag handle uses its hover color as its focus style.
- The Cmd+K dialog hides its close button; Escape and a click outside close it.
- Drag handles in the block editor and outline view do nothing on Enter or Space; the block menu and outline "Move" items are the keyboard way.
- `rule` borders are the only edge of Ashgrove's menu, search and tag buttons (1.3:1). The text inside passes, so this is within WCAG, but a stronger edge would help people with low vision.
- The Ashgrove accent is editable in the customizer and nothing checks its contrast.
- Content images without alt text are rendered as decorative (`alt=""`). The SEO alt text report lists them.
- Public theme strings (like "Skip to content") are not translated; the public site keeps its own language and Ashgrove has no public catalog yet.
- Not checked: screen readers, 200% zoom, forced colors on the public site.

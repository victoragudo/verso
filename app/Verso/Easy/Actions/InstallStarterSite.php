<?php

namespace App\Verso\Easy\Actions;

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Starters\StarterImages;
use App\Verso\Easy\Starters\StarterInstallReport;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSite;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\Contracts\SeedsDemoContent;
use App\Verso\Plugins\PluginManager;
use App\Verso\Site\Actions\CreateMenu;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Actions\UpdateSiteSettings;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Theme\Templates\PageTemplates;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Installs a starter site: plugins, sample pictures, published pages, menus and settings.
 * Running it again (or another starter) never duplicates a page: pages made by a starter are updated, the site's own pages are kept.
 *
 * @phpstan-import-type MenuItemData from SyncMenuItems
 */
class InstallStarterSite
{
    // Entry meta key that marks a page made by a starter site.
    public const META_KEY = 'easy_starter';

    public function __construct(
        protected StarterSiteRegistry $starters,
        protected SectionLibrary $sections,
        protected StarterImages $images,
        protected CreateEntry $createEntry,
        protected UpdateEntry $updateEntry,
        protected CreateMenu $createMenu,
        protected SyncMenuItems $syncMenuItems,
        protected UpdateSiteSettings $siteSettings,
        protected EnablePlugin $enablePlugin,
        protected PluginManager $plugins,
        protected MenuLocations $locations,
        protected Permalinks $permalinks,
        protected PageTemplates $templates,
        protected EasyState $state,
        protected Router $router,
        protected SimpleMode $simpleMode,
    ) {}

    public function handle(User $user, string $key): StarterInstallReport
    {
        $site = $this->starters->get($key);
        [$enabled, $missing] = $this->enablePlugins($site);
        $images = $this->images->store($site, $user);

        return DB::transaction(function () use ($site, $user, $images, $enabled, $missing): StarterInstallReport {
            $created = $updated = $skipped = [];
            $entries = $kept = [];

            foreach ($site->pages as $order => $page) {
                $existing = Entry::query()->ofType($page->type)->where('slug', $page->slug)->whereNull('parent_id')->first();

                if ($existing !== null && ! $this->madeByStarter($existing)) {
                    $skipped[] = $existing->title;
                    $entries[$page->slug] = $existing;
                    $kept[$page->slug] = true;

                    continue;
                }

                $entries[$page->slug] = $this->savePage($user, $site, $page, $order, $images, $existing);
                $existing === null ? $created[] = $page->title : $updated[] = $page->title;
            }

            $this->saveMenu($site, 'primary', $site->primaryMenu, $entries);
            $this->saveMenu($site, 'footer', $site->footerMenu, $entries);
            $frontPageId = $this->saveSettings($site, $entries, $kept);
            $this->state->rememberStarter($site->key);
            $this->simpleMode->rememberNewSiteDefault();

            return new StarterInstallReport($site->key, $this->starters->label($site), $created, $updated, $skipped, $enabled, $missing, $frontPageId);
        });
    }

    /**
     * The blocks of a starter page: every section with known content, built with one kit so ids stay unique.
     *
     * @param  array<string, array<string, mixed>>  $images  image attrs by picture name
     * @return list<array<string, mixed>>
     */
    public function blocks(StarterPage $page, array $images = []): array
    {
        $kit = new SectionKit($this->plugins);
        $blocks = [];

        foreach ($page->sections as $section) {
            $template = $this->sections->find($section->key);

            if ($template === null || ! $this->sections->isAvailable($template)) {
                continue;
            }

            $blocks = [...$blocks, ...$template->build($this->withImages($section->content, $images), $kit)];
        }

        return $blocks;
    }

    /**
     * Turns on the plugins of the starter that are installed. Plugins turned on now add their demo content (like the contact form).
     *
     * @return array{0: list<string>, 1: list<string>} enabled now, missing
     */
    protected function enablePlugins(StarterSite $site): array
    {
        $enabled = $missing = [];

        foreach ($site->plugins as $slug) {
            $plugin = $this->plugins->find($slug);

            if ($plugin === null || ! $plugin->canBeEnabled()) {
                $missing[] = $slug;

                continue;
            }

            $wasEnabled = $this->plugins->isEnabled($slug);

            try {
                $this->enablePlugin->handle($slug);
                $provider = $this->plugins->register($this->plugins->get($slug));

                if (! $wasEnabled && $provider instanceof SeedsDemoContent) {
                    $provider->seedDemoContent();
                }
            } catch (Throwable $exception) {
                Log::warning("The starter site could not turn on the plugin \"{$slug}\".", ['exception' => $exception]);
                $missing[] = $slug;

                continue;
            }

            if (! $wasEnabled) {
                $enabled[] = $slug;
            }
        }

        if ($enabled !== []) {
            $this->router->getRoutes()->refreshNameLookups();
            $this->router->getRoutes()->refreshActionLookups();
        }

        return [$enabled, $missing];
    }

    /**
     * @param  array<string, array<string, mixed>>  $images
     */
    protected function savePage(User $user, StarterSite $site, StarterPage $page, int $order, array $images, ?Entry $existing): Entry
    {
        $template = $page->type === 'page' ? $this->wideTemplate() : null;
        $meta = [...($existing->meta ?? []), self::META_KEY => $site->key, ...($template !== null ? ['template' => $template] : [])];
        $data = [
            'title' => $page->title,
            'blocks' => $this->blocks($page, $images),
            'excerpt' => $page->excerpt !== '' ? $page->excerpt : null,
            'status' => 'published',
            'menu_order' => $order,
            'meta' => $meta,
        ];

        if ($existing !== null) {
            return $this->updateEntry->handle($existing, $data, $user);
        }

        return $this->createEntry->handle($user, $page->type, [...$data, 'slug' => $page->slug]);
    }

    // True when a starter site made this entry, so a new starter may replace its content.
    protected function madeByStarter(Entry $entry): bool
    {
        return is_string($entry->meta[self::META_KEY] ?? null);
    }

    // The first page template of the active theme that uses the full width, or null.
    protected function wideTemplate(): ?string
    {
        foreach ((array) config('verso.easy.wide_templates') as $name) {
            if (is_string($name) && $this->templates->find('page', $name) !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Fills a menu named after the starter and shows it in the location. Locations the theme lacks are skipped.
     *
     * @param  list<StarterMenuItem>  $items
     * @param  array<string, Entry>  $entries  starter entries by slug
     */
    protected function saveMenu(StarterSite $site, string $location, array $items, array $entries): void
    {
        if ($items === [] || ! $this->locations->has($location)) {
            return;
        }

        $name = $site->label.' '.($location === 'primary' ? 'main menu' : $location.' menu');
        $menu = Menu::query()->where('name', $name)->first() ?? $this->createMenu->handle($name);

        $this->syncMenuItems->handle($menu, $this->menuItems($items, $entries));
        $menu->assignLocation($location);
    }

    /**
     * Menu item data for SyncMenuItems. Items that point to something missing are left out.
     *
     * @param  list<StarterMenuItem>  $items
     * @param  array<string, Entry>  $entries
     * @return list<MenuItemData>
     */
    protected function menuItems(array $items, array $entries): array
    {
        $data = [];

        foreach ($items as $item) {
            $children = $this->menuItems($item->children, $entries);
            $url = match (true) {
                $item->archive !== null => $this->permalinks->archivePath($item->archive),
                $item->route !== null => $this->router->has($item->route) ? route($item->route, absolute: false) : null,
                default => $item->url,
            };

            if ($item->page !== null && isset($entries[$item->page])) {
                $data[] = ['type' => 'entry', 'entry_id' => (int) $entries[$item->page]->id, 'label' => $item->label, 'children' => $children];
            } elseif ($item->page === null && $url !== null && $url !== '') {
                $data[] = ['type' => 'custom', 'label' => $item->label, 'url' => $url, 'children' => $children];
            }
        }

        return $data;
    }

    /**
     * Saves the tagline and the home page. Returns the id of the new home page, or null when the posts stay there.
     *
     * @param  array<string, Entry>  $entries
     * @param  array<string, true>  $kept  slugs of the site's own pages that were left alone
     */
    protected function saveSettings(StarterSite $site, array $entries, array $kept): ?int
    {
        $front = $site->frontPage();
        $frontEntry = $front !== null ? ($entries[$front->slug] ?? null) : null;
        $values = $site->settings;

        if ($front === null) {
            $values['reading.front_page'] = null;
        } elseif ($frontEntry !== null && ! isset($kept[$front->slug])) {
            $values['reading.front_page'] = (int) $frontEntry->id;
        }

        $this->siteSettings->handle($values);

        $saved = $values['reading.front_page'] ?? null;

        return is_int($saved) ? $saved : null;
    }

    /**
     * Content with picture names replaced by image attrs. Unknown names are dropped.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, array<string, mixed>>  $images
     * @return array<string, mixed>
     */
    protected function withImages(array $content, array $images): array
    {
        $resolve = fn (mixed $image): ?array => is_string($image) ? ($images[$image] ?? null) : (is_array($image) ? $image : null);

        if (is_array($content['images'] ?? null)) {
            $content['images'] = array_values(array_filter(array_map($resolve, $content['images'])));
        }

        if (is_array($content['items'] ?? null)) {
            $content['items'] = array_map(function (mixed $item) use ($resolve): mixed {
                if (is_array($item) && array_key_exists('image', $item)) {
                    $item['image'] = $resolve($item['image']);
                }

                return $item;
            }, $content['items']);
        }

        return $content;
    }
}

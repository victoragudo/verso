<?php

namespace App\Verso\Easy;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardNotice;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Easy\Blocks\MapBlock;
use App\Verso\Easy\Help\CoreHelpTopics;
use App\Verso\Easy\Help\HelpTopics;
use App\Verso\Easy\Launch\CoreLaunchChecks;
use App\Verso\Easy\Launch\LaunchChecklist;
use App\Verso\Easy\Launch\LaunchChecklistCard;
use App\Verso\Easy\Sections\CoreSections;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Sections\SectionPictures;
use App\Verso\Easy\Simple\CoreSimpleNav;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Simple\SimpleModeNav;
use App\Verso\Easy\Starters\Core\CoreStarterSites;
use App\Verso\Easy\Starters\StarterImages;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Styles\Core\CoreFontPairs;
use App\Verso\Easy\Styles\Core\CoreStylePresets;
use App\Verso\Easy\Styles\FontPairRegistry;
use App\Verso\Easy\Styles\StylePresetRegistry;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\AdminNavFilter;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

// Boots Easy mode: ready sections, starter sites, style presets, font pairs, the map block, the guided start, "Make it yours",
// the simple admin mode, the launch checklist and the help panel.
class EasyServiceProvider extends ServiceProvider
{
    // Stands for the entry id in links the page list fills in for each row.
    public const ENTRY_PLACEHOLDER = '__entry__';

    public function register(): void
    {
        $this->app->singleton(SectionLibrary::class, function (Application $app): SectionLibrary {
            $library = new SectionLibrary($app->make(PluginManager::class));
            (new CoreSections)->register($library);

            return $library;
        });
        $this->app->singleton(StarterSiteRegistry::class, function (): StarterSiteRegistry {
            $registry = new StarterSiteRegistry;
            (new CoreStarterSites)->register($registry);

            return $registry;
        });
        $this->app->singleton(StylePresetRegistry::class, function (): StylePresetRegistry {
            $registry = new StylePresetRegistry;
            (new CoreStylePresets)->register($registry);

            return $registry;
        });
        $this->app->singleton(FontPairRegistry::class, function (): FontPairRegistry {
            $registry = new FontPairRegistry;
            (new CoreFontPairs)->register($registry);

            return $registry;
        });
        $this->app->singleton(SimpleModeNav::class, function (): SimpleModeNav {
            $nav = new SimpleModeNav;
            (new CoreSimpleNav)->register($nav);

            return $nav;
        });
        $this->app->singleton(HelpTopics::class, function (): HelpTopics {
            $topics = new HelpTopics;
            (new CoreHelpTopics)->register($topics);

            return $topics;
        });
        // Core steps need routes, so they are added when the checklist is first used.
        $this->app->singleton(LaunchChecklist::class, function (Application $app): LaunchChecklist {
            $checklist = new LaunchChecklist;
            $app->make(CoreLaunchChecks::class)->register($checklist);

            return $checklist;
        });
        $this->app->bind(SectionKit::class, fn (Application $app): SectionKit => new SectionKit($app->make(PluginManager::class)));
        $this->app->bind(SectionPictures::class, fn (): SectionPictures => new SectionPictures((int) config('verso.easy.section_pictures')));
        $this->app->bind(StarterImages::class, fn (): StarterImages => new StarterImages(
            width: (int) config('verso.easy.image_width'),
            height: (int) config('verso.easy.image_height'),
            quality: (int) config('verso.easy.image_quality'),
            portraitSize: (int) config('verso.easy.portrait_size'),
            disk: (string) config('verso.media.disk'),
            folder: (string) config('verso.easy.media_folder'),
        ));
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/easy.php'));

        $map = $this->app->make(MapBlock::class);
        $this->app->make(PluginBlockRegistry::class)->register($map);
        $this->app->make(BlockRegistry::class)->register(new PluginBlockAdapter($map));

        $this->bootSimpleMode();
        $this->bootAdminSlots();

        // The ready sections (testimonials, prices, team, FAQ, call to action...) are also offered in the builder Add panel.
        EditorManifest::extend(fn (?User $user): array => $user?->can('create', Entry::class) ? [
            'sections' => array_map(fn (array $section): array => array_intersect_key($section, array_flip(['key', 'label', 'description', 'category', 'keywords'])), $this->app->make(SectionLibrary::class)->toArray()),
            'sectionUrl' => route('admin.easy.sections.build', ['key' => 'section-key']),
        ] : []);

        $this->app->booted(function (): void {
            AdminNav::add(new NavItem(
                key: 'easy-details',
                label: 'Your site details',
                route: 'admin.easy.details',
                icon: 'site',
                order: 5,
                capability: Capability::ManageSettings,
                group: NavGroup::Settings,
                keywords: ['contact', 'email', 'phone', 'address', 'social', 'instagram', 'description'],
            ));
            AdminNav::add(new NavItem(
                key: 'easy-start',
                label: 'Guided start',
                route: 'admin.easy.start',
                icon: 'starter',
                order: 5,
                capability: Capability::ManageSettings,
                group: NavGroup::Design,
                keywords: ['starter site', 'ready made site', 'template', 'wizard', 'setup'],
            ));
            AdminNav::add(new NavItem(
                key: 'easy-style',
                label: 'Make it yours',
                route: 'admin.easy.style',
                icon: 'palette',
                order: 6,
                capability: Capability::ManageThemes,
                group: NavGroup::Design,
                keywords: ['logo', 'colors', 'colours', 'palette', 'fonts', 'style', 'brand', 'look'],
            ));

            $this->app->make(Dashboard::class)->addNotice(new DashboardNotice(
                key: 'easy-start',
                resolve: fn (User $user): array => $this->startNotice(),
                capability: Capability::ManageSettings,
                order: 20,
                icon: 'starter',
            ));

            $this->app->make(Dashboard::class)->addNotice(new DashboardNotice(
                key: 'easy-style',
                resolve: fn (User $user): ?array => $this->styleNotice(),
                capability: Capability::ManageThemes,
                order: 21,
                icon: 'palette',
            ));
        });
    }

    // Simple mode: fewer sidebar items with plain names, and the shared props the admin UI reads.
    protected function bootSimpleMode(): void
    {
        $this->app->make(FilterRegistry::class)->add(AdminNavFilter::class, function (array $items, ?User $user): array {
            return $this->app->make(SimpleMode::class)->isOn($user) ? $this->app->make(SimpleModeNav::class)->filter($items) : $items;
        }, priority: 1000);

        Inertia::share([
            // Null for people who cannot write or build the site: they get no switch and no help panel.
            'simpleMode' => fn (): ?bool => request()->user()?->hasCapability(Capability::EditOwnEntries)
                ? $this->app->make(SimpleMode::class)->isOn(request()->user())
                : null,
            'routeName' => fn (): ?string => request()->route()?->getName(),
        ]);
    }

    // The launch checklist on the dashboard, the simple mode switch on the profile page and "Edit on the page" in the page list.
    protected function bootAdminSlots(): void
    {
        $slots = $this->app->make(AdminSlotRegistry::class);

        $slots->add(new SlotItem(
            slot: AdminSlot::DashboardTop,
            key: 'easy.launch-checklist',
            component: 'easy.launch-checklist',
            capability: Capability::ManageSettings,
            order: 10,
            props: fn (Request $request): ?array => $request->user() instanceof User
                ? $this->app->make(LaunchChecklistCard::class)->props($request->user())
                : null,
        ));
        $slots->add(new SlotItem(
            slot: AdminSlot::ProfileSections,
            key: 'easy.simple-mode',
            component: 'easy.simple-mode',
            capability: Capability::EditOwnEntries,
            order: 10,
            props: fn (Request $request): array => [
                'enabled' => $this->app->make(SimpleMode::class)->isOn($request->user()),
                'siteDefault' => $this->app->make(SimpleMode::class)->siteDefault(),
            ],
        ));
        $slots->add(new SlotItem(
            slot: AdminSlot::EntriesRowActions,
            key: 'easy.edit-on-page',
            component: 'easy.edit-on-page',
            label: 'Edit on the page',
            order: 5,
            props: fn (Request $request): ?array => $this->isPageList($request) && $this->app->make(SimpleMode::class)->isOn($request->user())
                ? ['href' => route('admin.easy.pages.edit', ['entry' => self::ENTRY_PLACEHOLDER], false)]
                : null,
        ));
    }

    // True on the list of pages (the route parameter is a ContentType once bound).
    protected function isPageList(Request $request): bool
    {
        $type = $request->route('contentType');

        return ($type instanceof ContentType ? $type->name : $type) === 'page';
    }

    /**
     * The dashboard notice: an invitation before a starter site is chosen, a quiet link after.
     *
     * @return array{title: string, description: string|null, href: string|null, action: string|null, tone: string}
     */
    protected function startNotice(): array
    {
        $href = route('admin.easy.start', absolute: false);

        if ($this->app->make(EasyState::class)->hasStarter()) {
            return [
                'title' => __('Want to try another ready made site?'),
                'description' => __('Pick a different kind of site any time. Your own pages stay.'),
                'href' => $href,
                'action' => __('Open the guided start'),
                'tone' => 'neutral',
            ];
        }

        return [
            'title' => __('Start with a ready made site'),
            'description' => __('Tell us what your site is for and get pages, menus and sample text in one click.'),
            'href' => $href,
            'action' => __('Choose a site'),
            'tone' => 'info',
        ];
    }

    /**
     * The "Make it yours" notice: shown after a starter site is chosen, until the style is saved once.
     *
     * @return array{title: string, description: string, href: string, action: string, tone: string}|null
     */
    protected function styleNotice(): ?array
    {
        $state = $this->app->make(EasyState::class);

        if (! $state->hasStarter() || $state->hasStyled()) {
            return null;
        }

        return [
            'title' => __('Make it yours'),
            'description' => __('Add your logo, take colors from it and pick fonts. You see the site change as you go.'),
            'href' => route('admin.easy.style', absolute: false),
            'action' => __('Choose colors and fonts'),
            'tone' => 'info',
        ];
    }
}

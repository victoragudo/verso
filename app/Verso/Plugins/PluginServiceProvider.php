<?php

namespace App\Verso\Plugins;

use App\Verso\Content\ContentAdminNav;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Easy\Help\HelpTopic;
use App\Verso\Easy\Help\HelpTopics;
use App\Verso\Easy\Launch\LaunchCheck;
use App\Verso\Easy\Launch\LaunchChecklist;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Sections\SectionTemplate;
use App\Verso\Easy\Simple\SimpleModeNav;
use App\Verso\Easy\Starters\StarterSite;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\FontPairRegistry;
use App\Verso\Easy\Styles\StylePreset;
use App\Verso\Easy\Styles\StylePresetRegistry;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Plugins\Admin\PluginNavPlacement;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\EntryPanel;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use App\Verso\Plugins\Filters\PublicRoutesFilter;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use App\Verso\Plugins\Support\PluginConfig;
use App\Verso\Plugins\Support\PluginMigrator;
use App\Verso\System\Config\ConfigGroup;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\Theme\ThemeManager;
use Closure;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LogicException;

// Base class for the service provider of every plugin. It adds small helpers for common tasks.
abstract class PluginServiceProvider extends ServiceProvider
{
    protected ?Plugin $plugin = null;

    // Called by the PluginManager before the provider is registered.
    public function setPlugin(Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function plugin(): Plugin
    {
        return $this->plugin ?? throw new LogicException('This plugin provider was not registered by the PluginManager.');
    }

    public function slug(): string
    {
        return $this->plugin()->slug;
    }

    // An absolute path inside the plugin folder.
    public function path(string $relative = ''): string
    {
        $base = $this->plugin()->path;

        return $relative === '' ? $base : $base.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    // Unlike Laravel's version, this also fills the key when the config is cached, so plugins enabled after "config:cache" still get their config.
    protected function mergeConfigFrom($path, $key): void
    {
        $this->app->make(PluginConfig::class)->merge((string) $path, (string) $key);
    }

    // The System settings group of this plugin. Pass it as the group of each ConfigParameter.
    protected function systemGroup(): string
    {
        return 'plugin-'.$this->slug();
    }

    // Adds settings of this plugin to the System settings area, in its own group. Saved values are put on config() at boot.
    protected function registerSystemSettings(string $label, string $icon, ConfigParameter ...$parameters): void
    {
        $registry = $this->app->make(ConfigRegistry::class);
        $registry->addGroup(new ConfigGroup(
            key: $this->systemGroup(),
            label: $label,
            description: 'Settings of the :plugin plugin.',
            icon: $icon,
            order: 200,
            plugin: $this->slug(),
            descriptionReplace: ['plugin' => $label],
        ));
        $registry->add(...array_values(array_filter($parameters, fn (ConfigParameter $parameter): bool => $registry->find($parameter->key) === null)));
    }

    // Adds the block to the plugin list and to the core registry, so it can be saved and rendered.
    protected function registerBlock(PluginBlock $block): void
    {
        $this->app->make(PluginBlockRegistry::class)->register($block, $this->plugin->slug ?? '');
        $this->app->make(BlockRegistry::class)->register(new PluginBlockAdapter($block));
    }

    // Adds a ready section to the Easy mode section gallery. Set its plugin, so it only shows while this plugin is on.
    protected function registerSection(SectionTemplate $section): void
    {
        $this->app->make(SectionLibrary::class)->register($section, $this->slug());
    }

    // Adds a starter site to the guided start ("What is your site for?").
    protected function registerStarterSite(StarterSite $site): void
    {
        $this->app->make(StarterSiteRegistry::class)->register($site, $this->slug());
    }

    // Adds a style preset to "Make it yours", like "Retro". Themes only get the tokens they declare.
    protected function registerStylePreset(StylePreset $preset): void
    {
        $this->app->make(StylePresetRegistry::class)->register($preset, $this->slug());
    }

    // Adds a font pair to "Make it yours". It shows only on themes that accept both fonts.
    protected function registerFontPair(FontPair $pair): void
    {
        $this->app->make(FontPairRegistry::class)->register($pair, $this->slug());
    }

    // Adds a step to the launch checklist on the dashboard.
    protected function registerLaunchCheck(LaunchCheck $check): void
    {
        $this->app->make(LaunchChecklist::class)->register($check, $this->slug());
    }

    // Adds a topic to the help panel of the admin.
    protected function registerHelpTopic(HelpTopic $topic): void
    {
        $this->app->make(HelpTopics::class)->register($topic, $this->slug());
    }

    // Keeps these sidebar items (full keys, like "forms.messages") in the simple admin mode.
    protected function allowInSimpleMode(string ...$keys): void
    {
        $this->app->make(SimpleModeNav::class)->allow(...$keys);
    }

    // Adds a box to the entry editor side panel. Its values are saved in the entry meta.
    protected function registerEntryPanel(EntryPanel $panel): void
    {
        $this->app->make(EntryPanelRegistry::class)->register($panel);
    }

    // Adds a React component to an admin screen. The plugin registers the component in resources/js/index.tsx.
    protected function addAdminSlot(SlotItem $item): void
    {
        $this->app->make(AdminSlotRegistry::class)->add($item->withPlugin($this->slug()));
    }

    /**
     * Adds a custom field group. Arrays use the same shape as the JSON export.
     *
     * @param  FieldGroup|array<mixed>  $group
     */
    protected function registerFieldGroup(FieldGroup|array $group): void
    {
        $this->app->make(FieldGroupRegistry::class)->register($group);
    }

    // Read field group JSON files from a plugin folder (by default "fields").
    protected function loadFieldGroups(?string $relative = null): void
    {
        $this->app->make(FieldGroupRegistry::class)->addFolder($this->path($relative ?? (string) config('verso.fields.json_folder')));
    }

    /**
     * @param  class-string<Filter>  $filter
     */
    protected function addFilter(string $filter, Closure $callback, int $priority = 10): void
    {
        $this->app->make(FilterRegistry::class)->add($filter, $callback, $priority);
    }

    protected function registerContentType(ContentType $type): void
    {
        $this->app->make(ContentTypeRegistry::class)->register($type, $this->slug());
        $this->refreshContentNav();
    }

    protected function registerTaxonomy(Taxonomy $taxonomy): void
    {
        $this->app->make(TaxonomyRegistry::class)->register($taxonomy);
        $this->refreshContentNav();
    }

    // A plugin that boots late (after the app booted) still gets its content in the sidebar.
    protected function refreshContentNav(): void
    {
        if ($this->app->isBooted()) {
            $this->app->make(ContentAdminNav::class)->register();
        }
    }

    // Load Blade views from resources/views of the plugin under the given namespace.
    protected function loadPluginViews(string $namespace): void
    {
        $this->loadViewsFrom($this->path('resources/views'), $namespace);
    }

    // Nav items use route(), so they are added once the app has booted.
    // Core moves the item to the place the plugin declared in verso.json (or under Tools).
    protected function addNavItem(NavItem $item): void
    {
        $this->app->booted(fn () => AdminNav::add($this->app->make(PluginNavPlacement::class)->place($this->plugin(), $item)));
    }

    /**
     * Add public routes before the theme routes, with the public middleware. Gets the Router.
     *
     * @param  Closure(Router): void  $routes
     */
    protected function addPublicRoutes(Closure $routes): void
    {
        $this->addFilter(PublicRoutesFilter::class, function (Router $router) use ($routes): Router {
            $routes($router);

            return $router;
        });
    }

    // Load a route file of the plugin inside the admin area.
    protected function loadAdminRoutes(string $relative): void
    {
        AdminRoutes::load($this->path($relative));
    }

    // Let "php artisan migrate" find the migrations inside the plugin folder.
    protected function loadPluginMigrations(): void
    {
        $path = $this->app->make(PluginMigrator::class)->path($this->plugin());

        if ($path !== null) {
            $this->loadMigrationsFrom($path);
        }
    }

    // Add a plugin folder of public views to the "theme" namespace. Themes can override them.
    protected function loadThemeViews(string $relative = 'resources/views/theme'): void
    {
        $this->app->make(ThemeManager::class)->addPluginViews($this->path($relative));
    }

    /**
     * @param  class-string|array{class-string, string}|Closure  $listener
     */
    protected function listen(string $event, string|array|Closure $listener): void
    {
        Event::listen($event, $listener);
    }
}

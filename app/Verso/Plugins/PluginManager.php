<?php

namespace App\Verso\Plugins;

use App\Verso\Access\Capabilities\ManifestAccess;
use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\Translation\TranslationNamespaces;
use App\Verso\Plugins\Admin\PluginNavPlacement;
use App\Verso\Plugins\Exceptions\PluginCannotBeEnabled;
use App\Verso\Plugins\Exceptions\PluginNotFound;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;
use Throwable;

// Lists plugins, turns them on and off, and boots the enabled ones.
class PluginManager
{
    // Settings key that holds the slugs of enabled plugins.
    public const SETTING = 'plugins.enabled';

    /** @var array<string, Plugin>|null */
    protected ?array $discovered = null;

    /** @var array<string, true> */
    protected array $autoloaded = [];

    /** @var array<string, PluginServiceProvider> */
    protected array $booted = [];

    public function __construct(
        protected Application $app,
        protected PluginDiscovery $discovery,
        protected Settings $settings,
    ) {}

    /**
     * Every plugin on disk with its enabled flag.
     *
     * @return list<Plugin>
     */
    public function all(): array
    {
        $enabled = $this->enabledSlugs();

        return array_values(array_map(
            fn (Plugin $plugin): Plugin => $plugin->withEnabled(in_array($plugin->slug, $enabled, true)),
            $this->discovered(),
        ));
    }

    public function find(string $slug): ?Plugin
    {
        $plugin = $this->discovered()[$slug] ?? null;

        return $plugin?->withEnabled($this->isEnabled($slug));
    }

    public function get(string $slug): Plugin
    {
        return $this->find($slug) ?? throw PluginNotFound::slug($slug);
    }

    public function isEnabled(string $slug): bool
    {
        return in_array($slug, $this->enabledSlugs(), true);
    }

    /**
     * Slugs stored in the plugins.enabled setting.
     *
     * @return list<string>
     */
    public function enabledSlugs(): array
    {
        $value = $this->settings->get(self::SETTING, []);

        return array_values(array_unique(array_filter(
            is_array($value) ? $value : [],
            fn (mixed $slug): bool => is_string($slug) && $slug !== '',
        )));
    }

    // Turn a plugin on. Its provider must load and extend PluginServiceProvider.
    public function enable(string $slug): Plugin
    {
        $plugin = $this->get($slug);

        if (! $plugin->canBeEnabled()) {
            throw PluginCannotBeEnabled::because($slug, $plugin->problems());
        }

        $this->ensureProviderLoads($plugin);

        if (! $plugin->enabled) {
            $this->settings->set(self::SETTING, [...$this->enabledSlugs(), $slug]);
        }

        return $plugin->withEnabled(true);
    }

    // Turn a plugin off. The change applies from the next request.
    public function disable(string $slug): Plugin
    {
        $plugin = $this->get($slug);

        $this->settings->set(self::SETTING, array_values(array_diff($this->enabledSlugs(), [$slug])));

        return $plugin->withEnabled(false);
    }

    /**
     * Register the providers of all enabled plugins. Broken plugins are skipped and logged.
     *
     * @return list<string> the slugs that were registered
     */
    public function registerEnabled(): array
    {
        $registered = [];

        foreach ($this->enabledSlugs() as $slug) {
            $plugin = $this->find($slug);

            if ($plugin === null || ! $plugin->canBeEnabled()) {
                continue;
            }

            try {
                $this->register($plugin);
                $registered[] = $slug;
            } catch (Throwable $exception) {
                Log::warning("Verso could not boot the plugin \"{$slug}\".", ['exception' => $exception]);
            }
        }

        return $registered;
    }

    // Load the plugin classes and register its service provider once.
    public function register(Plugin $plugin): PluginServiceProvider
    {
        if (isset($this->booted[$plugin->slug])) {
            return $this->booted[$plugin->slug];
        }

        $class = $this->ensureProviderLoads($plugin);

        /** @var PluginServiceProvider $provider */
        $provider = new $class($this->app);
        $provider->setPlugin($plugin);

        // Capabilities and roles from verso.json, so the roles admin lists them and the gate knows them.
        if ($plugin->manifest !== null) {
            $this->app->make(ManifestAccess::class)->register('plugin:'.$plugin->slug, $plugin->manifest->name, $plugin->manifest->capabilities, $plugin->manifest->roles);
        }

        $this->app->register($provider);
        $this->registerMigrations($plugin);
        $this->registerTranslations($plugin);

        // The sidebar links from verso.json use route(), so they are added once the app has booted.
        $this->app->booted(fn () => $this->app->make(PluginNavPlacement::class)->register($plugin));

        return $this->booted[$plugin->slug] = $provider;
    }

    // Admin strings of the plugin, read from lang/<code>.json with the "<slug>::" prefix.
    protected function registerTranslations(Plugin $plugin): void
    {
        $this->app->make(TranslationNamespaces::class)->add(
            $plugin->slug,
            $plugin->path.DIRECTORY_SEPARATOR.trim((string) config('verso.i18n.extension_lang_path'), '/\\'),
        );
    }

    // Let "php artisan migrate" also run the migrations of an enabled plugin.
    protected function registerMigrations(Plugin $plugin): void
    {
        $path = $plugin->path.DIRECTORY_SEPARATOR.trim((string) config('verso.plugins.migrations_path'), '/\\');

        if (! is_dir($path)) {
            return;
        }

        $this->app->afterResolving('migrator', fn (Migrator $migrator) => $migrator->path($path));

        if ($this->app->resolved('migrator')) {
            $this->app->make('migrator')->path($path);
        }
    }

    /**
     * Providers registered by registerEnabled() or register().
     *
     * @return array<string, PluginServiceProvider>
     */
    public function bootedProviders(): array
    {
        return $this->booted;
    }

    // Read the plugin folders again on the next call.
    public function refresh(): void
    {
        $this->discovered = null;
    }

    // Add the PSR-4 folders from the manifest to the class loader.
    public function autoload(Plugin $plugin): void
    {
        if ($plugin->manifest === null || isset($this->autoloaded[$plugin->slug]) || $plugin->manifest->autoload === []) {
            return;
        }

        $loader = new ClassLoader;

        foreach ($plugin->manifest->autoload as $namespace => $folder) {
            $loader->addPsr4($namespace, $plugin->path.DIRECTORY_SEPARATOR.trim($folder, '/\\'));
        }

        $loader->register();
        $this->autoloaded[$plugin->slug] = true;
    }

    /**
     * @return class-string<PluginServiceProvider>
     */
    protected function ensureProviderLoads(Plugin $plugin): string
    {
        $this->autoload($plugin);

        $class = (string) $plugin->manifest?->provider;

        if (! class_exists($class)) {
            throw PluginCannotBeEnabled::because($plugin->slug, [__('The provider class :class was not found.', ['class' => $class])]);
        }

        if (! is_subclass_of($class, PluginServiceProvider::class)) {
            throw PluginCannotBeEnabled::because($plugin->slug, [__('The provider class :class must extend :parent.', ['class' => $class, 'parent' => PluginServiceProvider::class])]);
        }

        return $class;
    }

    /**
     * @return array<string, Plugin>
     */
    protected function discovered(): array
    {
        return $this->discovered ??= $this->discovery->discover();
    }
}

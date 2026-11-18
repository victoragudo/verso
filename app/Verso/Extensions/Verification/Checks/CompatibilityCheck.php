<?php

namespace App\Verso\Extensions\Verification\Checks;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\PluginManifest;
use App\Verso\Plugins\Support\VersionConstraint;
use Closure;
use Composer\InstalledVersions;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use JsonException;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

// Checks a plugin works with this site: Verso and PHP versions, Composer needs and clashes with what is installed.
class CompatibilityCheck
{
    public function __construct(
        protected PluginManager $plugins,
        protected PhpScanner $scanner,
        protected Router $router,
        protected ContentTypeRegistry $contentTypes,
        protected TaxonomyRegistry $taxonomies,
        protected PluginBlockRegistry $blocks,
    ) {}

    /**
     * Add compatibility findings. $data is the raw verso.json, $path the plugin folder being checked.
     *
     * @param  array<mixed>  $data
     */
    public function check(PluginManifest $manifest, array $data, string $path, ScanResult $scan, SandboxResult $sandbox): void
    {
        $others = $this->otherPlugins($manifest->slug, $path);
        $installed = $this->plugins->find($manifest->slug);
        $same = $installed;

        $this->checkVersions($manifest, $data, $path, $scan);
        $this->checkComposer($path, $scan);
        $this->checkSlug($manifest, $installed, $path, $scan);
        $this->checkTables($scan, $others, $same);
        $this->checkConfig($path, $scan, $others, $same);
        $this->checkClasses($manifest, $path, $scan, $others, $same);
        $this->checkRoutes($sandbox, $same, $scan);
        $this->checkRegistered($sandbox, $same, $scan);
    }

    /**
     * @param  array<mixed>  $data
     */
    protected function checkVersions(PluginManifest $manifest, array $data, string $path, ScanResult $scan): void
    {
        $verso = (string) config('verso.version');

        if (! $manifest->supports($verso)) {
            $scan->add('compat.verso-version', Severity::High, message: 'The plugin needs Verso :requires, but this site runs Verso :version.', file: 'verso.json', replace: ['requires' => $manifest->requires, 'version' => $verso]);
        }

        $constraints = array_filter([
            'verso.json' => is_string($data['php'] ?? null) ? $data['php'] : null,
            'composer.json' => is_string($this->composer($path)['require']['php'] ?? null) ? $this->composer($path)['require']['php'] : null,
        ]);

        foreach ($constraints as $file => $constraint) {
            $normalized = VersionConstraint::normalizeConstraint($constraint);

            if (! VersionConstraint::isValid($normalized)) {
                $scan->add('compat.php-version', Severity::Low, message: 'The PHP version constraint ":constraint" could not be read.', file: $file, replace: ['constraint' => $constraint]);
            } elseif (! VersionConstraint::satisfies(PHP_VERSION, $normalized)) {
                $scan->add('compat.php-version', Severity::High, message: 'The plugin needs PHP :constraint, but this site runs PHP :version.', file: $file, replace: ['constraint' => $constraint, 'version' => PHP_VERSION]);
            }
        }
    }

    // Composer packages and PHP extensions the plugin needs must already be installed.
    protected function checkComposer(string $path, ScanResult $scan): void
    {
        $require = $this->composer($path)['require'] ?? [];

        foreach (is_array($require) ? $require : [] as $package => $constraint) {
            $package = strtolower((string) $package);
            $constraint = is_string($constraint) ? $constraint : '*';

            if ($package === 'php') {
                continue;
            }

            if (str_starts_with($package, 'ext-')) {
                if (! extension_loaded(substr($package, 4))) {
                    $scan->add('compat.php-extension', Severity::High, message: 'The plugin needs the PHP extension ":extension", which is not loaded.', file: 'composer.json', replace: ['extension' => substr($package, 4)]);
                }

                continue;
            }

            if (! InstalledVersions::isInstalled($package)) {
                $scan->add('compat.composer-require', Severity::Medium, message: 'The plugin needs the Composer package :package (:constraint), which this site does not have.', file: 'composer.json', replace: ['package' => $package, 'constraint' => $constraint]);

                continue;
            }

            $version = ltrim((string) InstalledVersions::getPrettyVersion($package), 'v');
            $normalized = VersionConstraint::normalizeConstraint($constraint);

            if (preg_match('/^\d+(\.\d+)*$/', $version) === 1 && VersionConstraint::isValid($normalized) && ! VersionConstraint::satisfies($version, $normalized)) {
                $scan->add('compat.composer-require', Severity::Medium, message: 'The plugin needs :package :constraint, but this site has :version.', file: 'composer.json', replace: ['package' => $package, 'constraint' => $constraint, 'version' => $version]);
            }
        }
    }

    // A Composer package plugin with the same slug cannot be replaced by a folder plugin.
    protected function checkSlug(PluginManifest $manifest, ?Plugin $installed, string $path, ScanResult $scan): void
    {
        if ($installed === null || realpath($installed->path) === realpath($path)) {
            return;
        }

        $installPath = realpath(dirname($installed->path));
        $pluginFolders = array_map(fn (mixed $folder): string|false => realpath((string) $folder), (array) config('verso.plugins.paths'));

        if (! in_array($installPath, $pluginFolders, true)) {
            $scan->add('compat.slug-clash', Severity::High, message: 'The slug ":slug" is already used by a plugin installed with Composer.', file: 'verso.json', replace: ['slug' => $manifest->slug]);
        }
    }

    /**
     * @param  list<Plugin>  $others
     */
    protected function checkTables(ScanResult $scan, array $others, ?Plugin $same): void
    {
        if ($scan->tables === []) {
            return;
        }

        $owners = [];

        foreach ($others as $plugin) {
            foreach (array_keys($this->scanPlugin($plugin, 'migrations')->tables) as $table) {
                $owners[$table] ??= $plugin->manifest->name ?? $plugin->slug;
            }
        }

        $ownTables = $same !== null ? array_keys($this->scanPlugin($same, 'migrations')->tables) : [];

        foreach ($scan->tables as $table => $place) {
            if (in_array($table, $ownTables, true)) {
                continue;
            }

            $owner = $owners[$table] ?? ($this->tableExists($table) ? 'this site' : null);

            if ($owner !== null) {
                $scan->add('compat.table-clash', Severity::High, message: 'The table ":table" is already used by :owner.', file: $place['file'], line: $place['line'], replace: ['table' => $table, 'owner' => $owner]);
            }
        }
    }

    /**
     * @param  list<Plugin>  $others
     */
    protected function checkConfig(string $path, ScanResult $scan, array $others, ?Plugin $same): void
    {
        $keys = $scan->configKeys;

        foreach (glob($path.'/config/*.php') ?: [] as $file) {
            $keys[basename($file, '.php')] ??= 'config/'.basename($file);
        }

        foreach ($keys as $key => $file) {
            if ($same !== null && is_file($same->path.'/config/'.$key.'.php')) {
                continue;
            }

            $owner = null;

            foreach ($others as $plugin) {
                if (is_file($plugin->path.'/config/'.$key.'.php')) {
                    $owner = $plugin->manifest->name ?? $plugin->slug;
                    break;
                }
            }

            $owner ??= config()->has($key) ? 'this site' : null;

            if ($owner !== null) {
                $scan->add('compat.config-clash', Severity::Medium, message: 'The config key ":key" is already used by :owner.', file: $file, replace: ['key' => $key, 'owner' => $owner]);
            }
        }
    }

    /**
     * @param  list<Plugin>  $others
     */
    protected function checkClasses(PluginManifest $manifest, string $path, ScanResult $scan, array $others, ?Plugin $same): void
    {
        $clashingNamespaces = [];

        foreach ($others as $plugin) {
            foreach (array_keys($plugin->manifest->autoload ?? []) as $namespace) {
                if (array_key_exists($namespace, $manifest->autoload)) {
                    $clashingNamespaces[] = $namespace;
                    $scan->add('compat.class-clash', Severity::High, message: 'The namespace :namespace is already used by :owner.', file: 'verso.json', replace: ['namespace' => $namespace, 'owner' => $plugin->manifest->name ?? $plugin->slug]);
                }
            }
        }

        foreach ($scan->classes as $class => $place) {
            if ($this->inNamespaces($class, $clashingNamespaces)) {
                continue;
            }

            $existing = $this->classFile($class);

            if ($existing === null || ! is_file($existing) || $this->isInside($existing, $path) || ($same !== null && $this->isInside($existing, $same->path))) {
                continue;
            }

            $scan->add('compat.class-clash', Severity::High, message: 'The class :class already exists on this site.', file: $place['file'], line: $place['line'], replace: ['class' => $class]);
        }
    }

    // Routes the plugin added in the sandbox must not reuse names or addresses of this site.
    protected function checkRoutes(SandboxResult $sandbox, ?Plugin $same, ScanResult $scan): void
    {
        if ($sandbox->routes === []) {
            return;
        }

        $routes = $this->router->getRoutes();
        $routes->refreshNameLookups();

        foreach ($sandbox->routes as $route) {
            $existing = $route['name'] !== null ? $routes->getByName($route['name']) : null;

            if ($existing !== null && ! $this->ownedBy($existing, $same)) {
                $scan->add('compat.route-clash', Severity::High, message: 'The route name ":name" is already used on this site.', file: 'routes', replace: ['name' => $route['name']]);

                continue;
            }

            foreach ($routes->getRoutes() as $candidate) {
                if ($candidate->uri() === $route['uri'] && array_intersect($candidate->methods(), $route['methods']) !== [] && ! $this->ownedBy($candidate, $same)) {
                    $scan->add('compat.route-clash', Severity::High, message: 'The address :methods /:uri is already used on this site.', file: 'routes', replace: ['methods' => implode('|', $route['methods']), 'uri' => $route['uri']]);
                    break;
                }
            }
        }
    }

    // Content types, taxonomies and blocks the plugin registers must not replace existing ones.
    protected function checkRegistered(SandboxResult $sandbox, ?Plugin $same, ScanResult $scan): void
    {
        $messages = [
            'content_types' => ['message' => 'The content type ":key" is already registered on this site.'],
            'taxonomies' => ['message' => 'The taxonomy ":key" is already registered on this site.'],
            'blocks' => ['message' => 'The block ":key" is already registered on this site.'],
        ];

        foreach ($messages as $kind => ['message' => $message]) {
            foreach ($sandbox->registered[$kind] ?? [] as $key) {
                $exists = match ($kind) {
                    'content_types' => $this->contentTypes->has($key),
                    'taxonomies' => $this->taxonomies->has($key),
                    'blocks' => $this->blocks->has($key) && ! $this->blockOwnedBy($key, $same),
                };

                if ($exists && ($kind === 'blocks' || $same === null || ! $same->enabled)) {
                    $scan->add('compat.slug-clash', Severity::High, $message, 'verso.json', replace: ['key' => $key]);
                }
            }
        }
    }

    /**
     * Installed plugins other than the one being checked.
     *
     * @return list<Plugin>
     */
    protected function otherPlugins(string $slug, string $path): array
    {
        return array_values(array_filter(
            $this->plugins->all(),
            fn (Plugin $plugin): bool => $plugin->slug !== $slug && $plugin->manifest !== null && realpath($plugin->path) !== realpath($path),
        ));
    }

    // Scan the PHP files of one folder of an installed plugin, to learn its tables.
    protected function scanPlugin(Plugin $plugin, string $folder): ScanResult
    {
        $result = new ScanResult;
        $base = $plugin->path.DIRECTORY_SEPARATOR.trim((string) config('verso.plugins.migrations_path'), '/\\');

        foreach ($folder === 'migrations' ? (glob($base.'/*.php') ?: []) : [] as $file) {
            $this->scanner->scan('database/migrations/'.basename($file), (string) file_get_contents($file), $result);
        }

        return $result;
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    // File that declares an existing class, or null when no such class is loaded or can be autoloaded.
    // A class loaded from a file that is gone (a removed plugin) does not count as a clash.
    /**
     * @param  list<string>  $namespaces
     */
    protected function inNamespaces(string $class, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if (str_starts_with(ltrim($class, '\\'), $namespace)) {
                return true;
            }
        }

        return false;
    }

    protected function classFile(string $class): ?string
    {
        try {
            if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
                return null;
            }

            return (string) (new ReflectionClass($class))->getFileName();
        } catch (Throwable) {
            return null;
        }
    }

    // True when the route belongs to the installed version of the same plugin.
    protected function ownedBy(Route $route, ?Plugin $same): bool
    {
        if ($same === null) {
            return false;
        }

        $uses = $route->getAction('uses');
        $class = is_string($uses) ? explode('@', $uses)[0] : '';

        try {
            $file = match (true) {
                $uses instanceof Closure => (string) (new ReflectionFunction($uses))->getFileName(),
                class_exists($class) => (string) (new ReflectionClass($class))->getFileName(),
                default => '',
            };
        } catch (Throwable) {
            return false;
        }

        return $file !== '' && $this->isInside($file, $same->path);
    }

    // True when the file lives inside the folder.
    protected function isInside(string $file, string $folder): bool
    {
        $folder = realpath($folder);

        return $folder !== false && str_starts_with($file, rtrim($folder, '/').'/');
    }

    protected function blockOwnedBy(string $name, ?Plugin $same): bool
    {
        $block = $this->blocks->find($name);

        if ($block === null || $same === null) {
            return false;
        }

        return $this->isInside((string) (new ReflectionClass($block))->getFileName(), $same->path);
    }

    /**
     * The composer.json of the plugin, or an empty array.
     *
     * @return array<mixed>
     */
    protected function composer(string $path): array
    {
        $file = $path.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($file)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}

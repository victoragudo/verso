<?php

namespace App\Verso\Plugins\Support;

use App\Verso\Plugins\Plugin;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;

// Loads plugin config files in a way that also works after "php artisan config:cache".
class PluginConfig
{
    public function __construct(
        protected Application $app,
        protected Repository $config,
        protected string $relativePath,
    ) {}

    // True when the app reads its config from bootstrap/cache/config.php.
    public function isCached(): bool
    {
        return $this->app instanceof CachesConfiguration && $this->app->configurationIsCached();
    }

    // Adds the file values under the key. Values already set (by the app or the config cache) win.
    // With a cached config, a key that is already there is left alone, and a missing key (a plugin enabled after caching) is filled from the file.
    public function merge(string $path, string $key): void
    {
        if (! is_file($path) || ($this->isCached() && $this->config->get($key) !== null)) {
            return;
        }

        $values = require $path;

        $this->config->set($key, array_merge(
            is_array($values) ? $values : [],
            (array) $this->config->get($key, []),
        ));
    }

    /**
     * Config files of a plugin, keyed by config key ("config/shop.php" gives "shop").
     *
     * @return array<string, string>
     */
    public function files(Plugin $plugin): array
    {
        $folder = $plugin->path.DIRECTORY_SEPARATOR.trim($this->relativePath, '/\\');
        $files = is_dir($folder) ? (glob($folder.DIRECTORY_SEPARATOR.'*.php') ?: []) : [];
        $keyed = [];

        foreach ($files as $file) {
            $keyed[basename($file, '.php')] = $file;
        }

        return $keyed;
    }

    /**
     * Loads the config files of every plugin found, enabled or not, so "config:cache" stores them with their env values.
     *
     * @param  iterable<Plugin>  $plugins
     */
    public function mergeAll(iterable $plugins): void
    {
        if ($this->isCached()) {
            return;
        }

        foreach ($plugins as $plugin) {
            foreach ($this->files($plugin) as $key => $file) {
                $this->merge($file, $key);
            }
        }
    }
}

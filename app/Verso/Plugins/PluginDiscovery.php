<?php

namespace App\Verso\Plugins;

use App\Verso\Core\Support\ManifestCache;
use App\Verso\Plugins\Exceptions\InvalidManifest;

// Finds plugins: folders inside the plugin paths that hold a manifest file, and Composer packages with extra.verso.
class PluginDiscovery
{
    // Key inside a package "extra" section that marks it as a Verso plugin.
    public const COMPOSER_EXTRA_KEY = 'verso';

    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        protected array $paths,
        protected string $manifestName,
        protected string $versoVersion,
        protected ?string $composerInstalled = null,
        protected ?ManifestCache $manifests = null,
    ) {}

    /**
     * All plugins found, sorted by slug. Plugin folders win over Composer packages, and the first one wins when two share a slug.
     *
     * @return array<string, Plugin>
     */
    public function discover(): array
    {
        $plugins = [];

        foreach ($this->folderManifests() as $folder => $manifest) {
            $slug = basename((string) $folder);
            $plugins[$slug] ??= $this->fromManifest((string) $folder, $manifest);
        }

        foreach ($this->composerPackages() as $package) {
            $plugin = $this->loadPackage($package);
            $plugins[$plugin->slug] ??= $plugin;
        }

        ksort($plugins);

        return $plugins;
    }

    // Read one plugin folder. A broken manifest gives a plugin with errors, not an exception.
    public function load(string $folder): Plugin
    {
        return $this->fromManifest($folder, $this->readManifest($folder));
    }

    /**
     * The decoded manifest of every plugin folder, in path order, from the manifest cache when the files did not change.
     *
     * @return array<string, array{data?: array<mixed>, errors?: list<string>}>
     */
    protected function folderManifests(): array
    {
        $folders = [];

        foreach ($this->paths as $path) {
            array_push($folders, ...$this->pluginFolders($path));
        }

        $read = function () use ($folders): array {
            $manifests = [];

            foreach ($folders as $folder) {
                $manifests[$folder] ??= $this->readManifest($folder);
            }

            return $manifests;
        };

        $files = array_map(fn (string $folder): string => $folder.DIRECTORY_SEPARATOR.$this->manifestName, $folders);

        return $this->manifests?->remember('plugins', $files, $read) ?? $read();
    }

    /**
     * @return array{data?: array<mixed>, errors?: list<string>}
     */
    protected function readManifest(string $folder): array
    {
        try {
            return ['data' => PluginManifest::decode($folder.DIRECTORY_SEPARATOR.$this->manifestName)];
        } catch (InvalidManifest $exception) {
            return ['errors' => $exception->errors];
        }
    }

    /**
     * @param  array{data?: array<mixed>, errors?: list<string>}  $manifest
     */
    protected function fromManifest(string $folder, array $manifest): Plugin
    {
        $slug = basename($folder);

        try {
            if (! isset($manifest['data'])) {
                throw new InvalidManifest($folder, $manifest['errors'] ?? [__('The manifest could not be read.')]);
            }

            $manifest = PluginManifest::fromArray($manifest['data'], $folder);
        } catch (InvalidManifest $exception) {
            return new Plugin(slug: $slug, path: $folder, manifest: null, errors: $exception->errors);
        }

        return new Plugin(
            slug: $slug,
            path: $folder,
            manifest: $manifest,
            compatible: $manifest->supports($this->versoVersion),
        );
    }

    /**
     * @return list<string>
     */
    protected function pluginFolders(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $folders = glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        return array_values(array_filter(
            $folders,
            fn (string $folder): bool => is_file($folder.DIRECTORY_SEPARATOR.$this->manifestName),
        ));
    }

    /**
     * Read one Composer package. extra.verso holds the manifest, or true (or a file name) to read the manifest file in the package.
     *
     * @param  array{name: string, version: string, path: string, extra: mixed}  $package
     */
    public function loadPackage(array $package): Plugin
    {
        $path = $package['path'];
        $extra = $package['extra'];
        $fallbackSlug = $this->packageSlug($package['name']);

        try {
            if (is_array($extra)) {
                $data = $extra + ['slug' => $fallbackSlug, 'version' => $this->packageVersion($package['version'])];
                $manifest = PluginManifest::fromArray($data, $path, matchFolder: false);
            } else {
                $file = is_string($extra) && $extra !== '' ? basename($extra) : $this->manifestName;
                $manifest = PluginManifest::fromFile($path.DIRECTORY_SEPARATOR.$file, matchFolder: false);
            }
        } catch (InvalidManifest $exception) {
            $slug = is_array($extra) && is_string($extra['slug'] ?? null) ? $extra['slug'] : $fallbackSlug;

            return new Plugin(slug: $slug, path: $path, manifest: null, errors: $exception->errors);
        }

        return new Plugin(
            slug: $manifest->slug,
            path: $path,
            manifest: $manifest,
            compatible: $manifest->supports($this->versoVersion),
        );
    }

    /**
     * Packages from vendor/composer/installed.json that declare extra.verso.
     *
     * @return list<array{name: string, version: string, path: string, extra: mixed}>
     */
    public function composerPackages(): array
    {
        if ($this->composerInstalled === null || ! is_file($this->composerInstalled)) {
            return [];
        }

        // installed.json can be hundreds of kilobytes, so the short list of plugin packages is cached.
        $read = fn (): array => $this->readComposerPackages((string) $this->composerInstalled);

        return $this->manifests?->remember('composer', [$this->composerInstalled], $read) ?? $read();
    }

    /**
     * @return list<array{name: string, version: string, path: string, extra: mixed}>
     */
    protected function readComposerPackages(string $installed): array
    {
        $data = json_decode((string) file_get_contents($installed), true);

        if (! is_array($data)) {
            return [];
        }

        // Composer 2 wraps the list in "packages", Composer 1 stores a plain list.
        $packages = isset($data['packages']) && is_array($data['packages']) ? $data['packages'] : $data;
        $composerDir = dirname($installed);
        $found = [];

        foreach ($packages as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null) || ! isset($package['extra'][self::COMPOSER_EXTRA_KEY])) {
                continue;
            }

            $found[] = [
                'name' => $package['name'],
                'version' => is_string($package['version'] ?? null) ? $package['version'] : '',
                'path' => $this->packagePath($composerDir, $package),
                'extra' => $package['extra'][self::COMPOSER_EXTRA_KEY],
            ];
        }

        return $found;
    }

    /**
     * @param  array<mixed>  $package
     */
    protected function packagePath(string $composerDir, array $package): string
    {
        $installPath = is_string($package['install-path'] ?? null)
            ? $package['install-path']
            : '../'.$package['name'];

        $path = str_starts_with($installPath, '/') ? $installPath : $composerDir.DIRECTORY_SEPARATOR.$installPath;

        return rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR);
    }

    // "acme/event-calendar" gives the slug "event-calendar".
    protected function packageSlug(string $name): string
    {
        $parts = explode('/', $name);

        return strtolower(end($parts));
    }

    // Composer versions look like "v1.2.0" or "dev-main"; only plain numbers are kept.
    protected function packageVersion(string $version): string
    {
        return preg_match('/^v?(\d+\.\d+\.\d+)/', $version, $matches) === 1 ? $matches[1] : '0.0.0';
    }
}

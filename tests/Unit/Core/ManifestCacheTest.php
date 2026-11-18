<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\ManifestCache;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Theme\ThemeManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->folder = storage_path('framework/testing/manifest-cache-'.uniqid());
    File::ensureDirectoryExists($this->folder);
    $this->store = new Repository(new ArrayStore);
    $this->cache = new ManifestCache($this->store, 'verso.manifest', 3600);
});

afterEach(function () {
    File::deleteDirectory($this->folder);
});

it('builds a value once while the files stay the same', function () {
    $file = $this->folder.'/theme.json';
    file_put_contents($file, '{"name": "Harbour"}');
    $builds = 0;

    foreach (range(1, 3) as $round) {
        $value = $this->cache->remember('themes', [$file], function () use (&$builds, $file) {
            $builds++;

            return json_decode((string) file_get_contents($file), true);
        });
    }

    expect($builds)->toBe(1)
        ->and($value)->toBe(['name' => 'Harbour']);
});

it('builds again when a file changes', function () {
    $file = $this->folder.'/verso.json';
    file_put_contents($file, '{"version": "1.0.0"}');
    $read = fn () => json_decode((string) file_get_contents($file), true);
    $this->cache->remember('plugins', [$file], $read);

    file_put_contents($file, '{"version": "1.0.10"}');
    clearstatcache();

    expect($this->cache->remember('plugins', [$file], $read))->toBe(['version' => '1.0.10']);
});

it('builds again when a file is replaced by a new one', function () {
    $file = $this->folder.'/verso.json';
    file_put_contents($file, '{"version": "1.0.0"}');
    $read = fn () => json_decode((string) file_get_contents($file), true);
    $this->cache->remember('plugins', [$file], $read);

    $replacement = $this->folder.'/next.json';
    file_put_contents($replacement, '{"version": "1.1.0"}');
    rename($replacement, $file);
    clearstatcache();

    expect($this->cache->remember('plugins', [$file], $read))->toBe(['version' => '1.1.0']);
});

it('builds again when the list of files changes', function () {
    $first = $this->folder.'/one.json';
    $second = $this->folder.'/two.json';
    file_put_contents($first, '{}');
    file_put_contents($second, '{}');

    $this->cache->remember('themes', [$first], fn () => ['harbour']);

    expect($this->cache->remember('themes', [$first, $second], fn () => ['harbour', 'meadow']))->toBe(['harbour', 'meadow']);
});

it('caches empty and null values too', function () {
    $builds = 0;
    $build = function () use (&$builds) {
        $builds++;

        return null;
    };

    $this->cache->remember('composer', [$this->folder.'/missing.json'], $build);
    $this->cache->remember('composer', [$this->folder.'/missing.json'], $build);

    expect($builds)->toBe(1);
});

it('always builds when it has no store or no ttl', function () {
    $builds = 0;
    $build = function () use (&$builds) {
        return ++$builds;
    };

    (new ManifestCache(null))->remember('themes', [], $build);
    (new ManifestCache(null))->remember('themes', [], $build);
    (new ManifestCache($this->store, ttl: 0))->remember('themes', [], $build);

    expect($builds)->toBe(3);
});

it('is used for themes, plugin folders and Composer packages', function () {
    $store = new Repository(new ArrayStore);
    app()->instance(ManifestCache::class, new ManifestCache($store, 'verso.manifest', 3600));
    app()->forgetInstance(PluginDiscovery::class);
    app()->forgetInstance(ThemeManager::class);

    app(PluginDiscovery::class)->discover();
    app(ThemeManager::class)->all();

    $keys = array_keys((fn () => $this->storage)->call($store->getStore()));
    $names = array_map(fn (string $key): string => explode('.', $key)[2], $keys);

    expect($names)->toContain('themes', 'plugins', 'composer');
});

it('reads theme manifests from the cache on the next discovery', function () {
    $themes = $this->folder.'/themes';
    File::ensureDirectoryExists($themes.'/harbour');
    file_put_contents($themes.'/harbour/theme.json', '{"name": "Harbour", "version": "2.1.0"}');
    $manager = fn () => new ThemeManager(app(Settings::class), app('view'), $themes, 'harbour', $this->cache);

    expect($manager()->find('harbour')?->version)->toBe('2.1.0');

    // A changed cache entry proves the next manager does not read the file again.
    $key = 'verso.manifest.themes.'.$this->cache->fingerprint([$themes.'/harbour/theme.json']);
    $this->store->put($key, ['value' => [$themes.'/harbour' => ['name' => 'Harbour from cache', 'version' => '9.9.9']]], 60);

    expect($manager()->find('harbour')?->name)->toBe('Harbour from cache');
});

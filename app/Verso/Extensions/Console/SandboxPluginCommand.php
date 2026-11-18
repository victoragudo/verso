<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginMigrator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

// Runs inside the sandbox process: migrates a temporary database, boots one plugin and rolls its migrations back.
class SandboxPluginCommand extends SandboxCommand
{
    protected $signature = 'verso:sandbox-plugin {path : Plugin folder inside the sandbox workspace} {--database= : The temporary SQLite file}';

    protected $description = 'Boot one plugin in the verification sandbox (used by verso:verify-plugin)';

    /** @var array<string, mixed> */
    protected array $report = ['migrated' => [], 'rolled_back' => [], 'routes' => [], 'registered' => [], 'errors' => []];

    public function handle(PluginManager $plugins, PluginMigrator $migrator, Router $router): int
    {
        $problem = $this->safetyProblem();

        if ($problem !== null) {
            return $this->finish([['stage' => 'setup', 'message' => $problem]]);
        }

        $slug = basename((string) $this->argument('path'));

        $error = $this->step('database', fn () => $this->callSilently('migrate', ['--force' => true]) === 0 ? null : 'The core migrations failed.')
            ?? $this->step('manifest', function () use ($plugins, $slug): ?string {
                $plugin = $plugins->find($slug);

                return $plugin === null ? 'The plugin was not found.' : ($plugin->isValid() ? null : implode(' ', $plugin->errors));
            });

        if ($error !== null) {
            return $this->finish([$error]);
        }

        $plugin = $plugins->get($slug);
        $before = $this->snapshot($router);

        // Each step runs even when an earlier one failed, so the report shows as much as possible.
        $errors = array_values(array_filter([
            $this->step('migrate', function () use ($migrator, $plugin): null {
                $this->report['migrated'] = $migrator->migrate($plugin);

                return null;
            }),
            $this->step('boot', function () use ($plugins, $plugin): null {
                $plugins->register($plugin);

                return null;
            }),
            $this->step('rollback', function () use ($migrator, $plugin): null {
                $this->report['rolled_back'] = $migrator->rollback($plugin);

                return null;
            }),
        ]));

        $this->report['routes'] = $this->newRoutes($router, $before['routes']);
        $this->report['registered'] = $this->newKeys($before);

        return $this->finish($errors);
    }

    /**
     * Routes, content types, taxonomies and blocks before the plugin boots.
     *
     * @return array{routes: array<string, true>, content_types: array<string, int>, taxonomies: array<string, int>, blocks: array<string, int>}
     */
    protected function snapshot(Router $router): array
    {
        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $routes[$this->routeKey($route)] = true;
        }

        return [
            'routes' => $routes,
            'content_types' => array_map(spl_object_id(...), app(ContentTypeRegistry::class)->all()),
            'taxonomies' => array_map(spl_object_id(...), app(TaxonomyRegistry::class)->all()),
            'blocks' => array_map(spl_object_id(...), app(PluginBlockRegistry::class)->all()),
        ];
    }

    /**
     * @param  array<string, true>  $before
     * @return list<array{uri: string, name: string|null, methods: list<string>}>
     */
    protected function newRoutes(Router $router, array $before): array
    {
        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! isset($before[$this->routeKey($route)])) {
                $routes[] = ['uri' => $route->uri(), 'name' => $route->getName(), 'methods' => array_values(array_diff($route->methods(), ['HEAD']))];
            }
        }

        return $routes;
    }

    /**
     * Keys the plugin added or replaced in the registries.
     *
     * @param  array{content_types: array<string, int>, taxonomies: array<string, int>, blocks: array<string, int>}  $before
     * @return array<string, list<string>>
     */
    protected function newKeys(array $before): array
    {
        $after = [
            'content_types' => array_map(spl_object_id(...), app(ContentTypeRegistry::class)->all()),
            'taxonomies' => array_map(spl_object_id(...), app(TaxonomyRegistry::class)->all()),
            'blocks' => array_map(spl_object_id(...), app(PluginBlockRegistry::class)->all()),
        ];

        $keys = [];

        foreach ($after as $kind => $ids) {
            $keys[$kind] = array_map(strval(...), array_keys(array_filter(
                $ids,
                fn (int $id, string|int $key): bool => ($before[$kind][$key] ?? null) !== $id,
                ARRAY_FILTER_USE_BOTH,
            )));
        }

        return $keys;
    }

    protected function routeKey(Route $route): string
    {
        return implode('|', $route->methods()).' '.$route->uri().' '.($route->getName() ?? '');
    }
}

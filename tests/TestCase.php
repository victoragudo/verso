<?php

namespace Tests;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    // Tests render Inertia pages without a built Vite manifest.
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // The test schema also has the tables of the bundled plugins. MySQL commits the test transaction on every
    // schema change, so running a plugin migration inside a test would make Laravel rebuild the whole database.
    public function createApplication()
    {
        $app = parent::createApplication();

        if (isset($this->traitsUsedByTest[RefreshDatabase::class])) {
            $folder = trim((string) $app['config']->get('verso.plugins.migrations_path'), '/\\');
            $paths = glob($app->basePath('plugins/*/'.$folder)) ?: [];
            $app->afterResolving('migrator', fn (Migrator $migrator) => array_map($migrator->path(...), $paths));

            if ($app->resolved('migrator')) {
                array_map($app->make('migrator')->path(...), $paths);
            }
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

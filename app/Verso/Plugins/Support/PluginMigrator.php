<?php

namespace App\Verso\Plugins\Support;

use App\Verso\Plugins\Plugin;
use Illuminate\Database\Migrations\Migrator;

// Runs the database migrations that a plugin ships in its own folder.
class PluginMigrator
{
    public function __construct(
        protected Migrator $migrator,
        protected string $relativePath,
        protected bool $runOnEnable = true,
    ) {}

    // True when enabling a plugin should also run its migrations.
    public function runsOnEnable(): bool
    {
        return $this->runOnEnable;
    }

    // The migrations folder of the plugin, or null when it has none.
    public function path(Plugin $plugin): ?string
    {
        $path = $plugin->path.DIRECTORY_SEPARATOR.trim($this->relativePath, '/\\');

        return is_dir($path) ? $path : null;
    }

    /**
     * Run the pending migrations of the plugin.
     *
     * @return list<string> the names of the migrations that ran
     */
    public function migrate(Plugin $plugin): array
    {
        $path = $this->path($plugin);

        if ($path === null) {
            return [];
        }

        if (! $this->migrator->repositoryExists()) {
            $this->migrator->getRepository()->createRepository();
        }

        /** @var list<string> $ran */
        $ran = $this->migrator->run([$path]);

        return array_map(fn (string $file): string => $this->migrator->getMigrationName($file), $ran);
    }

    /**
     * Roll back every migration of the plugin that ran, newest first. The plugin tables are dropped.
     *
     * @return list<string> the names of the migrations that were rolled back
     */
    public function rollback(Plugin $plugin): array
    {
        $path = $this->path($plugin);

        if ($path === null || ! $this->migrator->repositoryExists()) {
            return [];
        }

        /** @var list<string> $rolledBack */
        $rolledBack = $this->migrator->reset([$path]);

        return array_map(fn (string $file): string => $this->migrator->getMigrationName($file), $rolledBack);
    }
}

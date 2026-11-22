<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Pipeline\InstallProgress;
use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

// Creates the tables. A retry first removes the tables that the failed try left behind:
// on MySQL and MariaDB a migration cut in half keeps its tables, and the next try fails
// with "table already exists". Tables that were there before the install are never touched.
class RunMigrations implements InstallStep
{
    public function __construct(
        protected Kernel $artisan,
        protected InstallProgress $progress,
        protected DatabaseManager $db,
    ) {}

    public function key(): string
    {
        return 'migrations';
    }

    public function label(): string
    {
        return __('Create the tables');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $before = $this->progress->tablesBeforeMigrations();

        if ($before === null) {
            $this->progress->rememberTablesBeforeMigrations($this->tables());
        } else {
            $this->dropTablesNotIn($before);
        }

        if ($this->artisan->call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(__('The migrations failed: :output', ['output' => trim($this->artisan->output())]));
        }
    }

    /**
     * @return list<string>
     */
    protected function tables(): array
    {
        return $this->db->connection()->getSchemaBuilder()->getTableListing();
    }

    /**
     * @param  list<string>  $keep
     */
    protected function dropTablesNotIn(array $keep): void
    {
        $leftovers = array_diff($this->tables(), $keep);

        if ($leftovers === []) {
            return;
        }

        $connection = $this->db->connection();
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();

        $schema->withoutForeignKeyConstraints(function () use ($schema, $leftovers, $prefix): void {
            foreach ($leftovers as $table) {
                $schema->dropIfExists($this->withoutPrefix($table, $prefix));
            }
        });
    }

    // Listed names include the table prefix, and dropIfExists() adds it again.
    protected function withoutPrefix(string $qualified, string $prefix): string
    {
        $dot = strrpos($qualified, '.');
        $schema = $dot === false ? '' : substr($qualified, 0, $dot + 1);
        $name = $dot === false ? $qualified : substr($qualified, $dot + 1);

        if ($prefix !== '' && str_starts_with($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }

        return $schema.$name;
    }
}

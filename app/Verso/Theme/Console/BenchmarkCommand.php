<?php

namespace App\Verso\Theme\Console;

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Benchmark\BenchmarkSeeder;
use App\Verso\Theme\Benchmark\RequestTimer;
use App\Verso\Theme\Benchmark\Samples;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

// Seeds a temporary database and measures the response time of public pages with and without the page cache.
class BenchmarkCommand extends Command
{
    // File name prefix of the temporary database. The worker refuses any other database.
    public const DATABASE_PREFIX = 'verso-benchmark-';

    protected $signature = 'verso:benchmark
        {--entries= : Posts to seed}
        {--requests= : Measured requests per route and mode}
        {--warmup= : Requests sent before measuring}
        {--worker : Internal: run the measurement inside the child process}';

    protected $description = 'Measure the time to first byte of public pages with and without the page cache';

    public function handle(): int
    {
        return $this->option('worker') ? $this->work() : $this->launch();
    }

    // Runs the benchmark in a child process, so it never touches the real database, cache or sessions.
    protected function launch(): int
    {
        if ($this->laravel->configurationIsCached()) {
            $this->error('The config is cached. Run "php artisan config:clear" first, so the benchmark can use its own database.');

            return self::FAILURE;
        }

        $database = storage_path('framework/'.self::DATABASE_PREFIX.Str::lower(Str::random(10)).'.sqlite');
        touch($database);

        try {
            $result = Process::forever()
                ->path(base_path())
                ->env($this->childEnvironment($database))
                ->run($this->childCommand(), function (string $type, string $output): void {
                    $this->output->write($output);
                });

            return $result->exitCode() ?? self::FAILURE;
        } finally {
            foreach ([$database, $database.'-wal', $database.'-shm', $database.'-journal'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function childCommand(): array
    {
        $command = [PHP_BINARY, 'artisan', 'verso:benchmark', '--worker', '--no-ansi'];

        foreach (['entries', 'requests', 'warmup'] as $option) {
            if ($this->option($option) !== null) {
                $command[] = '--'.$option.'='.(int) $this->option($option);
            }
        }

        return $command;
    }

    /**
     * Production settings with a private SQLite database, the database cache store and no stored sessions.
     * The budgets come from this process, so the child compares with the same values.
     *
     * @return array<string, string>
     */
    protected function childEnvironment(string $database): array
    {
        return [
            'VERSO_BENCHMARK_DATABASE' => $database,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_URL' => '',
            'DB_DATABASE' => $database,
            'CACHE_STORE' => 'database',
            'VERSO_PAGE_CACHE' => 'true',
            'VERSO_PAGE_CACHE_STORE' => '',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_LEVEL' => 'error',
            'VERSO_BENCHMARK_PERCENTILE' => (string) $this->percentile(),
            'VERSO_BENCHMARK_CACHED_BUDGET_MS' => (string) (float) config('verso.benchmark.cached_budget_ms'),
            'VERSO_BENCHMARK_UNCACHED_BUDGET_MS' => (string) (float) config('verso.benchmark.uncached_budget_ms'),
        ];
    }

    protected function work(): int
    {
        $database = (string) getenv('VERSO_BENCHMARK_DATABASE');

        if (! $this->isBenchmarkDatabase($database)) {
            $this->error('The worker only runs on a temporary benchmark database. Run "php artisan verso:benchmark" instead.');

            return self::FAILURE;
        }

        $entries = $this->number('entries');
        $requests = $this->number('requests');
        $warmup = $this->number('warmup');

        $this->line("Seeding {$entries} posts into a temporary SQLite database...");
        $this->callSilently('migrate', ['--force' => true]);
        $this->laravel->make(Settings::class)->forgetCache();
        $paths = $this->laravel->make(BenchmarkSeeder::class)->seed($entries);

        $this->line("Measuring {$requests} requests per route after {$warmup} warm up requests (in-process HTTP kernel).");

        try {
            $rows = $this->measure($paths, $requests, $warmup);
        } catch (Throwable $exception) {
            $this->error('The benchmark stopped: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Route', 'Path', 'Page cache', 'p50 ms', 'p95 ms', 'p99 ms', 'Mean ms', 'Budget ms', 'Result'], array_column($rows, 'row'));

        $failed = array_filter($rows, fn (array $row): bool => ! $row['passed']);
        $percentile = $this->percentile();

        if ($failed !== []) {
            $this->error(count($failed)." of the measured routes are over budget at p{$percentile}.");

            return self::FAILURE;
        }

        $this->info("Every route is within budget at p{$percentile}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $paths
     * @return list<array{row: list<string>, passed: bool}>
     */
    protected function measure(array $paths, int $requests, int $warmup): array
    {
        $timer = $this->laravel->make(RequestTimer::class);
        $rows = [];

        foreach ([false, true] as $cached) {
            $timer->usePageCache($cached);
            $budget = (float) config($cached ? 'verso.benchmark.cached_budget_ms' : 'verso.benchmark.uncached_budget_ms');

            foreach ($paths as $name => $path) {
                $samples = $timer->measure($path, $requests, max($cached ? 1 : 0, $warmup), $cached ? 'HIT' : null);
                $rows[] = $this->row($name, $path, $cached, $samples, $budget);
            }
        }

        return $rows;
    }

    /**
     * @return array{row: list<string>, passed: bool}
     */
    protected function row(string $name, string $path, bool $cached, Samples $samples, float $budget): array
    {
        $measured = $samples->percentile($this->percentile());
        $passed = $budget <= 0 || $measured <= $budget;

        return [
            'row' => [
                $name,
                $path,
                $cached ? 'on' : 'off',
                $this->ms($samples->percentile(50)),
                $this->ms($samples->percentile(95)),
                $this->ms($samples->percentile(99)),
                $this->ms($samples->mean()),
                $budget > 0 ? $this->ms($budget) : '-',
                $passed ? 'ok' : 'OVER',
            ],
            'passed' => $passed,
        ];
    }

    // True only for a temporary file made by the parent command, and only when it is the database in use.
    protected function isBenchmarkDatabase(string $database): bool
    {
        return $database !== ''
            && str_starts_with(basename($database), self::DATABASE_PREFIX)
            && is_file($database)
            && config('database.default') === 'sqlite'
            && realpath((string) config('database.connections.sqlite.database')) === realpath($database);
    }

    protected function number(string $option): int
    {
        $value = $this->option($option) ?? config('verso.benchmark.'.$option);

        return max(1, (int) $value);
    }

    protected function percentile(): int
    {
        return (int) config('verso.benchmark.percentile');
    }

    protected function ms(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}

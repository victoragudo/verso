<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

// Creates the tables. Migrations that already ran are skipped, so a retry is safe.
class RunMigrations implements InstallStep
{
    public function __construct(protected Kernel $artisan) {}

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
        if ($this->artisan->call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException(__('The migrations failed: :output', ['output' => trim($this->artisan->output())]));
        }
    }
}

<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Database\DatabaseCreator;
use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;

// Creates the database (or the SQLite file) when it does not exist yet.
class PrepareDatabase implements InstallStep
{
    public function __construct(protected DatabaseCreator $creator) {}

    public function key(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return __('Prepare the database');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $this->creator->create($plan->database);
    }
}

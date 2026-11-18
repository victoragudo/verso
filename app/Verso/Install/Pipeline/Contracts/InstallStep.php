<?php

namespace App\Verso\Install\Pipeline\Contracts;

use App\Verso\Install\Plan\InstallPlan;

// One step of the install pipeline. Steps must be safe to run again after a failure.
interface InstallStep
{
    public function key(): string;

    public function label(): string;

    // False skips the step for this plan, like demo content when the user starts empty.
    public function shouldRun(InstallPlan $plan): bool;

    public function handle(InstallPlan $plan): void;
}

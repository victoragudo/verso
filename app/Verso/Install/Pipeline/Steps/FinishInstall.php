<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Core\Settings\Settings;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;

// Marks the site as installed (setting and lock file) and warms the settings cache.
class FinishInstall implements InstallStep
{
    public function __construct(
        protected Settings $settings,
        protected InstallState $state,
    ) {}

    public function key(): string
    {
        return 'finish';
    }

    public function label(): string
    {
        return __('Finish and warm the cache');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $this->settings->setMany([
            InstallState::SETTING => now()->toIso8601String(),
            'install.version' => (string) config('verso.version'),
        ]);

        $this->settings->forgetCache();
        $this->settings->all();
        $this->state->lock();
    }
}

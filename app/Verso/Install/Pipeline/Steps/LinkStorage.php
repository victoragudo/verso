<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Console\Kernel;

// Links public/storage to the uploads folder, so uploaded files can be served.
class LinkStorage implements InstallStep
{
    public function __construct(
        protected Kernel $artisan,
        protected Config $config,
    ) {}

    public function key(): string
    {
        return 'storage';
    }

    public function label(): string
    {
        return __('Link the uploads folder');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return $plan->services->storage === 'local';
    }

    public function handle(InstallPlan $plan): void
    {
        foreach (array_keys((array) $this->config->get('filesystems.links', [])) as $link) {
            if (! file_exists((string) $link) && ! is_link((string) $link)) {
                $this->artisan->call('storage:link');

                return;
            }
        }
    }
}

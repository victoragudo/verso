<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Env\EnvWriteInput;
use App\Verso\Install\Env\EnvWriter;
use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Console\Kernel;

// Writes the chosen settings to .env and clears the config cache.
class WriteEnvironment implements InstallStep
{
    public function __construct(
        protected EnvWriter $writer,
        protected Kernel $artisan,
        protected Config $config,
        protected string $envPath,
    ) {}

    public function key(): string
    {
        return 'environment';
    }

    public function label(): string
    {
        return __('Write the .env file');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $values = $plan->envValues();

        if ((string) $this->config->get('app.key') === '') {
            $values = ['APP_KEY' => $this->writer->newAppKey(), ...$values];
        }

        $this->writer->write(new EnvWriteInput($this->envPath, $values));
        $this->artisan->call('config:clear');
    }
}

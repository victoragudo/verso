<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;

// Adds the demo posts, pages, photos, menus and comments. One transaction, so a retry starts clean.
class SeedDemoContent implements InstallStep
{
    public function __construct(
        protected Container $container,
        protected DatabaseManager $db,
    ) {}

    public function key(): string
    {
        return 'demo';
    }

    public function label(): string
    {
        return __('Add the demo content');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return $plan->content->demo;
    }

    public function handle(InstallPlan $plan): void
    {
        $seeder = $this->container->make(DemoContentSeeder::class);
        $seeder->setContainer($this->container);

        $this->db->connection()->transaction(fn () => $seeder->__invoke());
    }
}

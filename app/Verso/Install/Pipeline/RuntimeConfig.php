<?php

namespace App\Verso\Install\Pipeline;

use App\Verso\Install\Plan\InstallPlan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;

// Points the running app at the chosen database and services, so steps work before .env is read again.
class RuntimeConfig
{
    public function __construct(
        protected Config $config,
        protected DatabaseManager $db,
        protected string $basePath,
    ) {}

    public function apply(InstallPlan $plan): void
    {
        $driver = $plan->database->driver;
        $connection = $plan->database->connectionConfig($this->basePath);
        $current = (array) $this->config->get("database.connections.{$driver}", []);

        $this->config->set("database.connections.{$driver}", array_merge($current, $connection));
        $this->config->set('database.default', $driver);
        $this->db->purge($driver);
        $this->db->setDefaultConnection($driver);

        $this->config->set([
            'app.name' => $plan->site->name,
            'app.url' => $plan->site->url,
            ...$plan->services->mailConfig(),
        ]);

        if ($plan->services->usesRedis()) {
            $this->config->set($plan->services->redisConfig());
        }

        if ($plan->services->storage === 's3') {
            $this->config->set('filesystems.disks.s3', array_merge((array) $this->config->get('filesystems.disks.s3', []), $plan->services->s3Config()));
            $this->config->set('verso.media.disk', 's3');
        }
    }
}

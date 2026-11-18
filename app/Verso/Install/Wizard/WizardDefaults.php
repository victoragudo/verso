<?php

namespace App\Verso\Install\Wizard;

use App\Verso\Install\Database\DatabaseSettings;
use Illuminate\Contracts\Config\Repository as Config;

// First values for each wizard step, read from config and the environment (useful in Docker).
class WizardDefaults
{
    public function __construct(
        protected Config $config,
        protected WizardOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(WizardStep $step, string $siteUrl = '', string $language = 'en'): array
    {
        return match ($step) {
            WizardStep::Welcome => ['language' => $language],
            WizardStep::Database => $this->environmentDatabase()->masked(),
            WizardStep::Site => [
                'site_name' => $this->config->get('app.name') === 'Laravel' ? '' : (string) $this->config->get('app.name'),
                'tagline' => '',
                'site_url' => rtrim($siteUrl ?: (string) $this->config->get('app.url'), '/'),
                'locale' => in_array($language, $this->options->locales(), true) ? $language : 'en',
                'timezone' => (string) $this->config->get('verso.install.cli.timezone', 'UTC'),
                'admin_name' => '',
                'admin_email' => '',
            ],
            WizardStep::Services => [
                'mailer' => 'log',
                'mail_host' => '',
                'mail_port' => (int) $this->config->get('verso.install.services.smtp_port'),
                'mail_encryption' => 'tls',
                'mail_username' => '',
                'mail_from_address' => '',
                'mail_from_name' => '',
                'cache_driver' => 'file',
                'session_driver' => 'file',
                'queue_driver' => 'sync',
                'redis_host' => (string) $this->config->get('verso.install.services.redis_host'),
                'redis_port' => (int) $this->config->get('verso.install.services.redis_port'),
                'storage' => 'local',
                's3_key' => '',
                's3_region' => (string) $this->config->get('verso.install.services.s3_region'),
                's3_bucket' => '',
                's3_endpoint' => '',
                's3_path_style' => false,
            ],
            WizardStep::Content => ['demo' => false, 'theme' => $this->options->recommendedTheme(), 'plugins' => []],
            default => [],
        };
    }

    // The database from DB_* environment values, or SQLite with the default file.
    public function environmentDatabase(): DatabaseSettings
    {
        $install = (array) $this->config->get('verso.install.database', []);
        $driver = (string) $this->config->get('database.default');
        $drivers = array_keys((array) ($install['drivers'] ?? []));

        if (! in_array($driver, $drivers, true) || $driver === 'sqlite') {
            return new DatabaseSettings(driver: 'sqlite', database: (string) ($install['sqlite_path'] ?? 'database/database.sqlite'));
        }

        $connection = (array) $this->config->get("database.connections.{$driver}", []);

        return DatabaseSettings::fromArray([
            'driver' => $driver,
            'host' => $connection['host'] ?? $install['host'] ?? '127.0.0.1',
            'port' => $connection['port'] ?? $install['drivers'][$driver]['port'] ?? null,
            'database' => $connection['database'] ?? $install['name'] ?? 'verso',
            'username' => $connection['username'] ?? '',
            'password' => $connection['password'] ?? '',
            'prefix' => $connection['prefix'] ?? '',
            'sslmode' => $connection['sslmode'] ?? $install['ssl_mode'] ?? 'prefer',
            'charset' => $connection['charset'] ?? $install['charset'] ?? '',
            'collation' => $connection['collation'] ?? $install['collation'] ?? '',
        ]);
    }

    // True when DB_* values point at a server database, like in a Docker setup.
    public function hasEnvironmentServer(): bool
    {
        return ! $this->environmentDatabase()->isSqlite() && $this->environmentDatabase()->host !== '';
    }
}

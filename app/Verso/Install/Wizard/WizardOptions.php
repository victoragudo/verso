<?php

namespace App\Verso\Install\Wizard;

use App\Verso\I18n\Locales;
use App\Verso\I18n\Translate;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Services\ServiceTester;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository as Config;

// Lists the choices the wizard and the command offer. Nothing here touches the database.
class WizardOptions
{
    public function __construct(
        protected Config $config,
        protected DatabaseConnectionTester $tester,
        protected ServiceTester $services,
        protected ThemeManager $themes,
        protected PluginDiscovery $plugins,
    ) {}

    /**
     * @return array<string, string>
     */
    public function languages(): array
    {
        $languages = [];

        foreach (app(Locales::class)->all() as $code => $locale) {
            $languages[$code] = $locale->name;
        }

        return $languages;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_map(strval(...), (array) $this->config->get('verso.site.locales', [])));
    }

    /**
     * @return list<string>
     */
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * Database drivers with their label, default port and if PHP can use them.
     *
     * @return list<array{value: string, label: string, port: int|null, available: bool, extension: string}>
     */
    public function drivers(): array
    {
        $drivers = [];

        foreach ((array) $this->config->get('verso.install.database.drivers', []) as $value => $driver) {
            $drivers[] = [
                'value' => (string) $value,
                'label' => (string) $driver['label'],
                'port' => $driver['port'] === null ? null : (int) $driver['port'],
                'available' => $this->tester->driverAvailable((string) $value),
                'extension' => (string) $driver['extension'],
            ];
        }

        return $drivers;
    }

    /**
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        return array_values(array_map(fn (array $driver): string => $driver['value'], array_filter($this->drivers(), fn (array $driver): bool => $driver['available'])));
    }

    /**
     * Installed themes. The recommended theme (verso.demo.theme) is marked and listed first.
     *
     * @return list<array{slug: string, name: string, description: string, recommended: bool}>
     */
    public function themes(): array
    {
        $recommended = $this->recommendedTheme();
        $themes = array_values(array_map(fn (Theme $theme): array => [
            'slug' => $theme->slug,
            'name' => $theme->name,
            'description' => Translate::text($theme->description, plugin: $theme->slug),
            'recommended' => $theme->slug === $recommended,
        ], $this->themes->all()));

        usort($themes, fn (array $a, array $b): int => $b['recommended'] <=> $a['recommended']);

        return $themes;
    }

    // The theme the wizard suggests, or the fallback theme when it is not installed.
    public function recommendedTheme(): string
    {
        $slug = (string) $this->config->get('verso.demo.theme');

        return $slug !== '' && $this->themes->exists($slug) ? $slug : $this->themes->active()->slug;
    }

    /**
     * Plugins on disk that can be turned on.
     *
     * @return list<array{slug: string, name: string, description: string, version: string}>
     */
    public function plugins(): array
    {
        $plugins = array_filter($this->plugins->discover(), fn (Plugin $plugin): bool => $plugin->canBeEnabled() && $plugin->manifest !== null);

        return array_values(array_map(fn (Plugin $plugin): array => [
            'slug' => $plugin->slug,
            'name' => (string) $plugin->manifest?->name,
            'description' => Translate::text((string) $plugin->manifest?->description, plugin: $plugin->slug),
            'version' => (string) $plugin->manifest?->version,
        ], $plugins));
    }

    /**
     * @return array<string, mixed>
     */
    public function services(): array
    {
        $install = (array) $this->config->get('verso.install.services', []);

        return [
            'mailers' => $install['mailers'] ?? [],
            'smtp_encryptions' => $install['smtp_encryptions'] ?? [],
            'cache_drivers' => $install['cache_drivers'] ?? [],
            'session_drivers' => $install['session_drivers'] ?? [],
            'queue_drivers' => $install['queue_drivers'] ?? [],
            'redis_available' => $this->services->redisAvailable(),
            's3_available' => $this->services->s3Available(),
        ];
    }

    /**
     * @return list<string>
     */
    public function sslModes(): array
    {
        return array_values(array_map(strval(...), (array) $this->config->get('verso.install.database.ssl_modes', [])));
    }
}

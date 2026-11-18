<?php

namespace App\Verso\Extensions\Actions;

use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginMigrator;
use App\Verso\Site\Models\SitePart;
use Throwable;

// Deletes what a plugin or theme stored: plugin tables (its migrations run down) and settings.
class DeleteExtensionData
{
    public function __construct(
        protected PluginManager $plugins,
        protected PluginMigrator $migrator,
        protected Settings $settings,
    ) {}

    // Returns a short summary for the admin, like "2 tables migrations rolled back, 5 settings deleted".
    public function handle(ExtensionKind $kind, string $slug): string
    {
        return $kind === ExtensionKind::Plugin ? $this->deletePluginData($slug) : $this->deleteThemeData($slug);
    }

    // Setting keys of a plugin start with its slug, like "shop.currency".
    public static function pluginSettingsPrefix(string $slug): string
    {
        return $slug.'.';
    }

    // Setting keys of a theme start with "theme.<slug>.", like "theme.harbour.accent".
    public static function themeSettingsPrefix(string $slug): string
    {
        return 'theme.'.$slug.'.';
    }

    protected function deletePluginData(string $slug): string
    {
        $plugin = $this->plugins->find($slug);
        $rolledBack = $plugin instanceof Plugin ? $this->rollback($plugin) : [];
        $settings = $this->settings->forgetPrefix(self::pluginSettingsPrefix($slug));

        return count($rolledBack).' migrations rolled back, '.count($settings).' settings deleted';
    }

    // Theme settings and the header, footer and other parts edited in the Site Editor (their revisions go too).
    protected function deleteThemeData(string $slug): string
    {
        $settings = $this->settings->forgetPrefix(self::themeSettingsPrefix($slug));
        $parts = SitePart::query()->where('theme', $slug)->delete();

        return count($settings).' settings deleted, '.$parts.' edited parts deleted';
    }

    /**
     * Plugin classes are loaded first, because a migration may use them.
     *
     * @return list<string>
     */
    protected function rollback(Plugin $plugin): array
    {
        try {
            $this->plugins->autoload($plugin);

            return $this->migrator->rollback($plugin);
        } catch (Throwable $exception) {
            throw ExtensionInstallFailed::because(__('The data of ":slug" could not be deleted: :error', ['slug' => $plugin->slug, 'error' => $exception->getMessage()]));
        }
    }
}

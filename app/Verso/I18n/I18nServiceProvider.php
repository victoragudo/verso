<?php

namespace App\Verso\I18n;

use App\Verso\I18n\Console\ExtractTranslationsCommand;
use App\Verso\I18n\Listeners\KeepGuestLocale;
use App\Verso\I18n\Translation\NamespacedJsonLoader;
use App\Verso\I18n\Translation\TranslationBundle;
use App\Verso\I18n\Translation\TranslationNamespaces;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use Throwable;

// Admin languages: the user choice, lang files of core, plugins and themes, and the bundle for the browser.
class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Locales::class, fn (): Locales => new Locales(
            array_values(array_map(strval(...), (array) config('verso.i18n.admin_locales'))),
            (string) config('verso.i18n.fallback_locale', 'en'),
        ));
        $this->app->singleton(TranslationNamespaces::class);
        $this->app->singleton(AdminLocale::class);
        $this->app->singleton(TranslationBundle::class, fn (Application $app): TranslationBundle => new TranslationBundle(
            $app->make(Locales::class),
            $app->make(TranslationNamespaces::class),
            $this->cacheStore($app),
            $app->langPath(),
            (int) config('verso.i18n.bundle_cache_ttl'),
        ));

        $this->app->extend('translation.loader', fn (Loader $loader, Application $app): Loader => new NamespacedJsonLoader(
            $loader,
            $app->make(TranslationNamespaces::class),
        ));

        $this->app->extend('translator', fn (Translator $translator): Translator => $translator->handleMissingKeysUsing(
            fn (string $key, array $replace, ?string $locale, bool $fallback): string => $this->missingKey($key, $locale, $fallback),
        ));
    }

    public function boot(): void
    {
        Route::group([], base_path('routes/verso/i18n.php'));

        Event::listen(Login::class, KeepGuestLocale::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExtractTranslationsCommand::class]);
        }

        $this->app->make(TranslationNamespaces::class)->resolveUsing(fn (): array => $this->themeFolders());
    }

    // A plugin or theme key with no translation uses the English file, then the text without the prefix.
    protected function missingKey(string $key, ?string $locale, bool $fallback): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*::(.+)$/s', $key, $match) !== 1) {
            return $key;
        }

        $source = $this->app->make(Locales::class)->fallback();

        if ($fallback && $locale !== $source) {
            $english = $this->app->make('translator')->get($key, [], $source, false);

            if (is_string($english) && $english !== $key) {
                return $english;
            }
        }

        return $match[1];
    }

    // The lang folders of the active theme and its parents. Empty before install.
    /**
     * @return array<string, string>
     */
    protected function themeFolders(): array
    {
        $folder = (string) config('verso.i18n.extension_lang_path');

        try {
            $themes = $this->app->make(ThemeManager::class);
            $lineage = $themes->lineage($themes->active());
        } catch (Throwable) {
            return [];
        }

        $folders = [];

        foreach ($lineage as $theme) {
            /** @var Theme $theme */
            $folders[$theme->slug] = $theme->filePath($folder);
        }

        return $folders;
    }

    protected function cacheStore(Application $app): ?Repository
    {
        try {
            return $app->make('cache')->store();
        } catch (Throwable) {
            return null;
        }
    }
}

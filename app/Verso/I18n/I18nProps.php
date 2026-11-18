<?php

namespace App\Verso\I18n;

use App\Models\User;
use App\Verso\I18n\Translation\TranslationBundle;
use Illuminate\Http\Request;

// The language data every admin page gets: current language, direction, bundle URL and the language list.
class I18nProps
{
    public function __construct(
        protected Locales $locales,
        protected AdminLocale $adminLocale,
        protected TranslationBundle $bundle,
    ) {}

    /**
     * @return array{locale: string, dir: string, bundle: string|null, fallback: string, siteLocale: string, contentLang: string, contentDir: string, userLocale: string|null, locales: list<array{code: string, name: string, englishName: string, dir: string}>}
     */
    public function forRequest(Request $request): array
    {
        $locale = $this->locales->normalize(app()->getLocale()) ?? $this->locales->fallback();
        $user = $request->user();

        return [
            'locale' => $locale,
            'dir' => $this->locales->dir($locale),
            'bundle' => $this->bundleUrl($locale),
            'fallback' => $this->locales->fallback(),
            'siteLocale' => $this->adminLocale->siteDefault(),
            'contentLang' => $content = $this->adminLocale->contentLanguage(),
            'contentDir' => $this->locales->directionOf($content),
            'userLocale' => $user instanceof User ? $this->locales->normalize($user->admin_locale) : null,
            'locales' => $this->locales->toArray(),
        ];
    }

    // Relative URL of the strings file, or null for the source language.
    public function bundleUrl(string $locale): ?string
    {
        if (! $this->bundle->needsBundle($locale)) {
            return null;
        }

        return route('verso.i18n.bundle', ['locale' => $locale, 'version' => $this->bundle->version($locale)], false);
    }
}

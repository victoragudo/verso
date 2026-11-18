<?php

namespace App\Verso\I18n;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Illuminate\Http\Request;
use Throwable;

// Picks the admin language: the user choice, then the site language, then English.
// Guests (login, install) get the language they picked, then the browser language.
class AdminLocale
{
    // Session key with the language a guest picked on the login or install screens.
    public const SESSION_KEY = 'verso.admin_locale';

    public function __construct(
        protected Locales $locales,
        protected Settings $settings,
    ) {}

    public function forRequest(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $this->forUser($user);
        }

        return $this->forGuest($request);
    }

    public function forUser(User $user): string
    {
        return $this->locales->normalize($user->admin_locale) ?? $this->siteDefault();
    }

    public function forGuest(Request $request): string
    {
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return $this->locales->normalize(is_string($chosen) ? $chosen : null)
            ?? $this->locales->fromAcceptLanguage($request->header('Accept-Language'))
            ?? $this->siteDefault();
    }

    // The language of the site content as set in settings, like "nl" or "pt-BR".
    public function contentLanguage(): string
    {
        try {
            $site = $this->settings->get('site.locale');
        } catch (Throwable) {
            $site = null;
        }

        return is_string($site) && $site !== '' ? str_replace('_', '-', $site) : $this->locales->fallback();
    }

    // The site language when the admin has a translation for it, else the fallback language.
    public function siteDefault(): string
    {
        try {
            $site = $this->settings->get('site.locale');
        } catch (Throwable) {
            // Before install the settings table does not exist.
            $site = null;
        }

        return $this->locales->normalize(is_string($site) ? $site : null) ?? $this->locales->fallback();
    }
}

<?php

namespace App\Verso\Easy\Simple;

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Settings\Settings;

// Simple admin mode: fewer menus and plain words. Each person can switch it; the site default applies until then.
class SimpleMode
{
    // Site default for people who never switched: true on new sites, missing on older ones.
    public const DEFAULT_SETTING = 'easy.simple_mode_default';

    public function __construct(protected Settings $settings) {}

    // True when this person sees the simple admin.
    public function isOn(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return AdminPreferences::of($user)->simpleMode() ?? $this->siteDefault();
    }

    // The site default, or the config fallback for sites that never saved one.
    public function siteDefault(): bool
    {
        $value = $this->settings->get(self::DEFAULT_SETTING);

        return is_bool($value) ? $value : (bool) config('verso.easy.simple_mode.default');
    }

    public function hasSiteDefault(): bool
    {
        return is_bool($this->settings->get(self::DEFAULT_SETTING));
    }

    public function setSiteDefault(bool $enabled): void
    {
        $this->settings->set(self::DEFAULT_SETTING, $enabled);
    }

    // Saves the default for new sites (install and first starter site), but never changes a saved choice.
    public function rememberNewSiteDefault(): void
    {
        if (! $this->hasSiteDefault()) {
            $this->setSiteDefault((bool) config('verso.easy.simple_mode.new_sites'));
        }
    }

    public function set(User $user, bool $enabled): void
    {
        AdminPreferences::of($user)->setSimpleMode($enabled);
    }
}

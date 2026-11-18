<?php

namespace App\Verso\Access\Support;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\CustomCode\CustomCodeAccess;
use Illuminate\Support\Facades\Gate;

// Gate abilities for routes that need one of several capabilities. Route files name them in "can:".
final class AccessAbilities
{
    // Site wide custom CSS or JavaScript.
    public const CUSTOM_CODE = 'verso.custom-code';

    // Install or remove plugins or themes.
    public const INSTALL_EXTENSIONS = 'verso.install-extensions';

    // Theme builder layouts: templates, headers and footers, or popups.
    public const LAYOUTS = 'verso.layouts';

    public static function define(): void
    {
        Gate::define(self::LAYOUTS, fn (User $user): bool => $user->hasCapability(Capability::EditTemplates) || $user->hasCapability(Capability::EditPopups));

        Gate::define(self::CUSTOM_CODE, function (User $user): bool {
            $access = app(CustomCodeAccess::class);

            return $access->css($user) || $access->js($user);
        });

        Gate::define(self::INSTALL_EXTENSIONS, fn (User $user): bool => $user->hasCapability(Capability::InstallPlugins) || $user->hasCapability(Capability::ManageThemes));
    }
}

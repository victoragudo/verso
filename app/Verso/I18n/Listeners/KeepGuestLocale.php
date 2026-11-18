<?php

namespace App\Verso\I18n\Listeners;

use App\Models\User;
use App\Verso\I18n\Actions\SetAdminLocale;
use App\Verso\I18n\AdminLocale;
use Illuminate\Auth\Events\Login;

// A language picked on the login screen becomes the user's admin language, when the user has none yet.
class KeepGuestLocale
{
    public function __construct(protected SetAdminLocale $setLocale) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->admin_locale !== null || ! app()->bound('session.store')) {
            return;
        }

        $chosen = app('session.store')->pull(AdminLocale::SESSION_KEY);

        if (is_string($chosen) && $chosen !== '') {
            $this->setLocale->handle($user, $chosen);
        }
    }
}

<?php

namespace App\Verso\I18n\Actions;

use App\Models\User;
use App\Verso\I18n\Locales;

// Saves the admin language of a user. Null means "follow the site language".
class SetAdminLocale
{
    public function __construct(protected Locales $locales) {}

    public function handle(User $user, ?string $locale): void
    {
        $user->admin_locale = $this->locales->normalize($locale);
        $user->save();
    }
}

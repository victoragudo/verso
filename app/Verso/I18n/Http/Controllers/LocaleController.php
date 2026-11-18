<?php

namespace App\Verso\I18n\Http\Controllers;

use App\Models\User;
use App\Verso\I18n\Actions\SetAdminLocale;
use App\Verso\I18n\AdminLocale;
use App\Verso\I18n\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

// Saves the admin language: on the user when signed in, in the session for guests.
class LocaleController
{
    public function update(UpdateLocaleRequest $request, SetAdminLocale $setLocale): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $setLocale->handle($user, $request->locale());
        } else {
            $request->session()->put(AdminLocale::SESSION_KEY, $request->locale());
        }

        return back(303);
    }
}

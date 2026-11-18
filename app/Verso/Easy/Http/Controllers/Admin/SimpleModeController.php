<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Easy\Simple\SimpleMode;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Turns the simple admin mode on or off for the person who is signed in.
class SimpleModeController extends Controller
{
    public function update(Request $request, #[CurrentUser] User $user, SimpleMode $simpleMode): RedirectResponse
    {
        $enabled = (bool) $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        $simpleMode->set($user, $enabled);
        Inertia::flash('toast', ['type' => 'success', 'message' => $enabled
            ? __('Simple mode is on. You see the tools most people need.')
            : __('All tools are shown. You can go back to simple mode any time.')]);

        return back();
    }
}

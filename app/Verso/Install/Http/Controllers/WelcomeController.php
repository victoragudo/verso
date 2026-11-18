<?php

namespace App\Verso\Install\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

// The one-time link at the end of the install: signs the new admin in and opens the guided start (or the dashboard).
class WelcomeController extends Controller
{
    public function __invoke(Request $request, InstallState $state, InstallProgress $progress, EasyState $easy): RedirectResponse
    {
        $user = User::query()->find((int) $request->route('user'));

        abort_unless($state->isLocked() && $user !== null && $progress->consumeLogin((int) $user->getKey(), (string) $request->query('nonce')), 403);

        Auth::login($user);
        $request->session()->regenerate();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verso is installed. Welcome to your new site!')]);

        if ((bool) config('verso.easy.guided_start_after_install') && ! $easy->hasStarter()) {
            return to_route('admin.easy.start');
        }

        return to_route('admin.dashboard');
    }
}

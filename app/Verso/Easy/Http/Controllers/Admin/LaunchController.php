<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Actions\CreatePrivacyPage;
use App\Verso\Easy\Actions\GoLive;
use App\Verso\Easy\Launch\LaunchChecklistCard;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

// The buttons of the launch checklist: create a privacy page, go live and close the card.
class LaunchController extends Controller
{
    // Makes the privacy page (or finds the one there is) and opens it in the page editor.
    public function privacy(#[CurrentUser] User $user, CreatePrivacyPage $create): RedirectResponse
    {
        Gate::authorize('create', Entry::class);

        $page = $create->handle($user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your privacy page is ready. Read it and change what does not fit your site.')]);

        return to_route('admin.easy.pages.edit', $page);
    }

    public function goLive(GoLive $goLive): RedirectResponse
    {
        $goLive->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your site is live. Search engines may now find it.')]);

        return back();
    }

    public function dismiss(#[CurrentUser] User $user, LaunchChecklistCard $card): RedirectResponse
    {
        if (! $card->dismiss($user)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Finish every step first. Then you can hide the checklist.')]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The launch checklist is hidden. Well done!')]);

        return back();
    }
}

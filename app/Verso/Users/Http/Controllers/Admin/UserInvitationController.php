<?php

namespace App\Verso\Users\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Users\Actions\InviteUser;
use App\Verso\Users\Actions\SendInvitation;
use App\Verso\Users\Http\Requests\InviteUserRequest;
use App\Verso\Users\Support\Invitations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

// Invites new users by email and sends invitations again.
class UserInvitationController extends Controller
{
    public function store(InviteUserRequest $request, InviteUser $inviteUser, #[CurrentUser] User $admin): RedirectResponse
    {
        $invited = $inviteUser->handle($request->invitationData(), $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invited->email])]);

        return back();
    }

    public function resend(SendInvitation $sendInvitation, Invitations $invitations, #[CurrentUser] User $admin, User $user): RedirectResponse
    {
        Gate::authorize('create', User::class);
        abort_unless($invitations->isPending($user), 404);

        $sendInvitation->handle($user, $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again to :email.', ['email' => $user->email])]);

        return back();
    }
}

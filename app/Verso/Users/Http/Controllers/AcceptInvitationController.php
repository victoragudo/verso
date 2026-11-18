<?php

namespace App\Verso\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Users\Actions\AcceptInvitation;
use App\Verso\Users\Http\Requests\AcceptInvitationRequest;
use App\Verso\Users\Support\Invitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

// Public page where an invited user chooses a password.
class AcceptInvitationController extends Controller
{
    public function __construct(protected Invitations $invitations) {}

    public function show(Request $request, User $user, string $token): Response
    {
        $this->ensureValid($user, $token);

        return Inertia::render('users/accept-invitation', [
            'name' => $user->name,
            'email' => $user->email,
            'submitUrl' => $request->fullUrl(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, AcceptInvitation $acceptInvitation, User $user, string $token): RedirectResponse
    {
        $this->ensureValid($user, $token);

        $acceptInvitation->handle($user, (string) $request->validated('password'));

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome! Your account is ready.')]);

        return to_route('admin.dashboard');
    }

    protected function ensureValid(User $user, string $token): void
    {
        abort_unless($this->invitations->isValid($user, $token), 403, __('This invitation is no longer valid.'));
    }
}

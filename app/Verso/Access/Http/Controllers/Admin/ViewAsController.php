<?php

namespace App\Verso\Access\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Access\Support\ViewAs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Starts and ends "view as role": an administrator sees the admin like someone with that role, read only.
class ViewAsController extends Controller
{
    public function __construct(protected AccessAudit $audit) {}

    public function start(Request $request, #[CurrentUser] User $user, RoleDefinition $role): RedirectResponse
    {
        abort_unless($user->isAdmin() && ! $role->locked, 403);

        $request->session()->put(ViewAs::SESSION_KEY, $role->slug);
        $this->audit->log($user, AccessAudit::VIEW_AS, $role->slug, null, 'start', ['name' => $role->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You now see the admin as :name. Nothing can be changed until you exit.', ['name' => $role->displayName()])]);

        return to_route('admin.dashboard');
    }

    public function stop(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $role = $request->session()->pull(ViewAs::SESSION_KEY);

        if (! is_string($role)) {
            return to_route('admin.dashboard');
        }

        $this->audit->log($user, AccessAudit::VIEW_AS, $role, 'start', 'stop');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The preview has ended.')]);

        return to_route('admin.roles.show', $role);
    }
}

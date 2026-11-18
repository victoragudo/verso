<?php

namespace App\Verso\Access\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Actions\SaveUserAccess;
use App\Verso\Access\Http\Presenters\RolePresenter;
use App\Verso\Access\Http\Requests\UserAccessRequest;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use App\Verso\System\Models\ConfigChange;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// The access page of one user: main role, extra roles and single grants or denies.
class UserAccessController extends Controller
{
    public function __construct(protected RolePresenter $presenter) {}

    public function edit(#[CurrentUser] User $viewer, User $user, AccessAudit $audit): Response
    {
        Gate::authorize('promote', $user);

        return Inertia::render('admin/access/users/access', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'extraRoles' => $user->extraRoles(),
                'overrides' => $user->capabilityOverrides(),
                'effective' => $user->capabilityNames(),
                'isAdmin' => $user->isAdmin(),
                'isSelf' => $user->is($viewer),
            ],
            'mainRoles' => array_map(fn (Role $role): string => $role->value, app(OptionalRoles::class)->available()),
            'roles' => $this->presenter->roles($viewer),
            'capabilities' => $this->presenter->capabilities(),
            'grantable' => $this->presenter->grantable($viewer),
            'audit' => ConfigChange::query()->with('user:id,name')
                ->where('action', AccessAudit::USER_ACCESS)
                ->where('key', AccessAudit::userKey($user))
                ->latest('id')
                ->limit(max(1, (int) config('verso.access.audit_limit')))
                ->get()
                ->map(fn (ConfigChange $change): array => AccessAudit::present($change))
                ->values()->all(),
        ]);
    }

    public function update(UserAccessRequest $request, SaveUserAccess $saveUserAccess, #[CurrentUser] User $viewer, User $user): RedirectResponse
    {
        $saveUserAccess->handle($viewer, $user, $request->role(), $request->list('extra_roles'), $request->list('grants'), $request->list('denies'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The access of :name was saved.', ['name' => $user->name])]);

        return back();
    }
}

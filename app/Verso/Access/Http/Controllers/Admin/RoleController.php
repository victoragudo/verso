<?php

namespace App\Verso\Access\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Actions\CloneRole;
use App\Verso\Access\Actions\DeleteRole;
use App\Verso\Access\Actions\ResetRole;
use App\Verso\Access\Actions\SaveRole;
use App\Verso\Access\Http\Presenters\RolePresenter;
use App\Verso\Access\Http\Requests\RoleMatrixRequest;
use App\Verso\Access\Http\Requests\RoleRequest;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Core\Enums\Role;
use App\Verso\I18n\Translate;
use App\Verso\System\Models\ConfigChange;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// The roles admin: the matrix of every role and capability, one page per role, and a compare view.
class RoleController extends Controller
{
    public function __construct(
        protected Roles $roles,
        protected RolePresenter $presenter,
        protected AccessAudit $audit,
    ) {}

    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('admin/access/roles/index', [
            'roles' => $this->presenter->roles($user),
            'capabilities' => $this->presenter->capabilities(),
            'grantable' => $this->presenter->grantable($user),
            'audit' => $this->auditRows(null),
        ]);
    }

    public function store(RoleRequest $request, SaveRole $saveRole, #[CurrentUser] User $user): RedirectResponse
    {
        $from = $request->validated('from');
        $source = is_string($from) ? $this->roles->find($from) : null;

        $role = $saveRole->handle($user, null, [
            'slug' => $request->slug(),
            'name' => (string) $request->validated('name'),
            'description' => $request->validated('description'),
            'capabilities' => $source !== null ? $source->capabilities : $request->capabilities(),
            'based_on' => $source?->slug,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The role :name was created.', ['name' => $role->displayName()])]);

        return to_route('admin.roles.show', $role->slug);
    }

    public function updateMatrix(RoleMatrixRequest $request, SaveRole $saveRole, #[CurrentUser] User $user): RedirectResponse
    {
        $saved = 0;

        foreach ($request->changes() as $slug => $capabilities) {
            $role = $this->roles->find($slug);

            if ($role === null || $role->locked || $this->sameList($role->capabilities, $capabilities)) {
                continue;
            }

            $saveRole->handle($user, $role, ['name' => $role->name, 'description' => $role->description, 'capabilities' => $capabilities]);
            $saved++;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('{count, plural, one {One role was saved.} other {# roles were saved.}}', ['count' => $saved])]);

        return back();
    }

    public function show(Request $request, #[CurrentUser] User $user, RoleDefinition $role): Response
    {
        $defaults = $this->roles->defaults($role->slug);

        return Inertia::render('admin/access/roles/show', [
            'role' => $this->presenter->role($role, $user),
            'defaults' => $defaults?->capabilities,
            'capabilities' => $this->presenter->capabilities(),
            'grantable' => $this->presenter->grantable($user),
            'roles' => array_map(fn (RoleDefinition $other): array => ['slug' => $other->slug, 'name' => $other->displayName()], array_values($this->roles->all())),
            'members' => $this->members($role),
            'audit' => $this->auditRows($role->slug),
            'canViewAs' => $user->isAdmin() && ! $role->locked,
        ]);
    }

    public function update(RoleRequest $request, SaveRole $saveRole, #[CurrentUser] User $user, RoleDefinition $role): RedirectResponse
    {
        $saved = $saveRole->handle($user, $role, [
            'name' => (string) $request->validated('name'),
            'description' => $request->validated('description'),
            'capabilities' => $request->has('capabilities') ? $request->capabilities() : $role->capabilities,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was saved.', ['name' => $saved->displayName()])]);

        return back();
    }

    public function clone(Request $request, CloneRole $cloneRole, #[CurrentUser] User $user, RoleDefinition $role): RedirectResponse
    {
        $copy = $cloneRole->handle($user, $role, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was created from :source.', ['name' => $copy->displayName(), 'source' => $role->displayName()])]);

        return to_route('admin.roles.show', $copy->slug);
    }

    public function reset(ResetRole $resetRole, #[CurrentUser] User $user, RoleDefinition $role): RedirectResponse
    {
        $resetRole->handle($user, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name has its default capabilities again.', ['name' => $role->displayName()])]);

        return back();
    }

    public function destroy(DeleteRole $deleteRole, #[CurrentUser] User $user, RoleDefinition $role): RedirectResponse
    {
        $users = $deleteRole->handle($user, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('{count, plural, =0 {{name} was deleted.} one {{name} was deleted and taken away from # user.} other {{name} was deleted and taken away from # users.}}', ['count' => $users, 'name' => $role->displayName()])]);

        return to_route('admin.roles.index');
    }

    public function compare(Request $request, #[CurrentUser] User $user): Response
    {
        $wanted = array_values(array_filter(
            array_map('trim', explode(',', $request->string('roles')->toString())),
            fn (string $slug): bool => $this->roles->exists($slug),
        ));
        $limit = max(2, (int) config('verso.access.compare_limit'));
        $wanted = array_slice($wanted === [] ? [Role::Editor->value, Role::Author->value] : array_values(array_unique($wanted)), 0, $limit);

        return Inertia::render('admin/access/roles/compare', [
            'selected' => $wanted,
            'roles' => $this->presenter->roles($user),
            'capabilities' => $this->presenter->capabilities(),
            'limit' => $limit,
        ]);
    }

    /**
     * People who have the role, as main role or extra role.
     *
     * @return array{total: int, items: list<array{id: int, name: string, email: string, main: bool}>}
     */
    protected function members(RoleDefinition $role): array
    {
        $query = User::query()->withRole($role->slug);
        $limit = max(1, (int) config('verso.access.members_limit'));

        return [
            'total' => (clone $query)->count(),
            'items' => array_values($query->orderBy('name')->orderBy('id')->limit($limit)->get(['id', 'name', 'email', 'role'])
                ->map(fn (User $member): array => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'main' => $member->role->value === $role->slug])
                ->all()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function auditRows(?string $role): array
    {
        return array_values($this->audit->forRole($role, (int) config('verso.access.audit_limit'))
            ->map(fn (ConfigChange $change): array => AccessAudit::present($change))
            ->all());
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected function sameList(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}

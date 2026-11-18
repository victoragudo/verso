<?php

namespace App\Verso\Users\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Http\Presenters\RolePresenter;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\EscalationGuard;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use App\Verso\Users\Actions\ChangeUserRole;
use App\Verso\Users\Actions\CreateUser;
use App\Verso\Users\Actions\DeleteUser;
use App\Verso\Users\Http\Requests\DeleteUserRequest;
use App\Verso\Users\Http\Requests\StoreUserRequest;
use App\Verso\Users\Http\Requests\UpdateUserRoleRequest;
use App\Verso\Users\Support\Invitations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin page to list, create, change the role of and delete users.
class UserController extends Controller
{
    public function __construct(
        protected Invitations $invitations,
        protected Roles $roles,
        protected EscalationGuard $guard,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        Gate::authorize('viewAny', User::class);

        $role = $this->roles->exists((string) $request->query('role')) ? (string) $request->query('role') : null;
        $search = trim((string) $request->query('search'));

        $users = $this->filtered($role, $search)
            ->whereNull('invited_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString()
            ->through(fn (User $row): array => $this->row($row, $user));

        $invitations = $this->filtered($role, $search)
            ->whereNotNull('invited_at')
            ->latest('invited_at')
            ->orderBy('id')
            ->limit(max((int) config('verso.users.pending_invitations_limit'), 1))
            ->get()
            ->map(fn (User $row): array => $this->row($row, $user))
            ->all();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'invitations' => $invitations,
            'filters' => ['role' => $role, 'search' => $search],
            'counts' => $this->roleCounts(),
            'roleTabs' => $this->roleTabs(),
            'can' => ['create' => $user->can('create', User::class)],
            'listView' => AdminPreferences::of($user)->listViewProps('users'),
            // Main roles this user may give (never more than they have themselves).
            'roles' => array_values(array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $this->roles->find($role->value)?->displayName() ?? $role->label()],
                array_filter(app(OptionalRoles::class)->available(), fn (Role $role): bool => $this->guard->canAssignRole($user, $role->value)),
            )),
            'reassignOptions' => User::query()
                ->where('role', '!=', Role::Subscriber)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $option): array => ['id' => $option->id, 'name' => $option->name])
                ->all(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $created = $createUser->handle($request->userData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was added.', ['name' => $created->name])]);

        return back();
    }

    public function updateRole(UpdateUserRoleRequest $request, ChangeUserRole $changeUserRole, User $user): RedirectResponse
    {
        $changeUserRole->handle($user, $request->role(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now :role.', ['name' => $user->name, 'role' => $user->role->label()])]);

        return back();
    }

    public function destroy(DeleteUserRequest $request, DeleteUser $deleteUser, User $user): RedirectResponse
    {
        $deleteUser->handle($user, $request->reassignTo());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Users with their entry count, filtered by role and by a name or email search.
     *
     * @return Builder<User>
     */
    protected function filtered(?string $role, string $search): Builder
    {
        return User::query()
            ->select('users.*')
            ->selectSub(DB::table('entries')->selectRaw('count(*)')->whereColumn('entries.author_id', 'users.id'), 'entries_count')
            ->when($role !== null, fn (Builder $query) => $query->withRole((string) $role))
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['name', 'email'], $search));
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(User $row, User $viewer): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'role' => $row->role->value,
            'role_label' => $this->roles->find($row->role->value)->name ?? $row->role->label(),
            // Main role first, then extra roles.
            'roles' => array_map(fn (string $slug): array => ['slug' => $slug, 'name' => $this->roles->find($slug)->name ?? $slug], $row->roleSlugs()),
            'can' => [
                'promote' => $viewer->can('promote', $row),
                'delete' => $viewer->can('delete', $row),
            ],
            'invited' => $this->invitations->isPending($row),
            'entries_count' => (int) $row->getAttribute('entries_count'),
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
            'two_factor' => $row->two_factor_confirmed_at !== null,
            'is_self' => $row->is($viewer),
        ];
    }

    /**
     * Number of users for each role, plus "all".
     *
     * @return array<string, int>
     */
    protected function roleCounts(): array
    {
        $counts = app(RolePresenter::class)->userCounts();
        $result = ['all' => User::query()->count()];

        foreach ($this->roles->slugs() as $slug) {
            $result[$slug] = $counts[$slug] ?? 0;
        }

        return $result;
    }

    /**
     * Filter tabs: built-in roles always, other roles once someone has them.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function roleTabs(): array
    {
        $counts = app(RolePresenter::class)->userCounts();

        return array_values(array_map(
            fn (RoleDefinition $role): array => ['value' => $role->slug, 'label' => $role->displayName()],
            array_filter($this->roles->all(), fn (RoleDefinition $role): bool => Role::tryFrom($role->slug) !== null || ($counts[$role->slug] ?? 0) > 0),
        ));
    }
}

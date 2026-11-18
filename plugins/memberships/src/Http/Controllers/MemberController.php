<?php

namespace Verso\Plugins\Memberships\Http\Controllers;

use App\Models\User;
use App\Verso\Core\Support\SiteTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Memberships\Actions\AssignMembership;
use Verso\Plugins\Memberships\Enums\MembershipStatus;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

// Admin page that lists members and assigns plans by hand.
class MemberController
{
    // Status filters shown as tabs.
    protected const FILTERS = ['all', 'active', 'expired', 'cancelled'];

    public function __construct(protected SiteTime $time) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));

        $members = Membership::query()
            ->with(['user:id,name,email', 'plan:id,name,level'])
            ->when($status === 'active', fn (Builder $query) => $query->active())
            ->when($status === 'expired', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('status', MembershipStatus::Expired)->orWhere(fn (Builder $q) => $q->due())))
            ->when($status === 'cancelled', fn (Builder $query) => $query->where('status', MembershipStatus::Cancelled))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate((int) config('memberships.per_page'))
            ->withQueryString()
            ->through(fn (Membership $membership): array => $this->row($membership));

        return Inertia::render('admin/plugins/memberships/members', [
            'members' => $members,
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => [
                'all' => Membership::query()->count(),
                'active' => Membership::query()->active()->count(),
                'expired' => Membership::query()->where(fn (Builder $q) => $q->where('status', MembershipStatus::Expired)->orWhere(fn (Builder $q) => $q->due()))->count(),
                'cancelled' => Membership::query()->where('status', MembershipStatus::Cancelled)->count(),
            ],
            'plans' => Plan::query()->orderBy('level')->get(['id', 'name', 'level']),
            'urls' => [
                'index' => route('admin.memberships.members.index'),
                'store' => route('admin.memberships.members.store'),
                'member' => route('admin.memberships.members.update', ['membership' => '__ID__']),
                'plans' => route('admin.memberships.plans.index'),
            ],
        ]);
    }

    public function store(Request $request, AssignMembership $assign): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', Rule::exists(User::class, 'email')],
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['email.exists' => __('memberships::No user has this email address. Invite them first on the Users page.')]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $plan = Plan::query()->findOrFail((int) $data['plan_id']);

        $assign->handle($user, $plan, $this->endOfDay($data['expires_on'] ?? null), $data['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships:::user is now a member of :plan.', ['user' => $user->name, 'plan' => $plan->name])]);

        return back();
    }

    // Change the end date or the status of a membership.
    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $data = $request->validate([
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(MembershipStatus::class)],
        ]);

        $membership->update([
            'expires_at' => $this->endOfDay($data['expires_on'] ?? null),
            'status' => $data['status'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Membership updated.')]);

        return back();
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        $membership->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Membership removed.')]);

        return back();
    }

    // A date typed in the site time zone means "until the end of that day".
    protected function endOfDay(?string $date): ?CarbonImmutable
    {
        return $date === null ? null : $this->time->toUtc($date.' 23:59:59');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'user' => ['id' => $membership->user->id, 'name' => $membership->user->name, 'email' => $membership->user->email],
            'plan' => ['id' => $membership->plan->id, 'name' => $membership->plan->name],
            'status' => $membership->status->value,
            'active' => $membership->isActive(),
            'starts_at' => $membership->starts_at?->toIso8601String(),
            'expires_at' => $membership->expires_at?->toIso8601String(),
            'expires_on' => $membership->expires_at !== null ? $this->time->local($membership->expires_at)->format('Y-m-d') : null,
            'note' => $membership->note,
        ];
    }
}

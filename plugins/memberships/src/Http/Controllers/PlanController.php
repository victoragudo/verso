<?php

namespace Verso\Plugins\Memberships\Http\Controllers;

use App\Verso\Core\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Memberships\MembershipAccess;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

// Admin page for membership plans and the teaser settings.
class PlanController
{
    public function __construct(protected Settings $settings) {}

    public function index(): Response
    {
        return Inertia::render('admin/plugins/memberships/plans', [
            'plans' => Plan::query()->orderBy('level')->orderBy('name')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'level' => $plan->level,
                'members' => $plan->memberships()->active()->count(),
            ]),
            'settings' => [
                'join_url' => (string) $this->settings->get('memberships.join_url', config('memberships.join_url')),
                'teaser_message' => (string) $this->settings->get('memberships.teaser_message', config('memberships.teaser_message')),
            ],
            'urls' => [
                'store' => route('admin.memberships.plans.store'),
                'plan' => route('admin.memberships.plans.update', ['plan' => '__ID__']),
                'settings' => route('admin.memberships.settings.update'),
                'members' => route('admin.memberships.members.index'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Plan :name created.', ['name' => $plan->name])]);

        return back();
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Plan :name saved.', ['name' => $plan->name])]);

        return back();
    }

    // Plans with active members cannot be deleted, so nobody loses access by mistake.
    public function destroy(Plan $plan): RedirectResponse
    {
        if (Membership::query()->active()->where('plan_id', $plan->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('memberships:::name still has active members. Move or cancel them first.', ['name' => $plan->name])]);

            return back();
        }

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Plan :name deleted.', ['name' => $plan->name])]);

        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'join_url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/'],
            'teaser_message' => ['required', 'string', 'max:500'],
        ]);

        $this->settings->set('memberships.join_url', (string) ($data['join_url'] ?? ''));
        $this->settings->set('memberships.teaser_message', $data['teaser_message']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('memberships::Teaser saved.')]);

        return back();
    }

    /**
     * @return array{name: string, slug: string, description: string|null, level: int}
     */
    protected function validated(Request $request, ?Plan $plan = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', Rule::notIn([MembershipAccess::EVERYONE, MembershipAccess::ANY_MEMBER]), Rule::unique(Plan::class, 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'level' => ['required', 'integer', 'min:1', 'max:1000'],
        ], ['slug.not_in' => __('memberships::This name is reserved. Choose another one.')]);

        return [
            'name' => (string) $data['name'],
            'slug' => (string) $data['slug'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'level' => (int) $data['level'],
        ];
    }
}

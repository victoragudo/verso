<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Memberships\Enums\MembershipStatus;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('memberships');
    $this->plans = membershipPlans();
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Saraiva']);
});

it('lists members with counts and filters', function () {
    memberOf($this->plans['reader'], 'Pedro Antunes');
    $expired = memberOf($this->plans['patron'], 'Lucia Barros');
    Membership::query()->where('user_id', $expired->id)->update(['expires_at' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->get(route('admin.memberships.members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/memberships/members', false)
            ->has('members.data', 2)
            ->where('counts', ['all' => 2, 'active' => 1, 'expired' => 1, 'cancelled' => 0])
            ->has('plans', 2)
        );

    $this->actingAs($this->admin)
        ->get(route('admin.memberships.members.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.user.name', 'Pedro Antunes')
            ->where('members.data.0.active', true)
        );

    $this->actingAs($this->admin)
        ->get(route('admin.memberships.members.index', ['search' => 'lucia']))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.plan.name', 'Patron'));
});

it('assigns a plan by email with an end date', function () {
    $user = User::factory()->create(['name' => 'Rui Moreira', 'email' => 'rui.moreira@example.pt']);

    $this->actingAs($this->admin)->post(route('admin.memberships.members.store'), [
        'email' => 'rui.moreira@example.pt',
        'plan_id' => $this->plans['patron']->id,
        'expires_on' => now()->addYear()->format('Y-m-d'),
        'note' => 'Conference speaker',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $membership = Membership::query()->firstWhere('user_id', $user->id);

    expect($membership->plan_id)->toBe($this->plans['patron']->id)
        ->and($membership->note)->toBe('Conference speaker')
        ->and($membership->expires_at->isFuture())->toBeTrue();
});

it('rejects unknown emails and past end dates', function () {
    $this->actingAs($this->admin)->post(route('admin.memberships.members.store'), [
        'email' => 'nobody@example.pt',
        'plan_id' => $this->plans['reader']->id,
        'expires_on' => now()->subDays(3)->format('Y-m-d'),
    ])->assertSessionHasErrors(['email', 'expires_on']);

    expect(Membership::query()->count())->toBe(0);
});

it('changes the end date and the status of a membership', function () {
    memberOf($this->plans['reader']);
    $membership = Membership::query()->first();

    $this->actingAs($this->admin)->put(route('admin.memberships.members.update', $membership), [
        'expires_on' => '2027-03-31',
        'status' => 'cancelled',
    ])->assertSessionHasNoErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Cancelled)
        ->and($membership->fresh()->expires_at)->not->toBeNull();
});

it('removes a membership', function () {
    memberOf($this->plans['reader']);

    $this->actingAs($this->admin)->delete(route('admin.memberships.members.destroy', Membership::query()->first()))->assertRedirect();

    expect(Membership::query()->count())->toBe(0);
});

it('keeps the pages for users who can manage users', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.memberships.members.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.memberships.plans.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.memberships.plans.store'), ['name' => 'Gold', 'level' => 3])->assertForbidden();
});

it('lists plans with their active members and the teaser settings', function () {
    memberOf($this->plans['reader']);

    $this->actingAs($this->admin)
        ->get(route('admin.memberships.plans.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/memberships/plans', false)
            ->where('plans.0.name', 'Reader')
            ->where('plans.0.members', 1)
            ->where('plans.1.members', 0)
            ->where('settings.teaser_message', 'This content is for members only.')
        );
});

it('creates, updates and deletes plans', function () {
    $this->actingAs($this->admin)->post(route('admin.memberships.plans.store'), [
        'name' => 'Supporter',
        'description' => 'Helps us pay the photographers.',
        'level' => 3,
    ])->assertSessionHasNoErrors();

    $plan = Plan::query()->firstWhere('slug', 'supporter');
    expect($plan->level)->toBe(3);

    $this->actingAs($this->admin)->put(route('admin.memberships.plans.update', $plan), [
        'name' => 'Supporter',
        'slug' => 'supporter',
        'level' => 4,
    ])->assertSessionHasNoErrors();
    expect($plan->fresh()->level)->toBe(4);

    $this->actingAs($this->admin)->delete(route('admin.memberships.plans.destroy', $plan))->assertRedirect();
    expect(Plan::query()->find($plan->id))->toBeNull();
});

it('refuses reserved and duplicate plan slugs', function (string $name) {
    $this->actingAs($this->admin)->post(route('admin.memberships.plans.store'), ['name' => $name, 'level' => 2])
        ->assertSessionHasErrors('slug');
})->with(['Members', 'Everyone', 'Reader']);

it('does not delete a plan that still has active members', function () {
    memberOf($this->plans['reader']);

    $this->actingAs($this->admin)->delete(route('admin.memberships.plans.destroy', $this->plans['reader']))->assertRedirect();

    expect(Plan::query()->find($this->plans['reader']->id))->not->toBeNull();
});

it('saves the teaser settings', function () {
    $this->actingAs($this->admin)->put(route('admin.memberships.settings.update'), [
        'join_url' => '/membership',
        'teaser_message' => 'Join the Harbor Club to keep reading.',
    ])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('memberships.join_url'))->toBe('/membership')
        ->and(app(Settings::class)->get('memberships.teaser_message'))->toBe('Join the Harbor Club to keep reading.');

    $this->actingAs($this->admin)->put(route('admin.memberships.settings.update'), [
        'join_url' => 'javascript:alert(1)',
        'teaser_message' => '',
    ])->assertSessionHasErrors(['join_url', 'teaser_message']);
});

it('adds a Members item to the admin nav', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.memberships.plans.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->flatten(2)->contains('memberships') || str_contains(json_encode($nav), '"memberships"')));
});

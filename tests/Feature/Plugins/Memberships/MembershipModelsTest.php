<?php

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\Event;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Memberships\Actions\AssignMembership;
use Verso\Plugins\Memberships\Enums\MembershipStatus;
use Verso\Plugins\Memberships\Events\MembershipGranted;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('memberships');
    $this->plans = membershipPlans();
});

it('ships a valid manifest that asks for the manage users and manage settings capabilities', function () {
    $plugin = PluginFixtures::use(base_path('plugins'))->get('memberships');

    expect($plugin->canBeEnabled())->toBeTrue()
        ->and($plugin->manifest->permissions)->toBe([Capability::ManageUsers, Capability::ManageSettings]);
});

it('builds the plan slug from the name', function () {
    expect($this->plans['reader']->slug)->toBe('reader')
        ->and(Plan::query()->create(['name' => 'Founding Circle', 'level' => 9])->slug)->toBe('founding-circle')
        ->and(Plan::query()->create(['name' => 'Student', 'slug' => 'Student Rate', 'level' => 1])->slug)->toBe('student-rate');
});

it('lets higher plans cover lower ones', function () {
    expect($this->plans['patron']->covers($this->plans['reader']))->toBeTrue()
        ->and($this->plans['reader']->covers($this->plans['patron']))->toBeFalse()
        ->and($this->plans['reader']->covers($this->plans['reader']))->toBeTrue();
});

it('assigns a plan and sends an event', function () {
    Event::fake([MembershipGranted::class]);
    $user = User::factory()->create(['name' => 'Tomas Albuquerque']);

    $membership = app(AssignMembership::class)->handle($user, $this->plans['reader'], now()->addMonth(), 'Paid by bank transfer');

    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->note)->toBe('Paid by bank transfer')
        ->and($membership->isActive())->toBeTrue();
    Event::assertDispatched(MembershipGranted::class, fn (MembershipGranted $event) => $event->membership->is($membership));
});

it('renews an existing membership instead of adding a second one', function () {
    $user = memberOf($this->plans['reader']);
    Membership::query()->first()->expire();

    app(AssignMembership::class)->handle($user, $this->plans['reader'], now()->addYear());

    expect(Membership::query()->count())->toBe(1)
        ->and(Membership::query()->first()->status)->toBe(MembershipStatus::Active);
});

it('knows when a membership is active', function () {
    $user = User::factory()->create();
    $membership = Membership::query()->create(['user_id' => $user->id, 'plan_id' => $this->plans['reader']->id, 'status' => 'active', 'expires_at' => now()->addDay()]);

    expect($membership->isActive())->toBeTrue()
        ->and($membership->isActive(now()->addDays(2)))->toBeFalse();

    $membership->update(['starts_at' => now()->addHour()]);
    expect($membership->fresh()->isActive())->toBeFalse();

    $membership->update(['starts_at' => null]);
    $membership->cancel();
    expect($membership->fresh()->isActive())->toBeFalse()
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Cancelled);
});

it('finds active and due memberships with scopes', function () {
    $active = memberOf($this->plans['reader'], 'Rita Fonseca');
    $due = memberOf($this->plans['patron'], 'Joao Pires');
    Membership::query()->where('user_id', $due->id)->update(['expires_at' => now()->subMinute()]);

    expect(Membership::query()->active()->pluck('user_id')->all())->toBe([$active->id])
        ->and(Membership::query()->due()->pluck('user_id')->all())->toBe([$due->id]);
});

it('gives labels to statuses', function () {
    expect(MembershipStatus::Expired->label())->toBe('Expired');
});

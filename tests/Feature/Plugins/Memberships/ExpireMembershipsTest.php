<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Memberships\Actions\ExpireMemberships;
use Verso\Plugins\Memberships\Enums\MembershipStatus;
use Verso\Plugins\Memberships\Events\MembershipExpired;
use Verso\Plugins\Memberships\Models\Membership;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('memberships');
    $this->plans = membershipPlans();
});

it('expires memberships past their end date and sends events', function () {
    Event::fake([MembershipExpired::class]);
    $late = memberOf($this->plans['reader'], 'Nuno Teixeira');
    $current = memberOf($this->plans['patron'], 'Sofia Carvalho');
    Membership::query()->where('user_id', $late->id)->update(['expires_at' => now()->subHour()]);
    Membership::query()->where('user_id', $current->id)->update(['expires_at' => now()->addWeek()]);

    expect(app(ExpireMemberships::class)->handle())->toBe(1)
        ->and(Membership::query()->where('user_id', $late->id)->first()->status)->toBe(MembershipStatus::Expired)
        ->and(Membership::query()->where('user_id', $current->id)->first()->status)->toBe(MembershipStatus::Active);
    Event::assertDispatchedTimes(MembershipExpired::class, 1);
});

it('leaves memberships without an end date alone', function () {
    memberOf($this->plans['reader']);

    expect(app(ExpireMemberships::class)->handle())->toBe(0);
});

it('runs from the artisan command', function () {
    $user = memberOf($this->plans['reader']);
    Membership::query()->update(['expires_at' => now()->subDay()]);

    $this->artisan('memberships:expire')->expectsOutput('1 memberships expired.')->assertSuccessful();

    expect(Membership::query()->where('user_id', $user->id)->first()->status)->toBe(MembershipStatus::Expired);
});

it('schedules the command once a day', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'memberships:expire'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('15 0 * * *');
});

<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Memberships\Actions\AssignMembership;
use Verso\Plugins\Memberships\MembershipAccess;
use Verso\Plugins\Memberships\Models\Membership;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('memberships');
    $this->plans = membershipPlans();
    $this->access = app(MembershipAccess::class);
});

it('reads the requirement from the entry meta', function () {
    expect($this->access->requirement(new Entry(['meta' => ['membership' => ['plan' => 'patron']]])))->toBe('patron')
        ->and($this->access->requirement(new Entry(['meta' => ['membership' => ['plan' => 'everyone']]])))->toBeNull()
        ->and($this->access->requirement(new Entry(['meta' => ['membership' => ['plan' => '']]])))->toBeNull()
        ->and($this->access->requirement(new Entry(['meta' => []])))->toBeNull()
        ->and($this->access->isRestricted(new Entry(['meta' => ['membership' => ['plan' => 'members']]])))->toBeTrue();
});

it('lets everyone see public content', function () {
    expect($this->access->allows(null, null))->toBeTrue()
        ->and($this->access->allows(null, 'everyone'))->toBeTrue();
});

it('hides restricted content from guests and users without a plan', function () {
    $user = User::factory()->create(['name' => 'Beatriz Lopes']);

    expect($this->access->allows(null, 'members'))->toBeFalse()
        ->and($this->access->allows($user, 'members'))->toBeFalse()
        ->and($this->access->allows($user, 'reader'))->toBeFalse();
});

it('compares plan levels', function () {
    $reader = memberOf($this->plans['reader'], 'Helena Brito');
    $patron = memberOf($this->plans['patron'], 'Duarte Moniz');

    expect($this->access->allows($reader, 'members'))->toBeTrue()
        ->and($this->access->allows($reader, 'reader'))->toBeTrue()
        ->and($this->access->allows($reader, 'patron'))->toBeFalse()
        ->and($this->access->allows($patron, 'reader'))->toBeTrue()
        ->and($this->access->allows($patron, 'patron'))->toBeTrue();
});

it('treats an unknown plan as any member', function () {
    expect($this->access->allows(memberOf($this->plans['reader']), 'retired-plan'))->toBeTrue()
        ->and($this->access->allows(User::factory()->create(), 'retired-plan'))->toBeFalse();
});

it('ignores expired and cancelled memberships', function () {
    $user = memberOf($this->plans['patron']);
    Membership::query()->update(['expires_at' => now()->subDay()]);

    expect($this->access->levelFor($user))->toBeNull()
        ->and($this->access->isMember($user))->toBeFalse();

    Membership::query()->update(['expires_at' => null, 'status' => 'cancelled']);

    expect($this->access->allows($user, 'members'))->toBeFalse();
});

it('uses the highest active level', function () {
    $user = memberOf($this->plans['reader']);
    app(AssignMembership::class)->handle($user, $this->plans['patron']);

    expect($this->access->levelFor($user))->toBe(5);
});

it('lets editors and admins see everything', function () {
    expect($this->access->allows(User::factory()->editor()->create(), 'patron'))->toBeTrue()
        ->and($this->access->allows(User::factory()->admin()->create(), 'members'))->toBeTrue()
        ->and($this->access->allows(User::factory()->author()->create(), 'members'))->toBeFalse();
});

it('builds a teaser with join and login links', function () {
    app(Settings::class)->set('memberships.join_url', '/join');

    $html = $this->access->teaser();

    expect($html)->toContain('This content is for members only.')
        ->toContain('href="/join"')
        ->toContain('href="'.route('login').'"');
});

it('hides the join link when no url is set and uses a custom message', function () {
    $html = $this->access->teaser('Subscribe to read the full recipe.');

    expect($html)->toContain('Subscribe to read the full recipe.')
        ->not->toContain('Become a member');
});

it('hides the login link for signed in users', function () {
    $this->actingAs(User::factory()->create());

    expect($this->access->teaser())->not->toContain('Log in');
});

it('empties hidden members only blocks in raw block data', function () {
    $blocks = [
        ['id' => 'b_box', 'type' => 'members-only', 'attrs' => ['plan' => 'patron'], 'children' => [
            ['id' => 'b_p', 'type' => 'paragraph', 'content' => 'Print edition schedule'],
        ]],
        ['id' => 'b_cols', 'type' => 'columns', 'children' => [['id' => 'b_col', 'type' => 'column', 'children' => [
            ['id' => 'b_box2', 'type' => 'members-only', 'attrs' => [], 'children' => [['id' => 'b_p2', 'type' => 'paragraph']]],
        ]]]],
        'junk',
    ];

    $guest = $this->access->stripBlocks($blocks, null);
    $reader = $this->access->stripBlocks($blocks, memberOf($this->plans['reader']));

    expect($guest[0]['children'])->toBe([])
        ->and($guest[1]['children'][0]['children'][0]['children'])->toBe([])
        ->and($guest[2])->toBe('junk')
        ->and($reader[0]['children'])->toBe([])
        ->and($reader[1]['children'][0]['children'][0]['children'])->toHaveCount(1);
});

it('reads the requirement of a block', function () {
    expect($this->access->blockRequirement(['attrs' => ['plan' => 'patron']]))->toBe('patron')
        ->and($this->access->blockRequirement(['attrs' => ['plan' => '']]))->toBe('members')
        ->and($this->access->blockRequirement([]))->toBe('members');
});

<?php

use App\Models\User;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Core\Enums\Role;
use App\Verso\System\Models\ConfigChange;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Alice Moreira']);
});

it('shows every role of a user and what the viewer may do with each row', function () {
    customRole('fact_checker', ['read_private_entries'], 'Fact checker');
    giveExtraRoles(User::factory()->author()->create(['name' => 'Bruna Costa']), 'fact_checker');

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.name', 'Bruna Costa')
            ->where('users.data.1.roles', [['slug' => 'author', 'name' => 'Author'], ['slug' => 'fact_checker', 'name' => 'Fact checker']])
            ->where('users.data.1.can', ['promote' => true, 'delete' => true])
            ->where('users.data.0.can', ['promote' => true, 'delete' => false])
            ->where('can.create', true)
            ->where('counts.fact_checker', 1)
            ->where('roleTabs', fn ($tabs) => collect($tabs)->pluck('value')->all() === ['admin', 'editor', 'author', 'contributor', 'subscriber', 'fact_checker']));
});

it('filters by a custom role', function () {
    customRole('fact_checker', ['read_private_entries'], 'Fact checker');
    giveExtraRoles(User::factory()->author()->create(['name' => 'Bruna Costa']), 'fact_checker');
    User::factory()->author()->create(['name' => 'Carlos Dias']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'fact_checker']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('filters.role', 'fact_checker'));
});

it('lets someone with list_users see the list but change nothing', function () {
    $viewer = userWith(['list_users']);
    $target = User::factory()->author()->create();

    $this->actingAs($viewer)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.create', false)->where('roles', [['value' => 'subscriber', 'label' => 'Subscriber']]));

    $this->actingAs($viewer)->put(route('admin.users.role', $target), ['role' => 'editor'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.users.destroy', $target))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.users.store'), ['name' => 'Eva Rocha', 'email' => 'eva@verso.example', 'role' => 'author'])->assertForbidden();
});

it('never lets a promoter hand out more than they have', function () {
    $promoter = userWith(['promote_users', 'list_users', 'edit_own_entries', 'read_private_entries']);
    $target = User::factory()->create(['name' => 'Filipa Ramos']);

    $this->actingAs($promoter)->put(route('admin.users.role', $target), ['role' => 'admin'])->assertSessionHasErrors('role');
    $this->actingAs($promoter)->put(route('admin.users.role', $target), ['role' => 'author'])->assertSessionHasErrors('role');
    $this->actingAs($promoter)->put(route('admin.users.role', $target), ['role' => 'contributor'])->assertSessionHasNoErrors();

    expect($target->fresh()->role)->toBe(Role::Contributor)
        ->and(ConfigChange::query()->where('action', AccessAudit::USER_ROLE)->where('key', 'contributor')->value('user_id'))->toBe($promoter->id);
});

it('never lets a creator add administrators', function () {
    $creator = userWith(['create_users', 'list_users', 'edit_own_entries', 'read_private_entries']);

    $this->actingAs($creator)
        ->post(route('admin.users.store'), ['name' => 'Gil Matias', 'email' => 'gil@verso.example', 'password' => 'a-long-safe-password-42', 'role' => 'admin'])
        ->assertSessionHasErrors('role');
});

it('lets someone with delete_users delete people below them only', function () {
    $remover = userWith(['delete_users', 'list_users', 'edit_own_entries', 'read_private_entries']);
    $contributor = User::factory()->contributor()->create();

    $this->actingAs($remover)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    $this->actingAs($remover)->delete(route('admin.users.destroy', $contributor))->assertRedirect();

    expect(User::query()->find($contributor->id))->toBeNull();
});

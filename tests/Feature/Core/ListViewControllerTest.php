<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create(['name' => 'Joana Ferreira']);
});

it('saves the columns and density of a list for the user', function () {
    $this->actingAs($this->editor)
        ->putJson(route('admin.list-views.update', 'entries.post'), ['columns' => ['tax.category', 'word_count'], 'density' => 'compact'])
        ->assertNoContent();

    expect(AdminPreferences::of($this->editor->fresh())->listView('entries.post'))
        ->toBe(['columns' => ['tax.category', 'word_count'], 'density' => 'compact']);
});

it('goes back to the default columns with null', function () {
    AdminPreferences::of($this->editor)->saveListView('comments', ['website'], 'compact');

    $this->actingAs($this->editor)
        ->putJson(route('admin.list-views.update', 'comments'), ['columns' => null, 'density' => 'comfortable'])
        ->assertNoContent();

    expect(AdminPreferences::of($this->editor->fresh())->listView('comments'))->toBe(['columns' => null, 'density' => 'comfortable']);
});

it('refuses bad column names, unknown densities and too many columns', function () {
    config(['verso.admin.lists.max_columns' => 3]);

    $this->actingAs($this->editor)
        ->putJson(route('admin.list-views.update', 'users'), ['columns' => ['Name; DROP TABLE'], 'density' => 'cozy'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['columns.0', 'density']);

    $this->actingAs($this->editor)
        ->putJson(route('admin.list-views.update', 'users'), ['columns' => ['a', 'b', 'c', 'd'], 'density' => null])
        ->assertJsonValidationErrors(['columns']);
});

it('keeps the views of two users apart', function () {
    $colleague = User::factory()->editor()->create(['name' => 'Rui Almeida']);

    $this->actingAs($this->editor)->putJson(route('admin.list-views.update', 'comments'), ['columns' => ['website'], 'density' => 'compact']);

    expect(AdminPreferences::of($colleague->fresh())->listView('comments'))->toBe(['columns' => null, 'density' => null]);
});

it('needs a signed in user', function () {
    $this->putJson(route('admin.list-views.update', 'comments'), ['columns' => null])->assertUnauthorized();
});

it('sends the saved view to the comments and users lists', function () {
    $admin = User::factory()->admin()->create();
    AdminPreferences::of($admin)->saveListView('comments', ['website', 'kind'], 'compact');
    AdminPreferences::of($admin)->saveListView('users', ['email'], null);

    $this->actingAs($admin->fresh())
        ->get(route('admin.comments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('listView', ['key' => 'comments', 'columns' => ['website', 'kind'], 'density' => 'compact']));

    $this->actingAs($admin->fresh())
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('listView', ['key' => 'users', 'columns' => ['email'], 'density' => null])
            ->where('users.data.0.two_factor', false)
        );
});

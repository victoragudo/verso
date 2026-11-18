<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Exceptions\LastAdminException;

it('stops any save that removes the last admin role', function () {
    $admin = User::factory()->admin()->create();
    $admin->role = Role::Author;

    expect(fn () => $admin->save())->toThrow(LastAdminException::class);
    expect($admin->fresh()->role)->toBe(Role::Admin);
});

it('stops any delete of the last admin', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => $admin->delete())->toThrow(LastAdminException::class);
    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('lets admins change their name freely', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ana Lopes']);
    $admin->update(['name' => 'Ana Lopes Ferreira']);

    expect($admin->fresh()->name)->toBe('Ana Lopes Ferreira');
});

it('lets other users be deleted', function () {
    User::factory()->admin()->create();
    $writer = User::factory()->author()->create();

    $writer->delete();

    expect(User::query()->find($writer->id))->toBeNull();
});

it('shows the error as a form error on the profile delete', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from('/settings/profile')
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasErrors('role');

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('answers with json when the client wants json', function () {
    $exception = LastAdminException::forDelete();
    $request = request()->duplicate();
    $request->headers->set('Accept', 'application/json');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getContent())->toContain('last admin');
});

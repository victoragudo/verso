<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;

$register = fn (string $name, string $email) => [
    'name' => $name,
    'email' => $email,
    'password' => 'correct-horse-battery',
    'password_confirmation' => 'correct-horse-battery',
];

it('makes the first registered user an admin', function () use ($register) {
    $this->post(route('register.store'), $register('Elena Ruiz', 'elena@lacasadelpan.es'));

    expect(User::where('email', 'elena@lacasadelpan.es')->first()->role)->toBe(Role::Admin);
});

it('gives later users the default role', function () use ($register) {
    User::factory()->admin()->create();

    $this->post(route('register.store'), $register('Jorge Molina', 'jorge@lacasadelpan.es'));

    expect(User::where('email', 'jorge@lacasadelpan.es')->first()->role)->toBe(Role::Subscriber);
});

it('reads the default role from config', function () use ($register) {
    config(['verso.users.default_role' => 'contributor']);
    User::factory()->admin()->create();

    $this->post(route('register.store'), $register('Ana Torres', 'ana@lacasadelpan.es'));

    expect(User::where('email', 'ana@lacasadelpan.es')->first()->role)->toBe(Role::Contributor);
});

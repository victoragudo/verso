<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;

it('remembers the sidebar items a user opened', function () {
    $user = User::factory()->editor()->create(['name' => 'Lucía Romero']);

    AdminPreferences::of($user)->markSeen('shop.orders');
    AdminPreferences::of($user)->markSeen('shop.orders');
    AdminPreferences::of($user)->markSeen('forms.messages');

    expect(AdminPreferences::of($user->fresh())->seenNavKeys())->toBe(['shop.orders', 'forms.messages'])
        ->and(AdminPreferences::of($user->fresh())->hasSeen('newsletter.campaigns'))->toBeFalse();
});

it('keeps only the newest opened items', function () {
    config(['verso.admin.nav.max_seen' => 2]);
    $user = User::factory()->create();

    foreach (['shop.orders', 'shop.products', 'shop.settings'] as $key) {
        AdminPreferences::of($user)->markSeen($key);
    }

    expect(AdminPreferences::of($user->fresh())->seenNavKeys())->toBe(['shop.products', 'shop.settings']);
});

it('saves the columns and density of each list', function () {
    $user = User::factory()->create();
    $preferences = AdminPreferences::of($user);

    $preferences->saveListView('entries.post', ['title', 'categories', 'word_count', 'categories'], 'compact');
    $preferences->saveListView('comments', null, 'comfortable');

    $fresh = AdminPreferences::of($user->fresh());

    expect($fresh->listView('entries.post'))->toBe(['columns' => ['title', 'categories', 'word_count'], 'density' => 'compact'])
        ->and($fresh->listView('comments'))->toBe(['columns' => null, 'density' => 'comfortable'])
        ->and($fresh->listView('entries.page'))->toBe(['columns' => null, 'density' => null]);
});

it('ignores a density it does not know', function () {
    $user = User::factory()->create();

    AdminPreferences::of($user)->saveListView('users', ['name'], 'cozy');

    expect(AdminPreferences::of($user->fresh())->listView('users')['density'])->toBeNull();
});

it('never sends the preferences with the user data', function () {
    $user = User::factory()->create();
    AdminPreferences::of($user)->markSeen('shop.orders');

    expect($user->fresh()->toArray())->not->toHaveKey('admin_preferences');
});

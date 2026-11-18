<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;

it('uses the site name as the first browser tab title in the admin', function () {
    app(Settings::class)->set('site.name', 'Harbor Notes');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Harbor Notes</title>', false);
});

it('uses the app name as the tab title on guest pages', function () {
    config(['app.name' => 'Alpine Field Journal']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Alpine Field Journal</title>', false);
});

it('does not read the tab title from the Vite app name', function () {
    $app = file_get_contents(resource_path('js/app.tsx'));
    $title = file_get_contents(resource_path('js/lib/page-title.ts'));

    expect($app)->not->toContain('VITE_APP_NAME')
        ->and($app)->toContain('title: pageTitle')
        ->and($title)->toContain('page?.props.siteName');
});

<?php

use App\Verso\Core\Models\Redirect;

beforeEach(function () {
    Redirect::query()->create(['from_path' => '/?p=10', 'to_path' => '/blog/a-weekend-in-lisbon', 'status_code' => 301]);
    Redirect::query()->create(['from_path' => '/?page_id=20', 'to_path' => '/about', 'status_code' => 301]);
});

it('redirects old post links', function () {
    $this->get('/?p=10')->assertStatus(301)->assertRedirect('/blog/a-weekend-in-lisbon');

    expect(Redirect::query()->where('from_path', '/?p=10')->value('hits'))->toBe(1);
});

it('redirects old page links', function () {
    $this->get('/?page_id=20&preview=false')->assertRedirect('/about');
});

it('ignores ids it does not know', function () {
    $this->get('/?p=404')->assertOk();
});

it('ignores ids that are not numbers', function () {
    $this->get('/?p=10abc')->assertOk();
});

it('only works on the home path', function () {
    $this->get('/harbour-tours?p=10')->assertNotFound();
});

it('ignores unsafe methods', function () {
    $this->post('/?p=10')->assertStatus(405);
});

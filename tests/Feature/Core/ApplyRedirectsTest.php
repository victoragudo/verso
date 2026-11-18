<?php

use App\Verso\Core\Http\Middleware\ApplyRedirects;
use App\Verso\Core\Models\Redirect;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'verso.redirects'])->group(function () {
        Route::get('/journal/{slug}', fn (string $slug) => "Journal: {$slug}");
        Route::post('/journal/{slug}', fn (string $slug) => "Saved: {$slug}");
    });
});

it('registers the middleware alias', function () {
    expect(app('router')->getMiddleware()['verso.redirects'])->toBe(ApplyRedirects::class);
});

it('redirects old paths with the stored status', function () {
    Redirect::point('/journal/old-harbour-walk', '/journal/harbour-walk');
    Redirect::point('/journal/winter-menu', '/journal/new-winter-menu', 302);

    $this->get('/journal/old-harbour-walk')->assertStatus(301)->assertRedirect('/journal/harbour-walk');
    $this->get('/journal/winter-menu')->assertStatus(302)->assertRedirect('/journal/new-winter-menu');
});

it('counts every hit', function () {
    Redirect::point('/journal/old-harbour-walk', '/journal/harbour-walk');

    $this->get('/journal/old-harbour-walk');
    $this->get('/journal/old-harbour-walk/');

    expect(Redirect::findForPath('/journal/old-harbour-walk')->hits)->toBe(2);
});

it('lets other requests through', function () {
    $this->get('/journal/harbour-walk')->assertOk()->assertSee('Journal: harbour-walk');
});

it('ignores form posts', function () {
    Redirect::point('/journal/old-harbour-walk', '/journal/harbour-walk');

    $this->post('/journal/old-harbour-walk')->assertOk()->assertSee('Saved: old-harbour-walk');
});

it('can send visitors to another site', function () {
    Redirect::point('/journal/shop', 'https://shop.example.org/');

    $this->get('/journal/shop')->assertRedirect('https://shop.example.org/');
});

it('applies redirects on the home route', function () {
    expect(Route::getRoutes()->getByName('home')->gatherMiddleware())->toContain('verso.redirects');
});

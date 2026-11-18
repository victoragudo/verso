<?php

use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\PublicRoutesFilter;
use App\Verso\Theme\ThemeServiceProvider;
use Illuminate\Routing\Router;

it('lets a plugin add public routes with the public middleware before the page catch-all', function () {
    Filters::add(PublicRoutesFilter::class, function (Router $router): Router {
        $router->get('events/{slug}', fn (string $slug) => 'Event: '.$slug)->name('theme.events.show');

        return $router;
    });

    $provider = new ThemeServiceProvider(app());
    (fn () => $this->registerPublicRoutes())->call($provider);
    app('router')->getRoutes()->refreshNameLookups();

    $route = app('router')->getRoutes()->getByName('theme.events.show');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('verso.redirects', 'verso.theme', 'verso.cache');

    $this->get('/events/porto-jazz-week')->assertOk()->assertSee('Event: porto-jazz-week');
});

it('returns the router unchanged when no plugin adds routes', function () {
    $router = app('router');

    expect(PublicRoutesFilter::apply($router))->toBe($router);
});

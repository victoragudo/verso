<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use Illuminate\Routing\Router;

// Lets plugins add public routes before the theme routes and the page catch-all.
// The routes get the public middleware (redirects, theme, page cache). Callbacks: fn (Router $router): Router.
final class PublicRoutesFilter implements Filter
{
    public static function apply(Router $router): Router
    {
        /** @var Router */
        return app(FilterRegistry::class)->apply(self::class, $router);
    }
}

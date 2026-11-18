<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

// Shares the admin appearance (light, dark or system) from its cookie with the views.
class HandleAppearance
{
    private const APPEARANCES = ['light', 'dark', 'system'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, self::APPEARANCES, true) ? $appearance : 'light');

        return $next($request);
    }
}

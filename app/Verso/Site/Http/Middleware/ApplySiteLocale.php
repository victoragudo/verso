<?php

namespace App\Verso\Site\Http\Middleware;

use App\Verso\Core\Settings\Settings;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Uses the site.locale setting as the app language for each web request.
class ApplySiteLocale
{
    public function __construct(protected Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $locale = $this->settings->get('site.locale');
        } catch (QueryException) {
            // Before install the settings table does not exist yet.
            return $next($request);
        }

        $allowed = array_map(strval(...), (array) config('verso.site.locales'));

        if (is_string($locale) && in_array($locale, $allowed, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

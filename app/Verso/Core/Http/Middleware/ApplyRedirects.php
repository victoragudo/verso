<?php

namespace App\Verso\Core\Http\Middleware;

use App\Verso\Core\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Sends visitors of public pages to the new URL when a redirect exists.
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $redirect = Redirect::findForPath($request->getPathInfo());

        if ($redirect === null) {
            return $next($request);
        }

        $redirect->recordHit();

        return redirect()->to($redirect->to_path, $redirect->status_code);
    }
}

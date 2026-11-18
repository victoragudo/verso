<?php

namespace App\Verso\Importer\Http\Middleware;

use App\Verso\Core\Models\Redirect;
use App\Verso\Importer\WordPress\LegacyQueryPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Sends old WordPress links like "/?p=123" or "/?page_id=7" to the imported entry.
class RedirectLegacyQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || $request->getPathInfo() !== '/') {
            return $next($request);
        }

        foreach (LegacyQueryPath::KEYS as $key) {
            $id = $request->query($key);

            if (! is_string($id) || ! ctype_digit($id)) {
                continue;
            }

            $redirect = Redirect::query()->where('from_path', LegacyQueryPath::make($key, (int) $id))->first();

            if ($redirect !== null) {
                $redirect->recordHit();

                return redirect()->to($redirect->to_path, $redirect->status_code);
            }
        }

        return $next($request);
    }
}

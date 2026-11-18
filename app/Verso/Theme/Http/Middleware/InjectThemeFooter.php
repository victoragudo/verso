<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Plugins\View\ThemeFooter;
use App\Verso\Theme\Support\HtmlResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Adds the plugin footer HTML before </body> on themes that do not print <x-verso-footer /> themselves.
// It runs inside the page cache, so cached pages keep the HTML too.
class InjectThemeFooter
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->attributes->get(ThemeFooter::PRINTED) === true || ! HtmlResponse::is($response)) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $response;
        }

        $footer = ThemeFooterFilter::apply('', $request);

        if ($footer !== '') {
            $response->setContent(substr($content, 0, $position).$footer.substr($content, $position));
        }

        return $response;
    }
}

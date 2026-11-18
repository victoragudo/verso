<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Theme\Templates\TemplateDebug;
use App\Verso\Theme\ThemeManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Points the "theme" view namespace at the active theme before a public page renders.
class UseActiveTheme
{
    public function __construct(
        protected ThemeManager $themes,
        protected TemplateDebug $debug,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->themes->registerViews();

        /** @var Response $response */
        $response = $next($request);

        if ($this->debug->enabledFor($request)) {
            $this->debug->inject($response);
        }

        return $response;
    }
}

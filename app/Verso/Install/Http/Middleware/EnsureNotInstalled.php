<?php

namespace App\Verso\Install\Http\Middleware;

use App\Verso\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Hides the web installer (404) once Verso is installed.
class EnsureNotInstalled
{
    public function __construct(protected InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->isInstalled(), 404);

        return $next($request);
    }
}

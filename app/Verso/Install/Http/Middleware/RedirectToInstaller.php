<?php

namespace App\Verso\Install\Http\Middleware;

use App\Verso\Install\InstallerMode;
use App\Verso\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Sends every request to /install until Verso is installed, with safe drivers that need no database.
class RedirectToInstaller
{
    /** @var list<string> */
    protected const OPEN_PATHS = ['install', 'install/*', 'up', 'i18n/*', 'locale'];

    public function __construct(
        protected InstallState $state,
        protected InstallerMode $mode,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('verso.install.redirect') || $this->installed()) {
            return $next($request);
        }

        $this->mode->activate();

        if ($request->is(...self::OPEN_PATHS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Verso is not installed yet.'), 'install_url' => url('/install')], 503);
        }

        return redirect('/install');
    }

    // Sites installed before the lock file existed get it now, so later requests skip the database.
    protected function installed(): bool
    {
        if ($this->state->isLocked()) {
            return true;
        }

        if ($this->state->isInstalled()) {
            $this->state->lock();

            return true;
        }

        return false;
    }
}

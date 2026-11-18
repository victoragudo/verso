<?php

namespace App\Verso\I18n\Http\Middleware;

use App\Verso\I18n\AdminLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Uses the admin language on admin, account, login and install pages. Public pages keep the site language.
class ApplyAdminLocale
{
    // Paths outside the admin prefix that belong to the admin side.
    protected const ADMIN_SIDE_PATHS = [
        'dashboard', 'settings', 'settings/*', 'login', 'logout', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge', 'email/*', 'user/*', 'passkeys/*', 'invitation/*', 'install', 'install/*', 'verso/welcome/*', 'locale',
    ];

    public function __construct(protected AdminLocale $locale) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAdminSide($request)) {
            app()->setLocale($this->locale->forRequest($request));
        }

        return $next($request);
    }

    public function isAdminSide(Request $request): bool
    {
        $prefix = trim((string) config('verso.admin.prefix'), '/');

        return $request->is($prefix, $prefix.'/*', ...self::ADMIN_SIDE_PATHS);
    }
}

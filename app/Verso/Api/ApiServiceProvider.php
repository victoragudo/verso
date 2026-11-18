<?php

namespace App\Verso\Api;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the read-only public JSON API under /api/v1.
class ApiServiceProvider extends ServiceProvider
{
    // Name of the rate limiter used by every API route.
    public const RATE_LIMITER = 'verso-api';

    public function boot(): void
    {
        if (! (bool) config('verso.api.enabled')) {
            return;
        }

        RateLimiter::for(self::RATE_LIMITER, fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('verso.api.rate_limit')))
            ->by((string) $request->ip()));

        Route::middleware(['api', 'throttle:'.self::RATE_LIMITER])
            ->prefix((string) config('verso.api.prefix'))
            ->name('api.')
            ->group(base_path('routes/verso/api.php'));
    }
}

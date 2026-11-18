<?php

namespace App\Verso\Core\Routing;

use Illuminate\Support\Facades\Route;

// Loads a module route file inside the admin area (prefix, name and middleware).
class AdminRoutes
{
    /** @var list<string> */
    public const MIDDLEWARE = ['web', 'auth', 'verified', 'verso.view-as'];

    public static function load(string $file): void
    {
        Route::middleware(self::MIDDLEWARE)
            ->prefix((string) config('verso.admin.prefix'))
            ->name('admin.')
            ->group($file);
    }
}

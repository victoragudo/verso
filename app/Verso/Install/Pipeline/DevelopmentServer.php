<?php

namespace App\Verso\Install\Pipeline;

// The PHP built-in server that "php artisan serve" runs. It restarts when .env changes,
// which stops any request that is still running.
class DevelopmentServer
{
    public function restartsOnEnvChange(): bool
    {
        return PHP_SAPI === 'cli-server';
    }

    public function processId(): int
    {
        return (int) getmypid();
    }
}

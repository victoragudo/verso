<?php

namespace App\Verso\Install\Env;

use Illuminate\Filesystem\Filesystem;
use Throwable;

// Creates .env from .env.example with a fresh APP_KEY on the very first start.
final class EnvironmentFile
{
    // Called from bootstrap/app.php before Laravel reads the environment.
    public static function ensure(string $basePath): bool
    {
        if (self::skipped()) {
            return false;
        }

        $target = $basePath.DIRECTORY_SEPARATOR.'.env';
        $example = $basePath.DIRECTORY_SEPARATOR.'.env.example';

        if (file_exists($target)) {
            return self::fillEmptyKey($target);
        }

        if (! is_file($example) || ! is_writable($basePath)) {
            return false;
        }

        return self::createFrom($example, $target);
    }

    // A copied .env.example has an empty APP_KEY, which breaks cookies. Give it a key.
    public static function fillEmptyKey(string $target): bool
    {
        if ((self::env('APP_KEY') ?? '') !== '' || ! is_writable($target)) {
            return false;
        }

        $contents = (string) @file_get_contents($target);

        if (preg_match('/^APP_KEY=\s*$/m', $contents) !== 1) {
            return false;
        }

        try {
            $writer = new EnvWriter(new Filesystem, (int) (fileperms($target) & 0777));
            $writer->write(new EnvWriteInput($target, ['APP_KEY' => $writer->newAppKey()]));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // Copy the example and set a new APP_KEY. Fails quietly: the requirements step reports the problem.
    public static function createFrom(string $example, string $target, int $permissions = 0640): bool
    {
        try {
            $files = new Filesystem;
            $files->copy($example, $target);
            $writer = new EnvWriter($files, $permissions);
            $writer->write(new EnvWriteInput($target, ['APP_KEY' => $writer->newAppKey()]));
            @chmod($target, $permissions);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // Tests and setups that turn it off keep their environment as it is.
    protected static function skipped(): bool
    {
        return self::env('APP_ENV') === 'testing' || in_array(self::env('VERSO_INSTALL_CREATE_ENV'), ['false', '0', 'off'], true);
    }

    protected static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : null;
    }
}

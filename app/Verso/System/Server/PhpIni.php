<?php

namespace App\Verso\System\Server;

// Reads and changes PHP ini values. Tests replace it with fake values.
class PhpIni
{
    public const ACCESS_USER = 1;

    public const ACCESS_PERDIR = 2;

    public const ACCESS_SYSTEM = 4;

    public function get(string $name): ?string
    {
        $value = ini_get($name);

        return $value === false ? null : $value;
    }

    // Where the value may be changed: a mix of the ACCESS_* bits, or null when PHP does not know the name.
    public function access(string $name): ?int
    {
        $all = $this->all();

        return isset($all[$name]) ? (int) $all[$name]['access'] : null;
    }

    public function set(string $name, string $value): bool
    {
        return ini_set($name, $value) !== false;
    }

    public function setTimeLimit(int $seconds): bool
    {
        return set_time_limit($seconds);
    }

    public function sapi(): string
    {
        return PHP_SAPI;
    }

    public function version(): string
    {
        return PHP_VERSION;
    }

    // The php.ini file PHP loaded, or null.
    public function loadedFile(): ?string
    {
        $file = php_ini_loaded_file();

        return $file === false ? null : $file;
    }

    // The per folder ini file name (".user.ini" by default). Empty means it is turned off.
    public function userIniFilename(): string
    {
        return (string) $this->get('user_ini.filename');
    }

    /** @return list<string> */
    public function extensions(): array
    {
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

        return $extensions;
    }

    // True when the value can be changed with ini_set() while a request runs.
    public function changeableAtRuntime(string $name): bool
    {
        $access = $this->access($name);

        return $access !== null && ($access & self::ACCESS_USER) === self::ACCESS_USER;
    }

    // True when .user.ini or .htaccess may set the value.
    public function changeablePerDirectory(string $name): bool
    {
        $access = $this->access($name);

        return $access !== null && ($access & (self::ACCESS_PERDIR | self::ACCESS_USER)) !== 0;
    }

    /** @return array<string, array{global_value: string|null, local_value: string|null, access: int}> */
    protected function all(): array
    {
        static $all = null;

        return $all ??= (array) ini_get_all(null, true);
    }

    // Turn "256M", "1G" or "512K" into bytes. -1 (no limit) and 0 stay as they are.
    public static function bytes(?string $value): int
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

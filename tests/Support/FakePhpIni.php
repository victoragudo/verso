<?php

namespace Tests\Support;

use App\Verso\System\Server\PhpIni;

// PHP ini values for tests: no real ini_set() call, so limits can be faked and checked.
class FakePhpIni extends PhpIni
{
    /** @var array<string, string> */
    public array $changed = [];

    /**
     * @param  array<string, string>  $values
     * @param  array<string, int>  $access
     */
    public function __construct(
        public array $values = [],
        public array $accessBits = [],
        public string $sapiName = 'fpm-fcgi',
    ) {}

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function access(string $name): ?int
    {
        return $this->accessBits[$name] ?? (array_key_exists($name, $this->values) ? self::ACCESS_USER | self::ACCESS_PERDIR | self::ACCESS_SYSTEM : null);
    }

    public function set(string $name, string $value): bool
    {
        $this->values[$name] = $value;
        $this->changed[$name] = $value;

        return true;
    }

    public function setTimeLimit(int $seconds): bool
    {
        $this->changed['max_execution_time'] = (string) $seconds;

        return true;
    }

    public function sapi(): string
    {
        return $this->sapiName;
    }

    public function loadedFile(): ?string
    {
        return '/etc/php/8.4/fpm/php.ini';
    }

    public function userIniFilename(): string
    {
        return '.user.ini';
    }

    // Typical shared hosting values: small uploads, upload sizes only changeable per folder.
    public static function sharedHosting(array $overrides = []): self
    {
        $perdir = self::ACCESS_PERDIR | self::ACCESS_SYSTEM;
        $all = self::ACCESS_USER | $perdir;

        return new self(
            values: [...[
                'upload_max_filesize' => '8M',
                'post_max_size' => '8M',
                'memory_limit' => '128M',
                'max_execution_time' => '30',
                'max_input_time' => '60',
                'max_input_vars' => '1000',
                'max_file_uploads' => '20',
                'user_ini.cache_ttl' => '300',
            ], ...$overrides],
            accessBits: [
                'upload_max_filesize' => $perdir,
                'post_max_size' => $perdir,
                'max_input_time' => $perdir,
                'max_input_vars' => $perdir,
                'max_file_uploads' => $perdir,
                'memory_limit' => $all,
                'max_execution_time' => $all,
                'user_ini.cache_ttl' => self::ACCESS_SYSTEM,
            ],
        );
    }
}

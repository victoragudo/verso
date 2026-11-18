<?php

namespace App\Verso\System\Server;

use App\Verso\System\Config\ConfigRegistry;
use Illuminate\Contracts\Config\Repository as Config;

// Compares PHP limits with what the Verso settings need, and builds ready to copy snippets.
class ServerAdvisor
{
    // Ini values Verso cares about, with a short reason shown in the admin.
    public const RELEVANT = [
        'upload_max_filesize' => ['description' => 'Biggest single uploaded file.'],
        'post_max_size' => ['description' => 'Biggest whole form post, files included.'],
        'memory_limit' => ['description' => 'Memory one request may use.'],
        'max_execution_time' => ['description' => 'Seconds one request may run.'],
        'max_input_time' => ['description' => 'Seconds PHP may spend reading the request (slow uploads).'],
        'max_input_vars' => ['description' => 'Most form fields in one request.'],
        'max_file_uploads' => ['description' => 'Most files in one request.'],
        'file_uploads' => ['description' => 'Uploads on or off.'],
        'user_ini.filename' => ['description' => 'Per folder ini file name.'],
        'user_ini.cache_ttl' => ['description' => 'Seconds before PHP reads .user.ini again.'],
        'opcache.enable' => ['description' => 'Keeps compiled PHP in memory.'],
        'realpath_cache_size' => ['description' => 'Cache of file paths.'],
    ];

    public function __construct(
        protected PhpIni $ini,
        protected ConfigRegistry $registry,
        protected Config $config,
        protected string $serverSoftware = '',
    ) {}

    public function webServer(): WebServer
    {
        return WebServer::detect($this->serverSoftware, $this->ini->sapi());
    }

    public function iniTarget(): ?IniTarget
    {
        return IniTarget::forSapi($this->ini->sapi());
    }

    // The biggest upload any Verso setting allows, in kilobytes.
    public function largestUploadKb(): int
    {
        $largest = 0;

        foreach ($this->registry->all() as $parameter) {
            if ($parameter->isUploadLimit()) {
                $largest = max($largest, (int) $this->config->get($parameter->key));
            }
        }

        return $largest;
    }

    /**
     * PHP values Verso would write: only the ones that are too low today.
     *
     * @return array<string, string>
     */
    public function recommended(): array
    {
        $uploadMb = max(1, (int) ceil($this->largestUploadKb() / 1024));
        $overheadMb = max(1, (int) ceil((int) $this->config->get('verso.system.server.upload_overhead_kb') / 1024));
        $memoryMb = (int) $this->config->get('verso.system.heavy_tasks.memory_mb');
        $seconds = (int) $this->config->get('verso.system.heavy_tasks.time_limit');

        $wanted = [
            'upload_max_filesize' => $uploadMb * 1024 ** 2,
            'post_max_size' => ($uploadMb + $overheadMb) * 1024 ** 2,
            'memory_limit' => $memoryMb * 1024 ** 2,
        ];
        $values = [];

        foreach ($wanted as $name => $bytes) {
            $current = PhpIni::bytes($this->ini->get($name));

            if ($current !== -1 && ! ($current === 0 && $name === 'post_max_size') && $current < $bytes) {
                $values[$name] = intdiv($bytes, 1024 ** 2).'M';
            }
        }

        $inputTime = (int) $this->ini->get('max_input_time');

        if ($seconds > 0 && $inputTime > 0 && $inputTime < $seconds) {
            $values['max_input_time'] = (string) $seconds;
        }

        return $values;
    }

    /**
     * Every relevant ini value with where it can be changed.
     *
     * @return list<array{name: string, value: string|null, about: string, runtime: bool, perDirectory: bool, scope: string}>
     */
    public function iniValues(): array
    {
        $rows = [];

        foreach (self::RELEVANT as $name => ['description' => $about]) {
            $access = $this->ini->access($name);
            $rows[] = [
                'name' => $name,
                'value' => $this->ini->get($name),
                'about' => __($about),
                'runtime' => $this->ini->changeableAtRuntime($name),
                'perDirectory' => $this->ini->changeablePerDirectory($name),
                'scope' => self::scope($access),
            ];
        }

        return $rows;
    }

    /**
     * Ready to copy snippets for every place a limit can be set.
     *
     * @param  array<string, string>  $values
     * @return list<array{key: string, label: string, file: string, code: string, recommended: bool}>
     */
    public function snippets(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $bodyMb = (int) ceil(PhpIni::bytes($values['post_max_size'] ?? (string) $this->ini->get('post_max_size')) / 1024 ** 2);
        $server = $this->webServer();
        $sapi = $this->ini->sapi();
        $ini = implode("\n", array_map(fn (string $name, string $value): string => "{$name} = {$value}", array_keys($values), $values));

        return [
            ['key' => 'php_ini', 'label' => 'php.ini', 'file' => $this->ini->loadedFile() ?? 'php.ini', 'code' => $ini, 'recommended' => false],
            ['key' => 'user_ini', 'label' => '.user.ini', 'file' => 'public/.user.ini', 'code' => $ini, 'recommended' => $this->iniTarget() === IniTarget::UserIni],
            ['key' => 'fpm', 'label' => __('PHP-FPM pool'), 'file' => '/etc/php/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'/fpm/pool.d/www.conf', 'code' => implode("\n", array_map(fn (string $name, string $value): string => "php_admin_value[{$name}] = {$value}", array_keys($values), $values)), 'recommended' => $sapi === 'fpm-fcgi'],
            ['key' => 'nginx', 'label' => 'nginx', 'file' => 'server { ... } block', 'code' => "client_max_body_size {$bodyMb}m;", 'recommended' => $server === WebServer::Nginx],
            ['key' => 'apache', 'label' => 'Apache', 'file' => 'VirtualHost or public/.htaccess', 'code' => 'LimitRequestBody '.($bodyMb * 1024 ** 2).($sapi === 'apache2handler' ? "\n".implode("\n", array_map(fn (string $name, string $value): string => "php_value {$name} {$value}", array_keys($values), $values)) : ''), 'recommended' => $server === WebServer::Apache],
            ['key' => 'caddy', 'label' => 'Caddy', 'file' => 'Caddyfile', 'code' => "request_body {\n    max_size {$bodyMb}MB\n}", 'recommended' => $server === WebServer::Caddy],
        ];
    }

    // PHP_INI_ALL, PHP_INI_PERDIR or PHP_INI_SYSTEM, from the access bits.
    public static function scope(?int $access): string
    {
        return match (true) {
            $access === null => 'unknown',
            ($access & PhpIni::ACCESS_USER) !== 0 => 'PHP_INI_ALL',
            ($access & PhpIni::ACCESS_PERDIR) !== 0 => 'PHP_INI_PERDIR',
            default => 'PHP_INI_SYSTEM',
        };
    }
}

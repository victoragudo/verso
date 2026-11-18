<?php

namespace App\Verso\System\Server;

// The web server in front of PHP, guessed from the SERVER_SOFTWARE value.
enum WebServer: string
{
    case Nginx = 'nginx';
    case Apache = 'apache';
    case LiteSpeed = 'litespeed';
    case Caddy = 'caddy';
    case PhpBuiltIn = 'php';
    case Unknown = 'unknown';

    public static function detect(string $software, string $sapi = ''): self
    {
        $software = strtolower($software);

        return match (true) {
            str_contains($software, 'nginx'), str_contains($software, 'openresty') => self::Nginx,
            str_contains($software, 'litespeed') => self::LiteSpeed,
            str_contains($software, 'apache'), $sapi === 'apache2handler' => self::Apache,
            str_contains($software, 'caddy') => self::Caddy,
            $sapi === 'cli-server', str_starts_with($software, 'php') => self::PhpBuiltIn,
            default => self::Unknown,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Nginx => 'nginx',
            self::Apache => 'Apache',
            self::LiteSpeed => 'LiteSpeed',
            self::Caddy => 'Caddy',
            self::PhpBuiltIn => __('PHP built-in server'),
            self::Unknown => __('Unknown web server'),
        };
    }
}

<?php

namespace App\Verso\System\Server;

// A file in public/ where Verso may write PHP limits: .user.ini (PHP-FPM, CGI) or .htaccess (Apache mod_php).
enum IniTarget: string
{
    case UserIni = 'user_ini';
    case Htaccess = 'htaccess';

    // The best target for this PHP setup, or null when no file in public/ is read.
    public static function forSapi(string $sapi): ?self
    {
        return match ($sapi) {
            'fpm-fcgi', 'cgi-fcgi', 'cgi' => self::UserIni,
            'apache2handler', 'litespeed' => self::Htaccess,
            default => null,
        };
    }

    public function fileName(string $userIniName = '.user.ini'): string
    {
        return $this === self::UserIni ? ($userIniName !== '' ? $userIniName : '.user.ini') : '.htaccess';
    }

    public function label(): string
    {
        return $this === self::UserIni ? __('.user.ini (PHP-FPM or CGI)') : '.htaccess (Apache mod_php)';
    }
}

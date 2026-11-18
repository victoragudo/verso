<?php

namespace App\Verso\Install\Database;

// Why a database connection test failed.
enum ConnectionProblem: string
{
    case DriverMissing = 'driver_missing';
    case HostUnreachable = 'host_unreachable';
    case AuthFailed = 'auth_failed';
    case UnknownDatabase = 'unknown_database';
    case VersionTooOld = 'version_too_old';
    case SqliteNotWritable = 'sqlite_not_writable';
    case Unknown = 'unknown';

    // A short hint that tells the user what to do next.
    public function hint(): string
    {
        return match ($this) {
            self::DriverMissing => __('Turn on the PDO extension for this database in php.ini.'),
            self::HostUnreachable => __('Check the host and port, and that the database server is running and accepts outside connections.'),
            self::AuthFailed => __('Check the username and password.'),
            self::UnknownDatabase => __('Create the database, or let the installer create it for you.'),
            self::VersionTooOld => __('Upgrade the database server, or pick another database.'),
            self::SqliteNotWritable => __('Make the folder writable for the web server, or pick another path.'),
            self::Unknown => __('Read the message from the database server for details.'),
        };
    }
}

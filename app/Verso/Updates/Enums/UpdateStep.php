<?php

namespace App\Verso\Updates\Enums;

// One step of a core update. The order of the cases is the order of the run.
enum UpdateStep: string
{
    case Lock = 'lock';
    case Download = 'download';
    case Verify = 'verify';
    case Extract = 'extract';
    case Maintenance = 'maintenance';
    case BackupDatabase = 'backup_database';
    case BackupFiles = 'backup_files';
    case ReplaceFiles = 'replace_files';
    case Migrate = 'migrate';
    case ClearCaches = 'clear_caches';
    case Online = 'online';
    case HealthCheck = 'health_check';
    case Cleanup = 'cleanup';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::Lock => __('Make sure no other update runs'),
            self::Download => __('Download the package'),
            self::Verify => __('Check the checksum and signature'),
            self::Extract => __('Unpack the package'),
            self::Maintenance => __('Turn on maintenance mode'),
            self::BackupDatabase => __('Back up the database'),
            self::BackupFiles => __('Back up the core files'),
            self::ReplaceFiles => __('Copy the new core files'),
            self::Migrate => __('Run database migrations'),
            self::ClearCaches => __('Clear caches'),
            self::Online => __('Turn off maintenance mode'),
            self::HealthCheck => __('Check that the site works'),
            self::Cleanup => __('Remove temporary files'),
            self::Rollback => __('Roll back'),
        };
    }

    /**
     * The steps of a normal run, in order.
     *
     * @return list<self>
     */
    public static function plan(): array
    {
        return [
            self::Lock, self::Download, self::Verify, self::Extract, self::Maintenance, self::BackupDatabase,
            self::BackupFiles, self::ReplaceFiles, self::Migrate, self::ClearCaches, self::Online, self::HealthCheck, self::Cleanup,
        ];
    }
}

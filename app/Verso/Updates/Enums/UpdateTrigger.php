<?php

namespace App\Verso\Updates\Enums;

// Who or what started an update.
enum UpdateTrigger: string
{
    case Admin = 'admin';
    case Cli = 'cli';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Cli => __('Command line'),
            self::Automatic => __('Automatic'),
        };
    }
}

<?php

namespace App\Verso\Updates\Enums;

// Which releases a site wants: only stable ones, or betas too.
enum UpdateChannel: string
{
    case Stable = 'stable';
    case Beta = 'beta';

    public function label(): string
    {
        return match ($this) {
            self::Stable => __('Stable'),
            self::Beta => __('Beta'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Stable => __('Only final releases.'),
            self::Beta => __('Final releases and test versions (beta, release candidates).'),
        };
    }
}

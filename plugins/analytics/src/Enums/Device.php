<?php

namespace Verso\Plugins\Analytics\Enums;

// Screen size bucket sent by the beacon. The exact width is never stored.
enum Device: string
{
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Desktop = 'desktop';

    // The bucket for a screen width, using the breakpoints from config.
    public static function forWidth(int $width, int $tabletFrom, int $desktopFrom): self
    {
        return match (true) {
            $width >= $desktopFrom => self::Desktop,
            $width >= $tabletFrom => self::Tablet,
            default => self::Mobile,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mobile => __('analytics::Mobile'),
            self::Tablet => __('analytics::Tablet'),
            self::Desktop => __('analytics::Desktop'),
        };
    }
}

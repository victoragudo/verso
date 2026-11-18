<?php

namespace App\Verso\Updates\Enums;

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Feed\Release;

// Which updates the daily job may install without a person.
enum AutomaticUpdates: string
{
    case Off = 'off';
    case Security = 'security';
    case Minor = 'minor';

    public function label(): string
    {
        return match ($this) {
            self::Off => __('Off'),
            self::Security => __('Security fixes only'),
            self::Minor => __('Minor and security updates'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Off => __('Updates are only installed when you start them.'),
            self::Security => __('Patch releases marked as security fixes are installed every day.'),
            self::Minor => __('New minor and patch releases of the same major version are installed every day.'),
        };
    }

    // True when the daily job may install this release on its own. Major versions always need a person.
    public function allows(Release $release, Version $current): bool
    {
        $next = $release->semver();

        if (! $next->isNewerThan($current) || ! $next->isSameMajor($current)) {
            return false;
        }

        return match ($this) {
            self::Off => false,
            self::Security => $release->security && $next->isSameMinor($current),
            self::Minor => true,
        };
    }
}

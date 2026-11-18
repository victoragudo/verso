<?php

namespace Verso\Plugins\Memberships\Enums;

// The state of one membership.
enum MembershipStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('memberships::Active'),
            self::Expired => __('memberships::Expired'),
            self::Cancelled => __('memberships::Cancelled'),
        };
    }
}

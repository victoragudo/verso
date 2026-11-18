<?php

namespace App\Verso\Site\Enums;

// Who sees a menu item: everybody, only signed in members or only guests.
enum MenuItemVisibility: string
{
    case Everyone = 'everyone';
    case Members = 'members';
    case Guests = 'guests';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('Everyone'),
            self::Members => __('Signed in members only'),
            self::Guests => __('Visitors who are not signed in'),
        };
    }

    public function allows(bool $signedIn): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Members => $signedIn,
            self::Guests => ! $signedIn,
        };
    }
}

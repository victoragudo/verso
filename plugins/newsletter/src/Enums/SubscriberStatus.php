<?php

namespace Verso\Plugins\Newsletter\Enums;

// Where a subscriber stands: waiting for the confirm click, subscribed or gone.
enum SubscriberStatus: string
{
    case Pending = 'pending';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('newsletter::Pending'),
            self::Subscribed => __('newsletter::Subscribed'),
            self::Unsubscribed => __('newsletter::Unsubscribed'),
        };
    }
}

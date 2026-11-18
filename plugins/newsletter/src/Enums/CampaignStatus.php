<?php

namespace Verso\Plugins\Newsletter\Enums;

// The life of a campaign: written, being sent, done.
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('newsletter::Draft'),
            self::Sending => __('newsletter::Sending'),
            self::Sent => __('newsletter::Sent'),
        };
    }
}

<?php

namespace App\Verso\Updates\Support;

use App\Verso\Core\Settings\Settings;
use App\Verso\Updates\Enums\AutomaticUpdates;
use App\Verso\Updates\Enums\UpdateChannel;

// Reads and saves the update settings (channel and automatic updates).
class UpdateSettings
{
    public const CHANNEL = 'updates.channel';

    public const AUTOMATIC = 'updates.automatic';

    public function __construct(protected Settings $settings) {}

    public function channel(): UpdateChannel
    {
        return UpdateChannel::tryFrom((string) $this->settings->get(self::CHANNEL)) ?? UpdateChannel::Stable;
    }

    public function automatic(): AutomaticUpdates
    {
        return AutomaticUpdates::tryFrom((string) $this->settings->get(self::AUTOMATIC)) ?? AutomaticUpdates::Off;
    }

    public function save(UpdateChannel $channel, AutomaticUpdates $automatic): void
    {
        $this->settings->setMany([
            self::CHANNEL => $channel->value,
            self::AUTOMATIC => $automatic->value,
        ]);
    }
}

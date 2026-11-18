<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Core\Settings\Settings;

// The admin setting that blocks high risk plugin installs, with its default from config.
class VerificationSettings
{
    // Settings key of the "block high risk plugins" switch.
    public const BLOCK_HIGH_RISK = 'extensions.block_high_risk';

    public function __construct(protected Settings $settings) {}

    public function blocksHighRisk(): bool
    {
        $value = $this->settings->get(self::BLOCK_HIGH_RISK);

        return $value === null ? (bool) config('verso.extensions.verification.block_high_risk') : (bool) $value;
    }

    public function setBlocksHighRisk(bool $block): void
    {
        $this->settings->set(self::BLOCK_HIGH_RISK, $block);
    }
}

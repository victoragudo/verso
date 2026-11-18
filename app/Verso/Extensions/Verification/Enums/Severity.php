<?php

namespace App\Verso\Extensions\Verification\Enums;

// How serious one finding of the plugin checks is.
enum Severity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    // Points this severity adds to the risk score (from config).
    public function weight(): int
    {
        return max(0, (int) config('verso.extensions.verification.weights.'.$this->value));
    }

    // Higher numbers sort first in reports.
    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Info => 0,
        };
    }
}

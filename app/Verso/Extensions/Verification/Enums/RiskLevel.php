<?php

namespace App\Verso\Extensions\Verification\Enums;

// Overall risk of a plugin, worked out from the score of its findings.
enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    // The level for a score. A single high finding always makes the plugin high risk.
    public static function fromScore(int $score, bool $hasHighFinding): self
    {
        $config = (array) config('verso.extensions.verification');

        return match (true) {
            $hasHighFinding || $score >= max(1, (int) ($config['high_score'] ?? 25)) => self::High,
            $score >= max(1, (int) ($config['medium_score'] ?? 6)) => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low risk'),
            self::Medium => __('Medium risk'),
            self::High => __('High risk'),
        };
    }
}

<?php

namespace App\Verso\Seo\Audit;

// How much an issue hurts. The weight sets its share of the site score.
enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Notice = 'notice';

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::Warning => 3,
            self::Notice => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Critical => __('Important'),
            self::Warning => __('Should fix'),
            self::Notice => __('Good to fix'),
        };
    }
}

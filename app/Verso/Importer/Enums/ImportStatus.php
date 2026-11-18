<?php

namespace App\Verso\Importer\Enums;

// The state of an import run.
enum ImportStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    // True when the run will not change any more.
    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }
}

<?php

namespace App\Verso\Updates\Enums;

// The state of one core update run.
enum UpdateStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    // True when the run will not change any more.
    public function isFinished(): bool
    {
        return ! in_array($this, [self::Pending, self::Running], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
            self::RolledBack => __('Rolled back'),
        };
    }
}

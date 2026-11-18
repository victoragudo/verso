<?php

namespace App\Verso\System\Config;

// Work done right after a setting is saved, so the new value is used everywhere.
enum ApplyAction: string
{
    case RestartQueue = 'restart_queue';
    case FlushPageCache = 'flush_page_cache';
    case FlushBlockCache = 'flush_block_cache';

    public function label(): string
    {
        return match ($this) {
            self::RestartQueue => __('Queue workers restart'),
            self::FlushPageCache => __('Cached pages are cleared'),
            self::FlushBlockCache => __('Cached block HTML is cleared'),
        };
    }
}

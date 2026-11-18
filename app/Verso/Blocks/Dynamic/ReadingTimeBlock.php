<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\I18n\Translate;

// About how many minutes the current entry takes to read.
class ReadingTimeBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-reading-time';
    }

    public function label(): string
    {
        return __('Reading time');
    }

    public function description(): string
    {
        return __('About how many minutes the post takes to read.');
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $minutes = max(1, (int) ceil($entry->wordCount() / max(1, (int) config('verso.layouts.words_per_minute'))));

        return '<p class="verso-reading-time">'.e(Translate::icu('{minutes, plural, one {# minute read} other {# minutes read}}', ['minutes' => $minutes])).'</p>';
    }
}

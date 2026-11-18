<?php

namespace Verso\Plugins\ReadingTime\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\ReadingTime\ReadingTime;
use Verso\Plugins\ReadingTime\ReadingTimeServiceProvider;

// A block that shows the reading time of the entry it sits in.
class ReadingTimeBlock implements HasBlockFields, PluginBlock
{
    public function __construct(protected ReadingTime $readingTime) {}

    public function name(): string
    {
        return ReadingTimeServiceProvider::BLOCK;
    }

    public function label(): string
    {
        return __('reading-time::Reading time');
    }

    public function description(): string
    {
        return __('reading-time::Shows how many minutes the entry takes to read.');
    }

    // Minutes are only used when the block is shown outside an entry.
    public function fields(): array
    {
        return [
            new BlockField(
                name: 'minutes',
                label: __('reading-time::Minutes'),
                type: BlockFieldType::Number,
                help: __('reading-time::Only used when the block is shown outside an entry. Inside an entry the time is counted for you.'),
                min: 1,
                max: 600,
            ),
        ];
    }

    /**
     * Uses attrs.minutes when the block is shown outside an entry.
     *
     * @param  array<mixed>  $block
     */
    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        $minutes = match (true) {
            $entry !== null => $this->readingTime->minutes($entry),
            is_numeric($attrs['minutes'] ?? null) => max(1, (int) $attrs['minutes']),
            default => null,
        };

        return $minutes === null ? '' : $this->readingTime->badge($minutes);
    }
}

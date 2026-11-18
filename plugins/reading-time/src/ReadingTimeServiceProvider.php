<?php

namespace Verso\Plugins\ReadingTime;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\Unit;
use Verso\Plugins\ReadingTime\Blocks\ReadingTimeBlock;

// Boots the Reading Time plugin: one block and two filters.
class ReadingTimeServiceProvider extends PluginServiceProvider
{
    // The block type stored in entries.
    public const BLOCK = 'reading-time';

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/reading-time.php'), 'reading-time');
        $this->registerSystemSettings('Reading time', 'clock', ...$this->systemSettings());

        $this->app->singleton(ReadingTime::class, fn (): ReadingTime => new ReadingTime(
            wordsPerMinute: (int) config('reading-time.words_per_minute'),
            label: (string) config('reading-time.label'),
        ));
    }

    public function boot(): void
    {
        $readingTime = $this->app->make(ReadingTime::class);

        $this->registerBlock(new ReadingTimeBlock($readingTime));

        // Put the badge on top of entries that do not place the block themselves.
        $this->addFilter(EntryContentFilter::class, function (string $html, Entry $entry) use ($readingTime): string {
            if (! (bool) config('reading-time.auto_insert') || $readingTime->hasBlock($entry->blocks ?? [])) {
                return $html;
            }

            return $readingTime->badge($readingTime->minutes($entry)).$html;
        });

        // Add the number of minutes to the public API.
        $this->addFilter(ApiEntryFilter::class, fn (array $data, Entry $entry): array => [
            ...$data,
            'reading_time' => $readingTime->minutes($entry),
        ]);
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('reading-time.words_per_minute', $group, 'Reading speed', 'Average words read per minute, used to count minutes.', unit: Unit::Items, min: 50, max: 1000, env: 'READING_TIME_WORDS_PER_MINUTE'),
        ];
    }
}

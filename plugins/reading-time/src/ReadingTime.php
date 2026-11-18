<?php

namespace Verso\Plugins\ReadingTime;

use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;

// Counts the words of an entry and turns them into minutes of reading.
class ReadingTime
{
    public function __construct(
        protected int $wordsPerMinute,
        protected string $label,
    ) {}

    public function words(Entry $entry): int
    {
        return $this->countWords($entry->blocks ?? []);
    }

    // Always at least one minute.
    public function minutes(Entry $entry): int
    {
        return max(1, (int) ceil($this->words($entry) / max(1, $this->wordsPerMinute)));
    }

    // The small badge HTML, like <p class="reading-time">4 min read</p>.
    public function badge(int $minutes): string
    {
        return '<p class="reading-time">'.e($this->text($minutes)).'</p>';
    }

    // The reading time as text, like "4 min read", in the current language unless a label is configured.
    public function text(int $minutes): string
    {
        if ($this->label !== '') {
            return str_replace(':minutes', (string) $minutes, $this->label);
        }

        return Translate::icu('reading-time::{minutes, plural, one {# min read} other {# min read}}', ['minutes' => $minutes]);
    }

    /**
     * True when the blocks already hold a reading-time block.
     *
     * @param  array<mixed>  $blocks
     */
    public function hasBlock(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === ReadingTimeServiceProvider::BLOCK || $this->hasBlock((array) ($block['children'] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $blocks
     */
    protected function countWords(array $blocks): int
    {
        $words = 0;

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $text = is_string($block['content'] ?? null) ? html_entity_decode(strip_tags($block['content'])) : '';
            $words += str_word_count($text);
            $words += $this->countWords((array) ($block['children'] ?? []));
        }

        return $words;
    }
}

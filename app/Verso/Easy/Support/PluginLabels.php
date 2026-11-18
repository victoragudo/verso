<?php

namespace App\Verso\Easy\Support;

use App\Verso\I18n\Translate;

// Remembers which plugin registered each item, so its English labels are translated with the plugin prefix first.
trait PluginLabels
{
    /** @var array<string, string> plugin slug by item key */
    protected array $owners = [];

    public function pluginOf(string $key): ?string
    {
        return $this->owners[$key] ?? null;
    }

    protected function rememberOwner(string $key, ?string $plugin): void
    {
        if ($plugin === null || $plugin === '') {
            unset($this->owners[$key]);

            return;
        }

        $this->owners[$key] = $plugin;
    }

    // The label in the admin language. Labels stay in English at boot and are translated when they are shown.
    protected function translated(string $key, string $text, ?string $fallbackPlugin = null): string
    {
        return $text === '' ? '' : Translate::text($text, plugin: $this->pluginOf($key) ?? $fallbackPlugin);
    }

    /**
     * Search words in the admin language plus the English ones, so both find the item.
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    protected function translatedKeywords(string $key, array $keywords, ?string $fallbackPlugin = null): array
    {
        $translated = array_map(fn (string $keyword): string => $this->translated($key, $keyword, $fallbackPlugin), $keywords);

        return array_values(array_unique([...$translated, ...$keywords]));
    }
}

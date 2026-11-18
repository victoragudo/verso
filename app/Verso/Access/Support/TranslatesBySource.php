<?php

namespace App\Verso\Access\Support;

use App\Verso\I18n\Translate;

trait TranslatesBySource
{
    private const TRANSLATED_SOURCES = ['plugin', 'theme'];

    public function plugin(): ?string
    {
        $parts = explode(':', $this->source, 2);

        return count($parts) === 2 && in_array($parts[0], self::TRANSLATED_SOURCES, true) && $parts[1] !== '' ? $parts[1] : null;
    }

    private function translateBySource(string $text): string
    {
        return $text === '' ? '' : Translate::text($text, plugin: $this->plugin());
    }
}

<?php

namespace App\Verso\Plugins\Support;

use App\Verso\I18n\Translate;
use App\Verso\I18n\Translation\JsonFile;
use App\Verso\Plugins\Plugin;

class PluginTranslations
{
    /** @var array<string, array<string, string>> */
    protected array $files = [];

    public function text(Plugin $plugin, string $text): string
    {
        if ($text === '') {
            return $text;
        }

        return $this->lines($plugin)[$text] ?? Translate::text($text, plugin: $plugin->slug);
    }

    public function name(Plugin $plugin): string
    {
        return $this->text($plugin, $plugin->manifest !== null ? $plugin->manifest->name : $plugin->slug);
    }

    public function description(Plugin $plugin): ?string
    {
        return $plugin->manifest === null ? null : $this->text($plugin, $plugin->manifest->description);
    }

    /**
     * @return array<string, string>
     */
    protected function lines(Plugin $plugin): array
    {
        $locale = app()->getLocale();
        $folder = trim((string) config('verso.i18n.extension_lang_path'), '/\\');

        return $this->files[$plugin->slug.'|'.$locale] ??= JsonFile::read(
            $plugin->path.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$locale.'.json',
        );
    }
}

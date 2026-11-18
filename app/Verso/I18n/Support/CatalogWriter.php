<?php

namespace App\Verso\I18n\Support;

use App\Verso\I18n\Translation\JsonFile;

// Turns extracted keys into lang/en.json files (core, each plugin, each theme) and compares the other languages with them.
class CatalogWriter
{
    /**
     * @param  list<string>  $locales  every admin language, English included
     */
    public function __construct(
        protected string $basePath,
        protected array $locales,
        protected string $source = 'en',
        protected string $folder = 'lang',
    ) {}

    /**
     * Keys grouped by the lang folder they belong to (key without the prefix => English text).
     *
     * @param  iterable<string>  $keys
     * @return array<string, array<string, string>>
     */
    public function catalogs(iterable $keys): array
    {
        $catalogs = [$this->basePath.'/lang' => []];

        foreach ($keys as $key) {
            [$folder, $plain] = $this->target($key);
            $catalogs[$folder][$plain] = $this->englishText($plain);
        }

        foreach ($catalogs as &$lines) {
            ksort($lines, SORT_STRING);
        }

        return $catalogs;
    }

    // The folder for a key and the key without its prefix. Unknown prefixes stay in core.
    /**
     * @return array{0: string, 1: string}
     */
    public function target(string $key): array
    {
        if (preg_match('/^([a-z0-9][a-z0-9-]*)::(.+)$/s', $key, $match)) {
            foreach (['plugins', 'themes'] as $kind) {
                if (is_dir($this->basePath.'/'.$kind.'/'.$match[1])) {
                    return [$this->basePath.'/'.$kind.'/'.$match[1].'/'.$this->folder, $match[2]];
                }
            }
        }

        return [$this->basePath.'/lang', $key];
    }

    /**
     * Writes the English files. Returns the files that changed.
     *
     * @param  array<string, array<string, string>>  $catalogs
     * @return list<string>
     */
    public function writeSource(array $catalogs, bool $dryRun = false): array
    {
        $changed = [];

        foreach ($catalogs as $folder => $lines) {
            $path = $folder.'/'.$this->source.'.json';

            if (JsonFile::read($path) === $lines && ($lines !== [] || is_file($path))) {
                continue;
            }

            $changed[] = $path;

            if (! $dryRun) {
                JsonFile::write($path, $lines);
            }
        }

        return $changed;
    }

    /**
     * Missing and extra keys per translation file.
     *
     * @param  array<string, array<string, string>>  $catalogs
     * @return array<string, array{missing: list<string>, extra: list<string>}>
     */
    public function compare(array $catalogs): array
    {
        $report = [];

        foreach ($catalogs as $folder => $lines) {
            foreach ($this->locales as $locale) {
                if ($locale === $this->source) {
                    continue;
                }

                $path = $folder.'/'.$locale.'.json';
                $translated = JsonFile::read($path);
                $report[$path] = [
                    'missing' => array_values(array_diff(array_keys($lines), array_keys(array_filter($translated, fn (string $text): bool => $text !== '')))),
                    'extra' => array_values(array_diff(array_keys($translated), array_keys($lines))),
                ];
            }
        }

        return $report;
    }

    /**
     * Removes keys that are no longer used from the translation files.
     *
     * @param  array<string, array<string, string>>  $catalogs
     */
    public function prune(array $catalogs): int
    {
        $removed = 0;

        foreach ($this->compare($catalogs) as $path => $result) {
            if ($result['extra'] === [] || ! is_file($path)) {
                continue;
            }

            $lines = array_diff_key(JsonFile::read($path), array_flip($result['extra']));
            $removed += count($result['extra']);
            JsonFile::write($path, $lines);
        }

        return $removed;
    }

    // English is the key itself, except that ICU plural keys are the same as their text too.
    protected function englishText(string $key): string
    {
        return $key;
    }
}

<?php

namespace App\Verso\I18n\Translation;

use JsonException;

// Reads and writes flat lang JSON files (key => text).
class JsonFile
{
    /**
     * Strings of a lang file, or an empty list when it is missing or broken.
     *
     * @return array<string, string>
     */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $lines = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $lines[(string) $key] = $value;
            }
        }

        return $lines;
    }

    /**
     * Writes keys in a stable order, with readable unicode and slashes, so diffs stay small.
     *
     * @param  array<string, string>  $lines
     */
    public static function write(string $path, array $lines): void
    {
        ksort($lines, SORT_STRING);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $json = json_encode($lines === [] ? new \stdClass : $lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($path, $json."\n");
    }
}

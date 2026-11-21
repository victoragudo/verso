<?php

use App\Verso\I18n\Locales;
use App\Verso\I18n\Support\IcuMessage;
use App\Verso\I18n\Translation\JsonFile;

// Every lang folder with an English catalog: core, each plugin and each theme.
function langCatalogs(): array
{
    return array_values(array_filter([base_path('lang'), ...glob(base_path('plugins/*/lang')) ?: [], ...glob(base_path('themes/*/lang')) ?: []], fn (string $folder): bool => is_file($folder.'/en.json')));
}

// The placeholders a text uses: ICU arguments and Laravel :names.
function textPlaceholders(string $text): array
{
    preg_match_all('/(?<![\w:\/]):([a-z_][a-z0-9_]*)/i', $text, $laravel);
    $names = [...IcuMessage::arguments($text), ...array_map(fn (string $name): string => ':'.$name, $laravel[1])];
    sort($names);

    return array_values(array_unique($names));
}

// The <tag> names a text uses for rich text.
function textTags(string $text): array
{
    preg_match_all('/<\/?([a-z][a-z0-9]*)>/', $text, $tags);
    $names = $tags[1];
    sort($names);

    return $names;
}

$languages = fn (): array => array_values(array_diff(app(Locales::class)->codes(), ['en']));

it('has a catalog for core and every language', function () use ($languages) {
    expect(is_file(base_path('lang/en.json')))->toBeTrue();

    foreach ($languages() as $locale) {
        expect(is_file(base_path("lang/{$locale}.json")))->toBeTrue("lang/{$locale}.json is missing");
    }
});

it('translates every key in every language', function () use ($languages) {
    $problems = [];

    foreach (langCatalogs() as $folder) {
        $english = JsonFile::read($folder.'/en.json');

        foreach ($languages() as $locale) {
            $translated = JsonFile::read($folder.'/'.$locale.'.json');
            $missing = array_diff_key($english, array_filter($translated, fn (string $text): bool => trim($text) !== ''));
            $extra = array_diff_key($translated, $english);

            if ($missing !== [] || $extra !== []) {
                $problems[] = str_replace(base_path().'/', '', $folder)."/{$locale}.json: ".count($missing).' missing ('.implode(' | ', array_slice(array_keys($missing), 0, 3)).'), '.count($extra).' unused';
            }
        }
    }

    expect($problems)->toBe([]);
});

it('keeps the same placeholders and tags as English', function () use ($languages) {
    $problems = [];

    foreach (langCatalogs() as $folder) {
        $english = JsonFile::read($folder.'/en.json');

        foreach ($languages() as $locale) {
            foreach (JsonFile::read($folder.'/'.$locale.'.json') as $key => $text) {
                if (! isset($english[$key])) {
                    continue;
                }

                if (textPlaceholders($english[$key]) !== textPlaceholders($text) || textTags($english[$key]) !== textTags($text)) {
                    $problems[] = "{$locale}: {$key} => {$text}";
                }
            }
        }
    }

    expect($problems)->toBe([]);
});

// True when a text has no words once placeholders and tags are removed, like "{count} / {total}".
function hasNoWords(string $text): bool
{
    $words = preg_replace(['/\{[^{}]*\}/', '/<\/?[a-z][a-z0-9]*>/', '/(?<![\w:\/]):[a-z_][a-z0-9_]*/i'], '', $text);

    return preg_match('/\p{L}/u', (string) $words) !== 1;
}

it('translates texts instead of copying the English', function () use ($languages) {
    // Texts that are right in English too (brands, units, code, loan words), per catalog: "*" for every language or a list of codes.
    $allowed = json_decode((string) file_get_contents(__DIR__.'/same-as-english.json'), true, 512, JSON_THROW_ON_ERROR);
    $problems = [];

    foreach (langCatalogs() as $folder) {
        $catalog = str_replace(base_path().'/', '', $folder);
        $english = JsonFile::read($folder.'/en.json');

        foreach ($languages() as $locale) {
            foreach (JsonFile::read($folder.'/'.$locale.'.json') as $key => $text) {
                if (($english[$key] ?? null) !== $text || hasNoWords($text)) {
                    continue;
                }

                $locales = $allowed[$catalog][$key] ?? [];

                if ($locales !== '*' && ! in_array($locale, $locales, true)) {
                    $problems[] = "{$catalog}/{$locale}.json: {$key}";
                }
            }
        }
    }

    expect($problems)->toBe([], 'Translate these texts, or add them to tests/Feature/I18n/same-as-english.json when English is right.');
});

it('lists only existing keys as allowed English', function () {
    $allowed = json_decode((string) file_get_contents(__DIR__.'/same-as-english.json'), true, 512, JSON_THROW_ON_ERROR);
    $stale = [];

    foreach ($allowed as $catalog => $keys) {
        $english = JsonFile::read(base_path($catalog.'/en.json'));

        foreach (array_keys($keys) as $key) {
            if (! isset($english[$key])) {
                $stale[] = "{$catalog}: {$key}";
            }
        }
    }

    expect($stale)->toBe([]);
});

it('keeps ICU plurals complete', function () use ($languages) {
    $problems = [];

    foreach (langCatalogs() as $folder) {
        foreach ($languages() as $locale) {
            foreach (JsonFile::read($folder.'/'.$locale.'.json') as $key => $text) {
                if (preg_match('/\{\s*\w+\s*,\s*(plural|selectordinal|select)\s*,/', $text) && ! preg_match('/\bother\s*\{/', $text)) {
                    $problems[] = "{$locale}: {$key} has no other branch";
                }

                if (substr_count($text, '{') !== substr_count($text, '}')) {
                    $problems[] = "{$locale}: {$key} has unbalanced braces";
                }
            }
        }
    }

    expect($problems)->toBe([]);
});

it('has the Laravel message files in every language', function () use ($languages) {
    foreach ($languages() as $locale) {
        foreach (['auth', 'pagination', 'passwords', 'validation'] as $file) {
            $english = require base_path("lang/en/{$file}.php");
            $translated = require base_path("lang/{$locale}/{$file}.php");

            expect(array_keys($translated))->toBe(array_keys($english), "lang/{$locale}/{$file}.php keys differ");
        }
    }
});

it('keeps the English catalogs in step with the code', function () {
    $this->artisan('verso:lang-extract', ['--check' => true])->assertSuccessful();
});

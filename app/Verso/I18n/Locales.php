<?php

namespace App\Verso\I18n;

// The languages the admin can be shown in, and how other language codes map to them.
class Locales
{
    // Names and directions of every language Verso has an admin translation for.
    protected const KNOWN = [
        'en' => ['English', 'English', 'ltr'],
        'es' => ['Español', 'Spanish', 'ltr'],
        'zh-Hans' => ['简体中文', 'Chinese (Simplified)', 'ltr'],
        'hi' => ['हिन्दी', 'Hindi', 'ltr'],
        'ar' => ['العربية', 'Arabic', 'rtl'],
        'pt-BR' => ['Português (Brasil)', 'Portuguese (Brazil)', 'ltr'],
        'bn' => ['বাংলা', 'Bengali', 'ltr'],
        'ru' => ['Русский', 'Russian', 'ltr'],
        'ja' => ['日本語', 'Japanese', 'ltr'],
        'fr' => ['Français', 'French', 'ltr'],
        'de' => ['Deutsch', 'German', 'ltr'],
        'id' => ['Bahasa Indonesia', 'Indonesian', 'ltr'],
        'tr' => ['Türkçe', 'Turkish', 'ltr'],
        'ko' => ['한국어', 'Korean', 'ltr'],
        'it' => ['Italiano', 'Italian', 'ltr'],
    ];

    // Other codes for the same language, like the ones browsers send.
    protected const ALIASES = [
        'zh' => 'zh-Hans',
        'zh-cn' => 'zh-Hans',
        'zh-sg' => 'zh-Hans',
        'zh-hans-cn' => 'zh-Hans',
        'pt' => 'pt-BR',
        'pt-pt' => 'pt-BR',
        'in' => 'id',
    ];

    // Languages written right to left, also for site languages the admin has no translation for.
    protected const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur', 'ps', 'yi', 'dv', 'ckb'];

    /** @var array<string, Locale>|null */
    protected ?array $enabled = null;

    /**
     * @param  list<string>  $codes  enabled admin languages, from config
     */
    public function __construct(
        protected array $codes,
        protected string $fallback = 'en',
    ) {}

    /**
     * @return array<string, Locale>
     */
    public function all(): array
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }

        $enabled = [];

        foreach ([$this->fallback, ...$this->codes] as $code) {
            if (isset(self::KNOWN[$code])) {
                [$name, $english, $dir] = self::KNOWN[$code];
                $enabled[$code] = new Locale($code, $name, $english, $dir);
            }
        }

        return $this->enabled = $enabled;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    public function get(string $code): Locale
    {
        return $this->all()[$this->normalize($code) ?? $this->fallback()] ?? $this->all()[$this->fallback()];
    }

    public function fallback(): string
    {
        return isset(self::KNOWN[$this->fallback]) ? $this->fallback : 'en';
    }

    public function dir(string $code): string
    {
        return $this->get($code)->dir;
    }

    // The text direction of any language code, like "he" or "fa-IR".
    public function directionOf(string $code): string
    {
        $base = strtolower(explode('-', str_replace('_', '-', $code))[0]);

        return in_array($base, self::RTL_LANGUAGES, true) ? 'rtl' : 'ltr';
    }

    // An enabled admin language for a code like "pt", "zh_CN" or "de-AT", or null when none fits.
    public function normalize(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = str_replace('_', '-', trim($code));
        $lower = strtolower($code);

        foreach ($this->codes() as $known) {
            if (strtolower($known) === $lower) {
                return $known;
            }
        }

        $alias = self::ALIASES[$lower] ?? null;

        if ($alias !== null && $this->has($alias)) {
            return $alias;
        }

        $base = explode('-', $lower)[0];

        if ($base !== $lower) {
            return $this->normalize($base);
        }

        return null;
    }

    // The best admin language for an Accept-Language header, or null.
    public function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        $ranked = [];

        foreach (explode(',', $header) as $index => $part) {
            $pieces = explode(';', trim($part));
            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (str_starts_with(trim($parameter), 'q=')) {
                    $quality = (float) substr(trim($parameter), 2);
                }
            }

            if ($pieces[0] !== '' && $pieces[0] !== '*' && $quality > 0) {
                $ranked[] = [$pieces[0], $quality, $index];
            }
        }

        usort($ranked, fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        foreach ($ranked as [$code]) {
            if (($locale = $this->normalize($code)) !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * @return list<array{code: string, name: string, englishName: string, dir: string}>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (Locale $locale): array => $locale->toArray(), $this->all()));
    }
}

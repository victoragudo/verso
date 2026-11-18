<?php

namespace App\Verso\I18n\Translation;

use App\Verso\I18n\Locales;
use Illuminate\Contracts\Cache\Repository;

// The strings the admin needs in one language: core lang/<code>.json plus plugin and theme files.
// The version is a hash of the files, so the bundle URL changes whenever a translation changes.
class TranslationBundle
{
    /** @var array<string, string> */
    protected array $versions = [];

    public function __construct(
        protected Locales $locales,
        protected TranslationNamespaces $namespaces,
        protected ?Repository $cache,
        protected string $langPath,
        protected int $cacheTtl,
    ) {}

    public function version(string $locale): string
    {
        if (isset($this->versions[$locale])) {
            return $this->versions[$locale];
        }

        $parts = [$locale];

        foreach ($this->files($locale) as $prefix => $file) {
            $parts[] = $prefix.'|'.$file.'|'.(@filemtime($file) ?: 0).'|'.(@filesize($file) ?: 0);
        }

        return $this->versions[$locale] = substr(hash('xxh128', implode("\n", $parts)), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    public function messages(string $locale): array
    {
        if ($this->cache === null) {
            return $this->build($locale);
        }

        /** @var array<string, string> */
        return $this->cache->remember('verso.i18n.bundle.'.$locale.'.'.$this->version($locale), $this->cacheTtl, fn (): array => $this->build($locale));
    }

    // False for the source language: its keys are the English text, so the browser needs no file.
    public function needsBundle(string $locale): bool
    {
        return $locale !== $this->locales->fallback();
    }

    /**
     * @return array<string, string>
     */
    protected function build(string $locale): array
    {
        $messages = [];

        foreach ($this->files($locale) as $prefix => $file) {
            foreach (JsonFile::read($file) as $key => $text) {
                if ($text !== '') {
                    $messages[$prefix === '' ? $key : $prefix.'::'.$key] = $text;
                }
            }
        }

        return $messages;
    }

    /**
     * Files that make the bundle: core first (prefix ''), then each plugin and theme.
     *
     * @return array<string, string>
     */
    protected function files(string $locale): array
    {
        $files = ['' => $this->langPath.DIRECTORY_SEPARATOR.$locale.'.json'];

        foreach ($this->namespaces->all() as $slug => $folder) {
            $files[$slug] = $folder.DIRECTORY_SEPARATOR.$locale.'.json';
        }

        return array_filter($files, is_file(...));
    }
}

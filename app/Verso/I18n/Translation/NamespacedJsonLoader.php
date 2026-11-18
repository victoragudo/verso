<?php

namespace App\Verso\I18n\Translation;

use Illuminate\Contracts\Translation\Loader;

// Wraps Laravel's file loader and adds the JSON files of plugins and themes with the "<slug>::" prefix.
class NamespacedJsonLoader implements Loader
{
    public function __construct(
        protected Loader $loader,
        protected TranslationNamespaces $namespaces,
        protected string $folder = 'lang',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group !== '*' || $namespace !== '*') {
            return $lines;
        }

        foreach ($this->namespaces->all() as $slug => $folder) {
            foreach (JsonFile::read($folder.DIRECTORY_SEPARATOR.$locale.'.json') as $key => $value) {
                $lines[$slug.'::'.$key] = $value;
            }
        }

        // An empty translation counts as missing, so the English text shows.
        return array_filter($lines, fn (mixed $line): bool => $line !== '');
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }
}

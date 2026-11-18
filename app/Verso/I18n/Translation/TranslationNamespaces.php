<?php

namespace App\Verso\I18n\Translation;

use Closure;

// Lang folders of plugins and themes. Their keys get the "<slug>::" prefix, so two extensions never clash.
class TranslationNamespaces
{
    /** @var array<string, string> */
    protected array $folders = [];

    /** @var list<Closure(): array<string, string>> */
    protected array $resolvers = [];

    public function add(string $slug, string $folder): void
    {
        if (is_dir($folder)) {
            $this->folders[$slug] = $folder;
        }
    }

    // A callback that returns more folders (slug => folder) when they are read, like the active theme.
    public function resolveUsing(Closure $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $folders = $this->folders;

        foreach ($this->resolvers as $resolver) {
            foreach ($resolver() as $slug => $folder) {
                if (! isset($folders[$slug]) && is_dir($folder)) {
                    $folders[$slug] = $folder;
                }
            }
        }

        ksort($folders);

        return $folders;
    }

    public function forget(string $slug): void
    {
        unset($this->folders[$slug]);
    }
}

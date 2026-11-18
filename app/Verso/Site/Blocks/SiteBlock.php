<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;

// Shared helpers of the site blocks (logo, navigation, footer links...). They use the plugin block
// contracts, so the editor shows their fields like any plugin block and plugins can add more.
abstract class SiteBlock implements HasBlockFields, PluginBlock
{
    public function __construct(protected UrlSanitizer $urls) {}

    /**
     * @param  array<mixed>  $block
     */
    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        return $this->html($attrs);
    }

    /**
     * @param  array<mixed>  $attrs
     */
    abstract protected function html(array $attrs): string;

    /**
     * @param  array<mixed>  $attrs
     */
    protected function text(array $attrs, string $name, string $default = ''): string
    {
        $value = $attrs[$name] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @param  array<mixed>  $attrs
     */
    protected function flag(array $attrs, string $name, bool $default = false): bool
    {
        return is_bool($attrs[$name] ?? null) ? $attrs[$name] : $default;
    }

    // A safe URL, or null when it is empty or not allowed.
    protected function url(string $url): ?string
    {
        return $url === '' ? null : $this->urls->clean($url);
    }

    // A link with escaped text. External links open in the same tab unless asked.
    protected function link(string $url, string $label, string $class = '', bool $newTab = false): string
    {
        return '<a'.($class !== '' ? ' class="'.e($class).'"' : '').' href="'.e($url).'"'
            .($newTab ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.e($label).'</a>';
    }

    /**
     * Lines of "Label | URL" with a safe URL. Lines without a URL are skipped.
     *
     * @return list<array{label: string, url: string}>
     */
    protected function linkLines(string $text): array
    {
        $links = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $parts = array_map(trim(...), explode('|', $line, 2));
            $url = $this->url($parts[1] ?? $parts[0]);

            if ($url !== null && $parts[0] !== '') {
                $links[] = ['label' => count($parts) === 2 ? $parts[0] : $url, 'url' => $url];
            }
        }

        return $links;
    }
}

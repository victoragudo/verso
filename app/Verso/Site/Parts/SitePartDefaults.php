<?php

namespace App\Verso\Site\Parts;

use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Str;

// The blocks a part starts with in the Site Editor before anyone edits it.
// A theme can ship them in parts/<key>.json; otherwise a simple starter is used.
class SitePartDefaults
{
    // Folder of the starter block files inside a theme.
    public const FOLDER = 'parts';

    public function __construct(protected ThemeManager $themes) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function blocks(Theme $theme, string $key): array
    {
        $file = $this->themes->locateFile($theme, self::FOLDER.'/'.$key.'.json');
        $decoded = $file === null ? null : json_decode((string) file_get_contents($file), true);

        if (is_array($decoded) && is_array($decoded['blocks'] ?? null) && array_is_list($decoded['blocks'])) {
            return $this->withIds($decoded['blocks'], $key);
        }

        return $this->withIds($this->starter(Str::before($key, '-')), $key);
    }

    /**
     * A friendly name for a part key: "header" is "Header", "footer-minimal" is "Footer: Minimal"
     * when the theme also has "footer", and "author-box" is "Author Box".
     *
     * @param  list<string>  $available  part keys of the theme
     */
    public function label(string $key, array $available = []): string
    {
        $name = Str::before($key, '-');

        if ($name !== $key && in_array($name, $available, true)) {
            return Str::headline($name).': '.Str::headline(Str::after($key, '-'));
        }

        return Str::headline($key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function starter(string $name): array
    {
        $row = fn (array $children, array $layout = []): array => [
            'type' => 'section',
            'attrs' => ['design' => ['layout' => ['display' => 'flex', 'align' => 'center', 'justify' => 'between', 'gap' => '1.5rem', 'wrap' => 'wrap', ...$layout], 'spacing' => ['padding' => ['top' => '1rem', 'bottom' => '1rem']]]],
            'children' => $children,
        ];

        return match ($name) {
            'header' => [$row([
                ['type' => 'site-logo'],
                ['type' => 'navigation', 'attrs' => ['location' => 'primary', 'label' => 'Main']],
            ])],
            'footer' => [$row([
                ['type' => 'copyright'],
                ['type' => 'navigation', 'attrs' => ['location' => 'footer', 'label' => 'Footer', 'collapse' => false]],
            ])],
            default => [['type' => 'paragraph', 'content' => '']],
        };
    }

    /**
     * Gives every block a stable id, like "header-1", when it has none.
     *
     * @param  array<mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    protected function withIds(array $blocks, string $prefix, int &$counter = 0): array
    {
        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            $counter++;
            $block['id'] = is_string($block['id'] ?? null) && $block['id'] !== '' ? $block['id'] : $prefix.'-'.$counter;

            if (is_array($block['children'] ?? null)) {
                $block['children'] = $this->withIds($block['children'], $prefix, $counter);
            }

            $result[] = $block;
        }

        return $result;
    }
}

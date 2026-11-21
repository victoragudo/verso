<?php

namespace App\Verso\Site\Parts;

use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Str;

// The blocks a part starts with in the Site Editor before anyone edits it.
// A theme can ship them in parts/<key>.json; otherwise a simple starter is used.
class SitePartDefaults
{
    // Folder of the starter block files inside a theme.
    public const FOLDER = 'parts';

    public function __construct(
        protected ThemeManager $themes,
        protected PartVariants $variants,
        protected ThemeText $text,
    ) {}

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
     * The name of a part in the admin language. It uses the labels in theme.json ("parts"), so
     * "header-centered" is "Header: Centered". Without a label, "author-box" is "Author box".
     *
     * @param  list<string>  $available  part keys of the theme
     */
    public function label(Theme $theme, string $key, array $available = []): string
    {
        foreach ($this->variants->definitions($theme) as $name => $definition) {
            foreach ($definition['variants'] as $variant) {
                if ($variant['part'] === $key) {
                    return $key === $name ? $definition['label'] : $this->joined($definition['label'], $variant['label']);
                }
            }
        }

        $own = $theme->parts[$key]['label'] ?? null;

        if (is_string($own) && $own !== '') {
            return $this->text->text($theme, $own);
        }

        $name = Str::before($key, '-');

        if ($name !== $key && in_array($name, $available, true)) {
            return $this->joined($this->plain($theme, $name), $this->plain($theme, Str::after($key, '-')));
        }

        return $this->plain($theme, $key);
    }

    // "footer-minimal" as "Footer minimal", translated when a theme or the core has the text.
    protected function plain(Theme $theme, string $key): string
    {
        return $this->text->text($theme, Str::ucfirst(str_replace('-', ' ', $key)));
    }

    protected function joined(string $part, string $variant): string
    {
        return __(':part: :variant', ['part' => $part, 'variant' => $variant]);
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

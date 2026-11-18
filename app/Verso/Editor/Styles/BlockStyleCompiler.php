<?php

namespace App\Verso\Editor\Styles;

// Turns attrs.design (and the admin only attrs.customCss) of a block into one scoped CSS class.
// Same styles give the same class, so a page prints each rule once. No style attributes are written.
class BlockStyleCompiler
{
    // Prefix of the generated class names.
    public const CLASS_PREFIX = 'vs-';

    // Words that are never allowed in custom CSS, because they load things or break out of the rule.
    protected const BLOCKED_CSS = ['{', '}', '<', '>', '@', '\\', 'url(', 'expression(', 'javascript:', 'behavior', '-moz-binding'];

    /**
     * @param  array<string, int>  $breakpoints  largest width in pixels of each screen size, like ["tablet" => 1024, "mobile" => 640]
     */
    public function __construct(
        protected BlockStyleSchema $schema,
        protected array $breakpoints,
        protected int $maxCustomCss,
        protected ?StylePresets $presets = null,
    ) {}

    /**
     * The class and the CSS rules for a block, or null when the block has no valid styles.
     *
     * @param  array<mixed>  $attrs
     */
    public function compile(array $attrs): ?CompiledStyle
    {
        $style = is_array($attrs[BlockStyleSchema::ATTR] ?? null) ? $attrs[BlockStyleSchema::ATTR] : [];
        $declarations = $this->declarations($this->withPreset($style, $attrs[BlockStyleSchema::PRESET_ATTR] ?? null));
        $custom = $this->sanitizeCustomCss($attrs[BlockStyleSchema::CUSTOM_CSS_ATTR] ?? null);

        if ($custom !== '') {
            $declarations['base'][] = $custom;
        }

        $declarations = array_filter($declarations);

        if ($declarations === []) {
            return null;
        }

        $class = self::CLASS_PREFIX.substr(hash('xxh128', (string) json_encode($declarations)), 0, 10);

        return new CompiledStyle($class, $this->rules($class, $declarations));
    }

    /**
     * The block styles on top of the styles of its preset. A preset that does not exist adds nothing.
     *
     * @param  array<mixed>  $style
     * @return array<mixed>
     */
    public function withPreset(array $style, mixed $preset): array
    {
        $base = $this->presets?->design($preset) ?? [];

        return $base === [] ? $style : array_replace_recursive($base, $style);
    }

    // Keeps only "property: value;" lines with safe text. Returns an empty text when nothing is left.
    public function sanitizeCustomCss(mixed $css): string
    {
        if (! is_string($css) || trim($css) === '') {
            return '';
        }

        $css = mb_substr(str_replace(["\r", "\n"], ' ', $css), 0, $this->maxCustomCss);
        $kept = [];

        foreach (explode(';', $css) as $line) {
            if (preg_match('/^\s*(-?[a-z][a-z-]{0,40})\s*:\s*([^;]{1,300})$/i', $line, $match) !== 1) {
                continue;
            }

            if ($this->containsBlocked($match[2]) || $this->containsBlocked($match[1])) {
                continue;
            }

            $kept[] = strtolower($match[1]).':'.trim($match[2]);
        }

        return implode(';', $kept);
    }

    /**
     * CSS declarations grouped by screen size, in the order base, tablet, mobile.
     *
     * @param  array<mixed>  $style
     * @return array<string, list<string>>
     */
    protected function declarations(array $style): array
    {
        $declarations = array_fill_keys(BlockStyleSchema::DEVICES, []);

        if ($this->schema->errors($style) !== []) {
            return $declarations;
        }

        foreach ($this->schema->options() as $path => [$property, $kind, $rule]) {
            $value = data_get($style, $path);

            if ($value === null) {
                continue;
            }

            foreach ($this->schema->byDevice($value) as $device => $single) {
                $css = $this->schema->css($kind, $single, $rule);

                if ($device !== '' && $css !== null && $css !== '') {
                    $declarations[$device][] = $property.':'.($kind === 'overlay' ? 'inset 0 0 0 100vmax '.$css : $css);
                }
            }
        }

        return array_map($this->joinBackgrounds(...), $declarations);
    }

    /**
     * A gradient and a picture both set background-image: one line with the gradient on top of the picture.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    protected function joinBackgrounds(array $lines): array
    {
        $prefix = 'background-image:';
        $images = array_filter($lines, fn (string $line): bool => str_starts_with($line, $prefix));

        if (count($images) < 2) {
            return $lines;
        }

        $joined = $prefix.implode(', ', array_map(fn (string $line): string => substr($line, strlen($prefix)), $images));
        $first = array_key_first($images);
        $result = [];

        foreach ($lines as $index => $line) {
            if ($index === $first) {
                $result[] = $joined;
            } elseif (! isset($images[$index])) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, list<string>>  $declarations
     */
    protected function rules(string $class, array $declarations): string
    {
        $css = '';

        foreach ($declarations as $device => $lines) {
            $rule = '.'.$class.'{'.implode(';', $lines).'}';
            $width = $this->breakpoints[$device] ?? null;
            $css .= $device === 'base' || $width === null ? $rule : '@media (max-width:'.$width.'px){'.$rule.'}';
        }

        return $css;
    }

    protected function containsBlocked(string $text): bool
    {
        $text = strtolower($text);

        foreach (self::BLOCKED_CSS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }
}

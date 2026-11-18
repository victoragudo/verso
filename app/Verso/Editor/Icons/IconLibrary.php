<?php

namespace App\Verso\Editor\Icons;

use InvalidArgumentException;

// The bundled icon set of the icon blocks (Lucide, ISC license), plus icons that plugins and themes add.
// Each icon is the inner SVG of a 24 by 24 drawing with round strokes in the current text color.
class IconLibrary
{
    // Icon names: lowercase letters, digits and dashes.
    public const string NAME_PATTERN = '/^[a-z0-9][a-z0-9-]{0,60}$/';

    // Shapes an added icon may use. Anything else (scripts, links, styles, events) is refused.
    protected const string SAFE_SHAPES = '/^(<(path|circle|rect|line|polyline|polygon|ellipse)(\s+[a-z-]+="[0-9a-zA-Z .,#%-]*")*\s*\/>)+$/';

    /** @var array<string, string>|null name => inner SVG, read once */
    protected ?array $icons = null;

    /** @var array<string, string> icons added by plugins and themes */
    protected array $added = [];

    public function __construct(protected string $file) {}

    // Adds one icon. The drawing may only use plain SVG shapes.
    public function register(string $name, string $svg): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || preg_match(self::SAFE_SHAPES, trim($svg)) !== 1) {
            throw new InvalidArgumentException("The icon [{$name}] is not valid.");
        }

        $this->added[$name] = trim($svg);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->icons === null) {
            $data = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;
            $this->icons = is_array($data['icons'] ?? null) ? array_filter($data['icons'], 'is_string') : [];
        }

        return [...$this->icons, ...$this->added];
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    // The SVG of an icon. Without a label it is hidden from screen readers; with one it is a named image.
    public function svg(string $name, string $label = '', string $class = 'verso-icon'): string
    {
        $inner = $this->all()[$name] ?? null;

        if ($inner === null) {
            return '';
        }

        $name = $label === '' ? ' aria-hidden="true" focusable="false"' : ' role="img" aria-label="'.e($label).'"';

        return '<svg class="'.e($class).'" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'.$name.'>'.$inner.'</svg>';
    }
}

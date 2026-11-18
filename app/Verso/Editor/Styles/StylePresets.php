<?php

namespace App\Verso\Editor\Styles;

use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\Translate;
use App\Verso\Theme\ThemeManager;
use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

// Style presets for every block: core and theme presets, plus the ones people save in the editor.
// Saved presets live in the setting "editor.style_presets" and replace a core or theme preset with the same key.
class StylePresets
{
    // Setting that holds the saved presets: key => {label, design}.
    public const string SETTING = 'editor.style_presets';

    /** @var array<string, StylePreset> presets from code: core, plugins */
    protected array $registered = [];

    /** @var array<string, StylePreset>|null every preset, read once per request */
    protected ?array $all = null;

    /**
     * @param  Closure(): array<mixed>|null  $themePresets  the "editor.presets" list of the active theme
     */
    public function __construct(
        protected ?Settings $settings = null,
        protected ?Closure $themePresets = null,
        protected int $maxSaved = 100,
    ) {}

    // Adds a preset from code. Plugins call this in boot().
    public function register(StylePreset $preset): void
    {
        if (preg_match(StylePreset::KEY_PATTERN, $preset->key) !== 1) {
            throw new InvalidArgumentException("The style preset key [{$preset->key}] is not valid.");
        }

        $this->registered[$preset->key] = $preset;
        $this->all = null;
    }

    /** @return array<string, StylePreset> */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }

        $presets = [...$this->registered, ...$this->fromTheme()];

        foreach ($this->saved() as $key => $saved) {
            $base = $presets[$key] ?? null;
            $presets[$key] = new StylePreset($key, $saved['label'], $saved['design'], $base->source ?? StylePreset::SAVED, $base !== null);
        }

        return $this->all = $presets;
    }

    public function find(string $key): ?StylePreset
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The styles of a preset, or an empty list when it does not exist.
     *
     * @return array<string, mixed>
     */
    public function design(mixed $key): array
    {
        return is_string($key) ? ($this->find($key)->design ?? []) : [];
    }

    /**
     * Saves a preset. A new key is made from the label when none is given.
     *
     * @param  array<string, mixed>  $design
     */
    public function save(?string $key, string $label, array $design): StylePreset
    {
        $saved = $this->saved();
        $key ??= $this->newKey($label);

        if (preg_match(StylePreset::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException(Translate::text('This preset name can not be used.'));
        }

        if (! isset($saved[$key]) && count($saved) >= $this->maxSaved) {
            throw new InvalidArgumentException(Translate::text('You have saved the most presets allowed. Delete one first.'));
        }

        $saved[$key] = ['label' => $label, 'design' => $design];
        $this->store($saved);

        return $this->find($key) ?? throw new InvalidArgumentException('The preset was not saved.');
    }

    // Deletes a saved preset. A changed core or theme preset goes back to its original.
    public function delete(string $key): void
    {
        $saved = $this->saved();
        unset($saved[$key]);
        $this->store($saved);
    }

    /**
     * A short hash of the presets some blocks use, at any depth. It changes when one of those presets changes.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function fingerprint(array $blocks): string
    {
        $used = $this->used($blocks);

        if ($used === []) {
            return '';
        }

        sort($used);

        return substr(hash('xxh128', (string) json_encode(array_map(fn (string $key): array => $this->design($key), $used))), 0, 12);
    }

    /**
     * The preset keys some blocks use, at any depth.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<string>
     */
    public function used(array $blocks): array
    {
        $keys = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $preset = $block['attrs'][BlockStyleSchema::PRESET_ATTR] ?? null;

            if (is_string($preset) && $preset !== '') {
                $keys[$preset] = true;
            }

            if (is_array($block['children'] ?? null)) {
                foreach ($this->used($block['children']) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /** @return list<array{key: string, label: string, design: array<string, mixed>, source: string, changed: bool}> */
    public function toArray(): array
    {
        return array_values(array_map(
            fn (StylePreset $preset): array => ['label' => $preset->source === StylePreset::SAVED ? $preset->label : Translate::text($preset->label)] + $preset->toArray(),
            $this->all(),
        ));
    }

    /** @return array<string, array{label: string, design: array<string, mixed>}> */
    protected function saved(): array
    {
        $value = $this->settings?->get(self::SETTING, []);
        $saved = [];

        foreach (is_array($value) ? $value : [] as $key => $preset) {
            if (is_string($key) && preg_match(StylePreset::KEY_PATTERN, $key) === 1 && is_array($preset) && is_string($preset['label'] ?? null) && is_array($preset['design'] ?? null)) {
                $saved[$key] = ['label' => $preset['label'], 'design' => $preset['design']];
            }
        }

        return $saved;
    }

    /** @param array<string, array{label: string, design: array<string, mixed>}> $saved */
    protected function store(array $saved): void
    {
        $this->settings?->set(self::SETTING, $saved);
        $this->all = null;
    }

    /** @return array<string, StylePreset> */
    protected function fromTheme(): array
    {
        $presets = [];

        foreach ($this->themePresets === null ? [] : ($this->themePresets)() as $key => $preset) {
            if (is_string($key) && preg_match(StylePreset::KEY_PATTERN, $key) === 1 && is_array($preset) && is_string($preset['label'] ?? null) && is_array($preset['design'] ?? null)) {
                $presets[$key] = new StylePreset($key, $preset['label'], $preset['design'], StylePreset::THEME);
            }
        }

        return $presets;
    }

    // A free key made from the label, like "soft-card" or "soft-card-2".
    protected function newKey(string $label): string
    {
        $base = Str::limit(Str::slug($label), 32, '') ?: 'preset';
        $base = preg_match('/^[a-z0-9]/', $base) === 1 ? $base : 'preset-'.$base;
        $key = $base;
        $number = 2;

        while ($this->find($key) !== null) {
            $key = $base.'-'.$number++;
        }

        return $key;
    }

    // The theme presets of the active theme, for the service container.
    public static function themeReader(ThemeManager $themes): Closure
    {
        return fn (): array => (array) ($themes->active()->editor['presets'] ?? []);
    }
}

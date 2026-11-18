<?php

namespace App\Verso\Theme;

use InvalidArgumentException;
use JsonException;

// One installed theme, read from themes/<slug>/theme.json.
final readonly class Theme
{
    /**
     * @param  array<string, mixed>  $settings  settings schema, with the fields of the parent themes
     * @param  array<string, mixed>  $templates  page templates from theme.json, with the ones of the parent themes
     * @param  array<array-key, mixed>  $menus  menu locations from theme.json (name => label), with the ones of the parent themes
     * @param  array<array-key, mixed>  $styles  design tokens from theme.json, with the ones of the parent themes
     * @param  array<array-key, mixed>  $parts  part styles (variants) from theme.json, with the ones of the parent themes
     * @param  array<array-key, mixed>  $editor  editor limits from theme.json (allowed blocks, colors, fonts, spacing)
     * @param  array<array-key, mixed>  $easy  Easy mode extras from theme.json (style presets, font pairs, font files)
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $path,
        public string $version = '1.0.0',
        public string $description = '',
        public string $author = '',
        public array $settings = [],
        public ?string $parent = null,
        public array $templates = [],
        public array $parts = [],
        public array $styles = [],
        public array $menus = [],
        public array $editor = [],
        public array $easy = [],
    ) {}

    // Build a theme from its folder. Throws when theme.json is missing or broken.
    public static function fromDirectory(string $path): self
    {
        return self::fromManifest($path, self::readManifest($path));
    }

    /**
     * The decoded theme.json of a folder. Throws when it is missing or broken.
     *
     * @return array<mixed>
     */
    public static function readManifest(string $path): array
    {
        $file = $path.'/theme.json';

        if (! is_file($file)) {
            throw new InvalidArgumentException("The theme in [{$path}] has no theme.json file.");
        }

        try {
            $manifest = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("The theme.json in [{$path}] is not valid JSON.", previous: $exception);
        }

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("The theme.json in [{$path}] must be an object.");
        }

        return $manifest;
    }

    /**
     * Build a theme from its folder and decoded theme.json.
     *
     * @param  array<mixed>  $manifest
     */
    public static function fromManifest(string $path, array $manifest): self
    {
        $slug = basename($path);

        return new self(
            slug: $slug,
            name: self::text($manifest, 'name') ?: $slug,
            path: $path,
            version: self::text($manifest, 'version') ?: '1.0.0',
            description: self::text($manifest, 'description'),
            author: self::text($manifest, 'author'),
            settings: is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [],
            parent: self::text($manifest, 'parent') ?: null,
            templates: is_array($manifest['templates'] ?? null) ? $manifest['templates'] : [],
            parts: is_array($manifest['parts'] ?? null) ? $manifest['parts'] : [],
            styles: is_array($manifest['styles'] ?? null) ? $manifest['styles'] : [],
            menus: is_array($manifest['menus'] ?? null) ? $manifest['menus'] : [],
            editor: is_array($manifest['editor'] ?? null) ? $manifest['editor'] : [],
            easy: is_array($manifest['easy'] ?? null) ? $manifest['easy'] : [],
        );
    }

    // A copy with the settings and templates of the parent themes added. The child wins on the same key.
    public function inheriting(self $parent): self
    {
        return new self(
            slug: $this->slug,
            name: $this->name,
            path: $this->path,
            version: $this->version,
            description: $this->description,
            author: $this->author,
            settings: array_replace($parent->settings, $this->settings),
            parent: $this->parent,
            templates: array_replace($parent->templates, $this->templates),
            parts: array_replace($parent->parts, $this->parts),
            styles: array_replace_recursive($parent->styles, $this->styles),
            menus: array_replace($parent->menus, $this->menus),
            editor: array_replace($parent->editor, $this->editor),
            easy: array_replace_recursive($parent->easy, $this->easy),
        );
    }

    public function isChild(): bool
    {
        return $this->parent !== null;
    }

    public function viewsPath(): string
    {
        return $this->path.'/views';
    }

    // Full path of a file inside the theme, like "assets/theme.css".
    public function filePath(string $file): string
    {
        return $this->path.'/'.ltrim($file, '/');
    }

    public function hasFile(string $file): bool
    {
        return is_file($this->filePath($file));
    }

    /**
     * @return array{slug: string, name: string, version: string, description: string, author: string, parent: string|null}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'parent' => $this->parent,
        ];
    }

    /** @param array<mixed> $manifest */
    private static function text(array $manifest, string $key): string
    {
        $value = $manifest[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}

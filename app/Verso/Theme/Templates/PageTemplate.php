<?php

namespace App\Verso\Theme\Templates;

use Illuminate\Support\Str;

// A page template declared in theme.json, like a full width layout for pages.
final readonly class PageTemplate
{
    // Template names: lowercase letters, digits, dashes and underscores.
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    // Folder of template views when theme.json gives no view.
    public const VIEW_FOLDER = 'templates';

    // Content types a template is for when theme.json does not say.
    public const DEFAULT_TYPES = ['page'];

    /**
     * @param  list<string>  $types
     * @param  array<string, string>  $parts  part styles this template uses, like ["header" => "minimal"]
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $view,
        public array $types = self::DEFAULT_TYPES,
        public string $description = '',
        public array $parts = [],
    ) {}

    // Build a template from its theme.json entry. Returns null when the entry is broken.
    public static function fromManifest(string $name, mixed $config): ?self
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || ! is_array($config)) {
            return null;
        }

        $view = is_string($config['view'] ?? null) ? trim($config['view']) : self::VIEW_FOLDER.'.'.$name;

        if (preg_match(TemplateCandidates::NAME_PATTERN, $view) !== 1) {
            return null;
        }

        $types = $config['types'] ?? self::DEFAULT_TYPES;

        if (! is_array($types)) {
            return null;
        }

        $types = array_values(array_unique(array_filter($types, fn (mixed $type): bool => is_string($type) && $type !== '')));

        if ($types === []) {
            return null;
        }

        return new self(
            name: $name,
            label: is_string($config['label'] ?? null) && trim($config['label']) !== '' ? trim($config['label']) : Str::headline($name),
            view: $view,
            types: $types,
            description: is_string($config['description'] ?? null) ? trim($config['description']) : '',
            parts: self::partsFrom($config['parts'] ?? null),
        );
    }

    /**
     * Part styles from theme.json, like {"header": "minimal"}. Odd names are left out.
     *
     * @return array<string, string>
     */
    public static function partsFrom(mixed $parts): array
    {
        $clean = [];

        foreach (is_array($parts) ? $parts : [] as $part => $style) {
            if (is_string($part) && is_string($style) && preg_match(TemplateParts::NAME_PATTERN, $part) === 1 && preg_match(TemplateParts::NAME_PATTERN, $style) === 1) {
                $clean[$part] = $style;
            }
        }

        return $clean;
    }

    public function withText(string $label, string $description): self
    {
        return new self(
            name: $this->name,
            label: $label,
            view: $this->view,
            types: $this->types,
            description: $description,
            parts: $this->parts,
        );
    }

    public function appliesTo(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->name, 'label' => $this->label, 'description' => $this->description];
    }
}

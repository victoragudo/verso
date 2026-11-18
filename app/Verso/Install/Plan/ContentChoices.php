<?php

namespace App\Verso\Install\Plan;

// Demo content, the theme and the plugins to turn on.
final readonly class ContentChoices
{
    /**
     * @param  list<string>  $plugins
     */
    public function __construct(
        public bool $demo,
        public string $theme,
        public array $plugins,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            demo: filter_var($data['demo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            theme: (string) ($data['theme'] ?? 'default'),
            plugins: array_values(array_unique(array_map(strval(...), (array) ($data['plugins'] ?? [])))),
        );
    }
}

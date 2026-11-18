<?php

namespace App\Verso\Plugins;

use App\Verso\I18n\Translate;

// A plugin found on disk, with its manifest (when valid) and its state.
final readonly class Plugin
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $slug,
        public string $path,
        public ?PluginManifest $manifest,
        public array $errors = [],
        public bool $enabled = false,
        public bool $compatible = true,
    ) {}

    public function isValid(): bool
    {
        return $this->manifest !== null && $this->errors === [];
    }

    // A plugin can run only when its manifest is valid and it supports this Verso version.
    public function canBeEnabled(): bool
    {
        return $this->isValid() && $this->compatible;
    }

    public function withEnabled(bool $enabled): self
    {
        return new self($this->slug, $this->path, $this->manifest, $this->errors, $enabled, $this->compatible);
    }

    /**
     * Why the plugin cannot run, in plain words.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if (! $this->isValid()) {
            return array_map(fn (string $error): string => Translate::text($error), $this->errors);
        }

        if (! $this->compatible && $this->manifest !== null) {
            return [__('It needs Verso :requires, but this site runs Verso :version.', [
                'requires' => $this->manifest->requires,
                'version' => (string) config('verso.version'),
            ])];
        }

        return [];
    }

    // The state shown in the admin: enabled, disabled, invalid or incompatible.
    public function status(): string
    {
        return match (true) {
            ! $this->isValid() => 'invalid',
            ! $this->compatible => 'incompatible',
            $this->enabled => 'enabled',
            default => 'disabled',
        };
    }

    /**
     * Data for the admin UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->manifest !== null ? $this->manifest->name : $this->slug,
            'version' => $this->manifest?->version,
            'description' => $this->manifest?->description,
            'requires' => $this->manifest?->requires,
            'permissions' => $this->manifest !== null ? $this->manifest->toArray()['permissions'] : [],
            'status' => $this->status(),
            'enabled' => $this->enabled,
            'problems' => $this->problems(),
        ];
    }
}

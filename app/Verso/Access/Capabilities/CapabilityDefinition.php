<?php

namespace App\Verso\Access\Capabilities;

use App\Verso\Access\Enums\RiskLevel;
use App\Verso\Access\Support\TranslatesBySource;

// Describes one capability for people: name, help text, group and risk.
final readonly class CapabilityDefinition
{
    use TranslatesBySource;

    // Capability keys are lowercase words with dots, dashes or underscores, like "forms.messages".
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*(?:[.\-][a-z0-9_]+)*$/';

    /**
     * @param  list<string>  $implies  capabilities this one also gives, like manage_users gives list_users
     * @param  list<string>  $roles  built-in roles that get it by default (admins always have everything)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
        public string $group = CapabilityGroup::CONTENT,
        public RiskLevel $risk = RiskLevel::Low,
        public string $source = 'core',
        public ?string $section = null,
        public array $implies = [],
        public array $roles = [],
    ) {}

    public function displayLabel(): string
    {
        return $this->translateBySource($this->label);
    }

    public function displayDescription(): string
    {
        return $this->translateBySource($this->description);
    }

    public function displayGroup(): string
    {
        return $this->translateBySource($this->group);
    }

    public function displaySection(): ?string
    {
        return $this->section === null ? null : $this->translateBySource($this->section);
    }

    /**
     * @return array{key: string, label: string, description: string, group: string, section: string|null, risk: string, source: string, implies: list<string>}
     */
    public function present(): array
    {
        return [
            ...$this->toArray(),
            'label' => $this->displayLabel(),
            'description' => $this->displayDescription(),
            'group' => $this->displayGroup(),
            'section' => $this->displaySection(),
        ];
    }

    /**
     * Data for the admin UI.
     *
     * @return array{key: string, label: string, description: string, group: string, section: string|null, risk: string, source: string, implies: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'group' => $this->group,
            'section' => $this->section,
            'risk' => $this->risk->value,
            'source' => $this->source,
            'implies' => $this->implies,
        ];
    }
}

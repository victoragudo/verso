<?php

namespace App\Verso\Editor\Builder;

// A part of the editor that can be turned on or off per role and content type, like the code editor.
final readonly class EditorFeature
{
    /**
     * @param  list<string>  $roles  roles that have the feature when no setting is saved
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $roles,
    ) {}

    /** @return array{key: string, label: string, description: string, roles: list<string>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'description' => $this->description, 'roles' => $this->roles];
    }
}

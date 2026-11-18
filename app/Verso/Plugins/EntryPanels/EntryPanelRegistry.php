<?php

namespace App\Verso\Plugins\EntryPanels;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EntryPanel;
use InvalidArgumentException;

// Keeps the entry editor panels that plugins add.
class EntryPanelRegistry
{
    // Meta keys the core editor already uses.
    public const RESERVED_KEYS = ['seo', 'comments_open', 'featured_image', 'fields', 'template'];

    /** @var array<string, EntryPanel> */
    protected array $panels = [];

    public function register(EntryPanel $panel): void
    {
        if (in_array($panel->key(), self::RESERVED_KEYS, true) || preg_match('/^[a-z][a-z0-9_]*$/', $panel->key()) !== 1) {
            throw new InvalidArgumentException("The entry panel key \"{$panel->key()}\" is reserved or not valid.");
        }

        $this->panels[$panel->key()] = $panel;
    }

    public function find(string $key): ?EntryPanel
    {
        return $this->panels[$key] ?? null;
    }

    /**
     * Panels that show for this content type.
     *
     * @return list<EntryPanel>
     */
    public function forType(string $contentType): array
    {
        return array_values(array_filter(
            $this->panels,
            fn (EntryPanel $panel): bool => $panel->appliesTo($contentType),
        ));
    }

    /**
     * Validation rules for the meta values of the panels of this type.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $contentType): array
    {
        $rules = [];

        foreach ($this->forType($contentType) as $panel) {
            $rules['meta.'.$panel->key()] = ['nullable', 'array'];

            foreach ($panel->fields() as $field) {
                $rules['meta.'.$panel->key().'.'.$field->name] = $field->rules();
            }
        }

        return $rules;
    }

    /**
     * Shape sent to the entry editor.
     *
     * @return list<array{key: string, title: string, description: string, fields: list<array<string, mixed>>}>
     */
    public function toArray(string $contentType): array
    {
        return array_map(fn (EntryPanel $panel): array => [
            'key' => $panel->key(),
            'title' => $panel->title(),
            'description' => $panel->description(),
            'fields' => array_map(fn (BlockField $field): array => $field->toArray(), $panel->fields()),
        ], $this->forType($contentType));
    }
}

<?php

namespace App\Verso\Fields\Json;

use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;
use JsonException;

// Reads and writes field groups as JSON, for export, import and theme or plugin files.
class FieldGroupJson
{
    // Version of the export format.
    public const int VERSION = 1;

    /**
     * Groups from JSON: one group, a list of groups or {"field_groups": [...]}.
     *
     * @return list<FieldGroup>
     */
    public function decode(string $json, string $source = FieldGroup::SOURCE_DATABASE): array
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidFieldDefinition(__('The file is not valid JSON.'));
        }

        if (! is_array($data)) {
            throw new InvalidFieldDefinition(__('The file holds no field groups.'));
        }

        $items = match (true) {
            array_key_exists('field_groups', $data) => is_array($data['field_groups']) ? $data['field_groups'] : [],
            array_key_exists('key', $data) => [$data],
            array_is_list($data) => $data,
            default => [],
        };

        if ($items === []) {
            throw new InvalidFieldDefinition(__('The file holds no field groups.'));
        }

        $groups = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidFieldDefinition(__('Every field group must be an object.'));
            }

            $groups[] = FieldGroup::fromArray($item, $source);
        }

        return $groups;
    }

    /**
     * Pretty JSON for a download.
     *
     * @param  list<FieldGroup>  $groups
     */
    public function encode(array $groups): string
    {
        return json_encode([
            'version' => self::VERSION,
            'field_groups' => array_map(fn (FieldGroup $group): array => $group->toArray(), $groups),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}

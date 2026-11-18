<?php

namespace App\Verso\Fields\Definitions;

use App\Verso\Fields\Enums\FieldPosition;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;

// A named set of fields with the rules that say where it shows.
final readonly class FieldGroup
{
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_\-]*$/';

    public const int KEY_MAX_LENGTH = 80;

    // Where a group comes from: the admin (database), plugin code or a JSON file.
    public const string SOURCE_DATABASE = 'database';

    public const string SOURCE_CODE = 'code';

    public const string SOURCE_JSON = 'json';

    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $key,
        public string $title,
        public Location $location,
        public array $fields,
        public FieldPosition $position = FieldPosition::Main,
        public string $description = '',
        public int $order = 0,
        public bool $active = true,
        public string $source = self::SOURCE_CODE,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || strlen($key) > self::KEY_MAX_LENGTH) {
            throw new InvalidFieldDefinition(__('The field group key ":key" must start with a letter and use only a to z, 0 to 9, - and _.', ['key' => $key]));
        }

        if (trim($title) === '') {
            throw new InvalidFieldDefinition(__('The field group ":key" needs a title.', ['key' => $key]));
        }

        if ($fields === []) {
            throw new InvalidFieldDefinition(__('The field group ":key" needs at least one field.', ['key' => $key]));
        }

        if ($location->groups === []) {
            throw new InvalidFieldDefinition(__('The field group ":key" needs at least one location rule.', ['key' => $key]));
        }

        Field::ensureUniqueNames($fields, $key);
    }

    /**
     * Builds a group from its JSON shape, checking nesting depth and field count.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data, string $source = self::SOURCE_CODE, ?int $maxDepth = null, ?int $maxFields = null): self
    {
        $maxDepth ??= (int) config('verso.fields.max_depth');
        $maxFields ??= (int) config('verso.fields.max_fields');

        $fields = [];
        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            if (! is_array($field)) {
                throw new InvalidFieldDefinition(__('Every field must be an object.'));
            }

            $fields[] = Field::fromArray($field, 1, $maxDepth);
        }

        $count = array_sum(array_map(fn (Field $field): int => $field->count(), $fields));

        if ($count > $maxFields) {
            throw new InvalidFieldDefinition(__('A field group can hold at most :max fields.', ['max' => $maxFields]));
        }

        $text = fn (string $key): string => is_scalar($data[$key] ?? null) ? trim((string) $data[$key]) : '';

        return new self(
            key: $text('key'),
            title: $text('title'),
            location: Location::fromArray(is_array($data['location'] ?? null) ? $data['location'] : []),
            fields: $fields,
            position: FieldPosition::tryFrom($text('position')) ?? FieldPosition::Main,
            description: $text('description'),
            order: is_numeric($data['order'] ?? null) ? (int) $data['order'] : 0,
            active: (bool) ($data['active'] ?? true),
            source: $source,
        );
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function isEditable(): bool
    {
        return $this->source === self::SOURCE_DATABASE;
    }

    /**
     * The JSON shape used by export, import and the admin.
     *
     * @return array{key: string, title: string, description: string, position: string, order: int, active: bool, location: list<list<array{param: string, operator: string, value: string}>>, fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position->value,
            'order' => $this->order,
            'active' => $this->active,
            'location' => $this->location->toArray(),
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $this->fields),
        ];
    }
}

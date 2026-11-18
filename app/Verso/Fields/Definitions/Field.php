<?php

namespace App\Verso\Fields\Definitions;

use App\Verso\Fields\Enums\FieldType;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;

// One custom field: its name, type, settings and nested fields.
final readonly class Field
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public const int NAME_MAX_LENGTH = 64;

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<string>  $contentTypes
     * @param  list<string>  $roles
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public string $instructions = '',
        public bool $required = false,
        public mixed $default = null,
        public string $placeholder = '',
        public array $options = [],
        public int|float|null $min = null,
        public int|float|null $max = null,
        public int|float|null $step = null,
        public ?int $maxLength = null,
        public bool $multiple = false,
        public array $contentTypes = [],
        public ?string $taxonomy = null,
        public array $roles = [],
        public ?int $minRows = null,
        public ?int $maxRows = null,
        public ?Condition $condition = null,
        public array $fields = [],
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidFieldDefinition(__('The field name ":name" must start with a letter and use only a to z, 0 to 9 and _.', ['name' => $name]));
        }

        if (trim($label) === '') {
            throw new InvalidFieldDefinition(__('The field ":name" needs a label.', ['name' => $name]));
        }

        if ($type->hasOptions() && $options === []) {
            throw new InvalidFieldDefinition(__('The field ":name" needs at least one option.', ['name' => $name]));
        }

        if ($type === FieldType::TermRelation && ($taxonomy === null || $taxonomy === '')) {
            throw new InvalidFieldDefinition(__('The field ":name" needs a taxonomy.', ['name' => $name]));
        }

        if ($type->hasSubFields() && $fields === []) {
            throw new InvalidFieldDefinition(__('The field ":name" needs at least one nested field.', ['name' => $name]));
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidFieldDefinition(__('The field ":name" has a min bigger than its max.', ['name' => $name]));
        }

        if ($minRows !== null && $maxRows !== null && $minRows > $maxRows) {
            throw new InvalidFieldDefinition(__('The field ":name" has more min rows than max rows.', ['name' => $name]));
        }

        self::ensureUniqueNames($fields, $name);
    }

    /**
     * Builds a field from its JSON shape. Depth counts nesting levels, starting at 1.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data, int $depth = 1, int $maxDepth = PHP_INT_MAX): self
    {
        $name = self::string($data, 'name');
        $type = FieldType::tryFrom(self::string($data, 'type'));

        if ($type === null) {
            throw new InvalidFieldDefinition(__('The field ":name" has an unknown type.', ['name' => $name]));
        }

        $fields = [];

        if ($type->hasSubFields()) {
            if ($depth >= $maxDepth) {
                throw new InvalidFieldDefinition(__('The field ":name" is nested too deep.', ['name' => $name]));
            }

            foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $child) {
                if (! is_array($child)) {
                    throw new InvalidFieldDefinition(__('The field ":name" has a nested field that is not an object.', ['name' => $name]));
                }

                $fields[] = self::fromArray($child, $depth + 1, $maxDepth);
            }
        }

        return new self(
            name: $name,
            label: self::string($data, 'label'),
            type: $type,
            instructions: self::string($data, 'instructions'),
            required: (bool) ($data['required'] ?? false),
            default: self::cleanDefault($data['default'] ?? null),
            placeholder: self::string($data, 'placeholder'),
            options: $type->hasOptions() ? self::options($data['options'] ?? []) : [],
            min: self::number($data['min'] ?? null),
            max: self::number($data['max'] ?? null),
            step: self::number($data['step'] ?? null),
            maxLength: self::positiveInt($data['max_length'] ?? null),
            multiple: $type->isRelation() && (bool) ($data['multiple'] ?? false),
            contentTypes: self::strings($data['content_types'] ?? []),
            taxonomy: ($taxonomy = self::string($data, 'taxonomy')) === '' ? null : $taxonomy,
            roles: self::strings($data['roles'] ?? []),
            minRows: self::positiveInt($data['min_rows'] ?? null),
            maxRows: self::positiveInt($data['max_rows'] ?? null),
            condition: is_array($data['condition'] ?? null) && ($data['condition']['field'] ?? '') !== '' ? Condition::fromArray($data['condition']) : null,
            fields: $fields,
        );
    }

    // The value used for a new entry, typed for this field.
    public function defaultValue(): mixed
    {
        if ($this->default !== null) {
            return $this->default;
        }

        return match (true) {
            $this->type === FieldType::Toggle => false,
            $this->type->picksMany(), $this->type === FieldType::Gallery, $this->type === FieldType::Repeater => [],
            $this->type->isRelation() && $this->multiple => [],
            default => null,
        };
    }

    // Nested field with this name, if any.
    public function child(string $name): ?self
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function optionValues(): array
    {
        return array_map(fn (array $option): string => $option['value'], $this->options);
    }

    // Number of fields, counting this one and every nested field.
    public function count(): int
    {
        return 1 + array_sum(array_map(fn (self $field): int => $field->count(), $this->fields));
    }

    /**
     * The JSON shape, without empty settings.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'instructions' => $this->instructions,
            'required' => $this->required,
            'default' => $this->default,
            'placeholder' => $this->placeholder,
            'options' => $this->options,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'max_length' => $this->maxLength,
            'multiple' => $this->multiple,
            'content_types' => $this->contentTypes,
            'taxonomy' => $this->taxonomy,
            'roles' => $this->roles,
            'min_rows' => $this->minRows,
            'max_rows' => $this->maxRows,
            'condition' => $this->condition?->toArray(),
            'fields' => array_map(fn (self $field): array => $field->toArray(), $this->fields),
        ], fn (mixed $value, string $key): bool => in_array($key, ['name', 'label', 'type', 'required'], true) || ($value !== null && $value !== '' && $value !== [] && $value !== false), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Field names must be unique on one level.
     *
     * @param  list<Field>  $fields
     */
    public static function ensureUniqueNames(array $fields, string $owner): void
    {
        $seen = [];

        foreach ($fields as $field) {
            if (isset($seen[$field->name])) {
                throw new InvalidFieldDefinition(__('The name ":name" is used twice in ":owner".', ['name' => $field->name, 'owner' => $owner]));
            }

            $seen[$field->name] = true;
        }

        foreach ($fields as $field) {
            if ($field->condition !== null && ($field->condition->field === $field->name || ! isset($seen[$field->condition->field]))) {
                throw new InvalidFieldDefinition(__('The condition of ":name" must watch another field of the same level.', ['name' => $field->name]));
            }
        }
    }

    /** @param array<mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function number(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    // Defaults are scalars or lists of scalars, never objects.
    private static function cleanDefault(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter($value, is_scalar(...)));
        }

        return is_scalar($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), fn (string $item): bool => $item !== '')));
    }

    /**
     * Options as a list of {value, label}. Accepts that list, a value => label map or a list of strings.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function options(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $options = [];

        foreach ($value as $key => $option) {
            if (is_array($option)) {
                $optionValue = is_scalar($option['value'] ?? null) ? trim((string) $option['value']) : '';
                $label = is_scalar($option['label'] ?? null) ? trim((string) $option['label']) : '';
            } elseif (is_scalar($option)) {
                $optionValue = is_string($key) ? $key : trim((string) $option);
                $label = trim((string) $option);
            } else {
                continue;
            }

            if ($optionValue === '' || isset($options[$optionValue])) {
                continue;
            }

            $options[$optionValue] = ['value' => $optionValue, 'label' => $label === '' ? $optionValue : $label];
        }

        return array_values($options);
    }
}

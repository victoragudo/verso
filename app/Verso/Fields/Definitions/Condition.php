<?php

namespace App\Verso\Fields\Definitions;

use App\Verso\Fields\Exceptions\InvalidFieldDefinition;

// Shows a field only when another field of the same level equals (or not) a value.
final readonly class Condition
{
    public const array OPERATORS = ['==', '!='];

    public function __construct(
        public string $field,
        public string $operator,
        public string|int|float|bool|null $value,
    ) {
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidFieldDefinition(__('The condition operator ":operator" is not valid.', ['operator' => $operator]));
        }
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $value = $data['value'] ?? null;

        return new self(
            field: is_string($data['field'] ?? null) ? $data['field'] : '',
            operator: is_string($data['operator'] ?? null) ? $data['operator'] : '==',
            value: is_scalar($value) ? $value : null,
        );
    }

    // True when the field should show, given the value of the field it watches.
    public function passes(mixed $watched): bool
    {
        $expected = self::normalize($this->value);
        $equal = is_array($watched)
            ? in_array($expected, array_map(self::normalize(...), $watched), true) || ($expected === '' && $watched === [])
            : self::normalize($watched) === $expected;

        return $this->operator === '==' ? $equal : ! $equal;
    }

    // Values are compared as text, so "1", 1 and true are the same.
    public static function normalize(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }

    /** @return array{field: string, operator: string, value: string|int|float|bool|null} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'operator' => $this->operator, 'value' => $this->value];
    }
}

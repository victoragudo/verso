<?php

namespace App\Verso\Fields\Definitions;

use App\Verso\Fields\Enums\LocationParam;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;

// One test of a location: "content type == post" or "user role != author".
final readonly class LocationRule
{
    public function __construct(
        public LocationParam $param,
        public string $operator,
        public string $value,
    ) {
        if (! in_array($operator, Condition::OPERATORS, true)) {
            throw new InvalidFieldDefinition(__('The location operator ":operator" is not valid.', ['operator' => $operator]));
        }

        if ($value === '') {
            throw new InvalidFieldDefinition(__('Every location rule needs a value.'));
        }
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $param = LocationParam::tryFrom(is_string($data['param'] ?? null) ? $data['param'] : '');

        if ($param === null) {
            throw new InvalidFieldDefinition(__('A location rule has an unknown parameter.'));
        }

        return new self(
            param: $param,
            operator: is_string($data['operator'] ?? null) ? $data['operator'] : '==',
            value: is_scalar($data['value'] ?? null) ? trim((string) $data['value']) : '',
        );
    }

    // True when the rule holds for this context. Null means the context does not know this value.
    public function matches(LocationContext $context): ?bool
    {
        $result = match ($this->param) {
            LocationParam::ContentType => $context->contentType === null ? null : $context->contentType === $this->value,
            LocationParam::PageTemplate => $context->template === false ? null : ($context->template ?? '') === $this->value,
            LocationParam::TaxonomyTerm => $context->termIds === null ? null : in_array((int) $this->value, $context->termIds, true),
            LocationParam::UserRole => $context->role === null ? null : ($context->role === $this->value || in_array($this->value, $context->roles, true)),
        };

        if ($result === null) {
            return null;
        }

        return $this->operator === '==' ? $result : ! $result;
    }

    /** @return array{param: string, operator: string, value: string} */
    public function toArray(): array
    {
        return ['param' => $this->param->value, 'operator' => $this->operator, 'value' => $this->value];
    }
}

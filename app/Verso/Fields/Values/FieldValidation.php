<?php

namespace App\Verso\Fields\Values;

// The result of checking field values: clean values and error messages by input path.
final readonly class FieldValidation
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}

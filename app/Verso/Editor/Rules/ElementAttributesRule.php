<?php

namespace App\Verso\Editor\Rules;

use App\Verso\Editor\Support\ElementPolicy;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

// Checks the attributes of an element block against the element policy, knowing the chosen tag.
class ElementAttributesRule implements DataAwareRule, ValidationRule
{
    /** @var array<mixed> */
    protected array $data = [];

    public function __construct(protected ElementPolicy $policy) {}

    /** @param array<mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tag = is_string($this->data['tag'] ?? null) ? $this->data['tag'] : 'div';
        $error = $this->policy->attributeError($tag, $value);

        if ($error !== null) {
            $fail($error);
        }
    }
}

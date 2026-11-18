<?php

namespace App\Verso\Access\Rules;

use App\Models\User;
use App\Verso\Access\Support\EscalationGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// The role exists and the current user has every capability of it, so giving it raises nobody above them.
class AssignableRole implements ValidationRule
{
    public function __construct(protected mixed $actor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->actor instanceof User || ! app(EscalationGuard::class)->canAssignRole($this->actor, $value)) {
            $fail(__('You cannot give this role, because it has capabilities you do not have.'));
        }
    }
}

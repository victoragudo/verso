<?php

namespace App\Verso\Editor\Rules;

use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\I18n\Translate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Fails when a URL uses a scheme that is not allowed, like javascript: or data:.
class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! app(UrlSanitizer::class)->isSafe($value)) {
            $fail(Translate::text('The :attribute must be a safe web address.'));
        }
    }
}

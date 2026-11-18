<?php

namespace App\Verso\Seo\Http\Requests;

use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Validates the alt texts saved from the bulk "Add alt text" screen.
class AltTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageSeo->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('verso.seo.alt_text.per_page'))],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:media,id'],
            'items.*.alt' => ['present', 'nullable', 'string', 'max:'.max(1, (int) config('verso.seo.alt_text.max_length'))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['items.*.alt' => __('alt text')];
    }
}

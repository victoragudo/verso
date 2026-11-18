<?php

namespace App\Verso\Easy\Http\Requests;

use App\Verso\Core\Enums\Capability;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Checks "Your site details": contact details, social profiles, tagline and search engine description.
class SiteDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageSettings->value) ?? false;
    }

    // Empty lines of the social profiles box are dropped before the checks.
    protected function prepareForValidation(): void
    {
        $social = $this->input('social');

        if (is_array($social)) {
            $this->merge(['social' => array_values(array_filter(array_map(
                fn (mixed $link): string => is_string($link) ? trim($link) : '',
                $social,
            ), fn (string $link): bool => $link !== ''))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:'.(int) config('verso.easy.details.phone_max')],
            'address' => ['nullable', 'string', 'max:'.(int) config('verso.easy.details.address_max')],
            'hours' => ['nullable', 'string', 'max:'.(int) config('verso.easy.details.hours_max')],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:'.(int) config('verso.seo.description_max_length')],
            'social' => ['nullable', 'array', 'max:'.(int) config('verso.seo.same_as_max')],
            'social.*' => ['string', 'url:https,http', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.email' => __('This does not look like an email address. Check it for typos.'),
            'social.*.url' => __('Each line needs a full address that starts with https://, like https://instagram.com/yourname.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'phone' => 'phone number',
            'hours' => 'opening hours',
            'description' => 'description',
            'social' => 'social profiles',
        ];
    }
}

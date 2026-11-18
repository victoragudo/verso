<?php

namespace App\Verso\I18n\Http\Requests;

use App\Verso\I18n\Locales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// The admin language picked in the user menu, the profile page, the login screen or the installer.
class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Empty means "use the site language".
            'locale' => ['nullable', 'string', Rule::in(app(Locales::class)->codes())],
        ];
    }

    public function locale(): ?string
    {
        $locale = $this->validated('locale');

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}

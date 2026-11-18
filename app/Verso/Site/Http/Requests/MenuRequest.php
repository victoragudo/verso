<?php

namespace App\Verso\Site\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Menus\MenuLocations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the name and location of a menu.
class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageMenus->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', Rule::in(array_keys(app(MenuLocations::class)->all()))],
        ];
    }

    public function location(): ?string
    {
        return $this->filled('location') ? (string) $this->validated('location') : null;
    }
}

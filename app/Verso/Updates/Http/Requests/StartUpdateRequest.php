<?php

namespace App\Verso\Updates\Http\Requests;

use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Validates the "Update now" form: the version the admin saw and the consent to skip the database backup.
class StartUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageUpdates->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:64'],
            'allow_without_database_backup' => ['sometimes', 'boolean'],
        ];
    }
}

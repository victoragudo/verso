<?php

namespace App\Verso\Updates\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Updates\Enums\AutomaticUpdates;
use App\Verso\Updates\Enums\UpdateChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the update settings form.
class UpdateSettingsRequest extends FormRequest
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
            'channel' => ['required', Rule::enum(UpdateChannel::class)],
            'automatic' => ['required', Rule::enum(AutomaticUpdates::class)],
        ];
    }
}

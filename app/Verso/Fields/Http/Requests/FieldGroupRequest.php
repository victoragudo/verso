<?php

namespace App\Verso\Fields\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Enums\FieldPosition;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;
use App\Verso\Fields\Models\FieldGroupRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validates the field group builder form.
class FieldGroupRequest extends FormRequest
{
    protected ?FieldGroup $definition = null;

    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageFields->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'key' => [
                'required', 'string', 'max:'.FieldGroup::KEY_MAX_LENGTH, 'regex:'.FieldGroup::KEY_PATTERN,
                Rule::unique(FieldGroupRecord::class, 'key')->ignore($this->record()?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'position' => ['required', Rule::enum(FieldPosition::class)],
            'order' => ['nullable', 'integer'],
            'active' => ['boolean'],
            'location' => ['required', 'array', 'min:1'],
            'fields' => ['required', 'array', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.regex' => __('The key must start with a letter and use only a to z, 0 to 9, - and _.'),
            'location.required' => __('Add at least one location rule.'),
            'fields.required' => __('Add at least one field.'),
        ];
    }

    // The fields and rules are checked by building the definition.
    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $this->definition = FieldGroup::fromArray($this->all(), FieldGroup::SOURCE_DATABASE);
                } catch (InvalidFieldDefinition $exception) {
                    $validator->errors()->add('fields', $exception->getMessage());
                }
            },
        ];
    }

    public function definition(): FieldGroup
    {
        return $this->definition ?? FieldGroup::fromArray($this->all(), FieldGroup::SOURCE_DATABASE);
    }

    public function record(): ?FieldGroupRecord
    {
        $record = $this->route('fieldGroup');

        return $record instanceof FieldGroupRecord ? $record : null;
    }
}

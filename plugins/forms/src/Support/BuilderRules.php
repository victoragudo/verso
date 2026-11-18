<?php

namespace Verso\Plugins\Forms\Support;

use App\Verso\Editor\Rules\SafeUrl;
use Closure;
use Illuminate\Validation\Rule;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Models\Form;

// Validation rules for the data the form builder saves.
class BuilderRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(?Form $form = null): array
    {
        $maxFields = max(1, (int) config('forms.max_fields'));
        $maxOptions = max(1, (int) config('forms.max_options'));

        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Form::class, 'slug')->ignore($form?->id)],
            'fields' => ['required', 'array', 'min:1', 'max:'.$maxFields, $this->uniqueNames()],
            'fields.*.id' => ['required', 'string', 'max:40'],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,39}$/'],
            'fields.*.label' => ['required', 'string', 'max:300'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'fields.*.help' => ['nullable', 'string', 'max:300'],
            'fields.*.required' => ['boolean'],
            'fields.*.width' => ['nullable', Rule::in(['full', 'half'])],
            'fields.*.options' => ['nullable', 'array', 'max:'.$maxOptions, 'required_if:fields.*.type,select,radio,checkboxes'],
            'fields.*.options.*' => ['string', 'max:120', 'distinct'],
            'fields.*.min_length' => ['nullable', 'integer', 'min:0', 'max:'.(int) config('forms.textarea_max')],
            'fields.*.max_length' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('forms.textarea_max')],
            'fields.*.accept' => ['nullable', 'array'],
            'fields.*.accept.*' => ['string', Rule::in((array) config('forms.upload_types'))],
            'fields.*.max_kb' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('forms.upload_max_kb')],
            'fields.*.value' => ['nullable', 'string', 'max:255'],
            'settings' => ['required', 'array'],
            'settings.submit_label' => ['required', 'string', 'max:40'],
            'settings.success_message' => ['required', 'string', 'max:500'],
            'settings.redirect_url' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'settings.notify_emails' => ['nullable', 'array', 'max:10'],
            'settings.notify_emails.*' => ['email:rfc', 'max:190'],
            'settings.notify_subject' => ['required', 'string', 'max:190'],
            'settings.notify_body' => ['required', 'string', 'max:5000'],
            'settings.confirmation_enabled' => ['boolean'],
            'settings.confirmation_subject' => ['required_if:settings.confirmation_enabled,true', 'nullable', 'string', 'max:190'],
            'settings.confirmation_body' => ['required_if:settings.confirmation_enabled,true', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.required' => __('forms::Add at least one field.'),
            'fields.*.name.regex' => __('forms::Use lower case letters, numbers and _ only, starting with a letter.'),
            'fields.*.options.required_if' => __('forms::Add at least one option.'),
            'slug.regex' => __('forms::Use lower case letters, numbers and dashes only.'),
        ];
    }

    // Two fields with the same name would overwrite each other.
    protected function uniqueNames(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $names = array_map(fn ($field): string => is_array($field) ? (string) ($field['name'] ?? '') : '', is_array($value) ? $value : []);

            if (count($names) !== count(array_unique($names))) {
                $fail(__('forms::Each field needs its own name.'));
            }
        };
    }
}

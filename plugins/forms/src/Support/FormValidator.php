<?php

namespace Verso\Plugins\Forms\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Verso\Plugins\Forms\Enums\FieldType;
use Verso\Plugins\Forms\Fields\FormField;
use Verso\Plugins\Forms\Models\Form;

// Checks what a visitor sent against the fields of a form.
class FormValidator
{
    // Error bag name of a form, so two forms on one page keep their own errors.
    public static function bag(Form $form): string
    {
        return 'form_'.$form->slug;
    }

    /**
     * Valid values keyed by field name, plus the uploaded files. Throws a ValidationException.
     *
     * @param  array<string, mixed>  $input
     * @return array{values: array<string, mixed>, files: array<string, UploadedFile>}
     */
    public function validate(Form $form, array $input): array
    {
        $fields = array_filter($form->formFields(), fn (FormField $field): bool => $field->takesInput());
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rules['fields.'.$field->name] = $field->rules();
            $attributes['fields.'.$field->name] = $field->label;

            if (($itemRules = $field->itemRules()) !== null) {
                $rules['fields.'.$field->name.'.*'] = $itemRules;
                $attributes['fields.'.$field->name.'.*'] = $field->label;
            }
        }

        $messages = ['accepted' => __('forms::Please tick the box: :attribute'), 'fields.*.required' => __('forms::Please fill in :attribute.')];

        $valid = Validator::make($input, $rules, $messages, $attributes)->validateWithBag(self::bag($form));
        $sent = is_array($valid['fields'] ?? null) ? $valid['fields'] : [];

        $values = [];
        $files = [];

        foreach ($form->formFields() as $field) {
            $value = $sent[$field->name] ?? null;

            match ($field->type) {
                FieldType::Hidden => $values[$field->name] = $field->value,
                FieldType::File => $value instanceof UploadedFile ? $files[$field->name] = $value : null,
                FieldType::Consent => $values[$field->name] = filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'on',
                FieldType::Checkboxes => $values[$field->name] = array_values(array_map('strval', is_array($value) ? $value : [])),
                default => $values[$field->name] = is_string($value) ? trim($value) : null,
            };
        }

        return ['values' => $values, 'files' => $files];
    }
}

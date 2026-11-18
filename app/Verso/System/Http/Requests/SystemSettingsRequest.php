<?php

namespace App\Verso\System\Http\Requests;

use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Checks System settings values against the registry: known key, capability, not set by env, type and range.
class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageSystem->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['values' => ['required', 'array', 'min:1']];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $registry = app(ConfigRegistry::class);
            $overlay = app(ConfigOverlay::class);
            $values = $this->input('values');

            foreach (is_array($values) ? $values : [] as $key => $value) {
                $key = (string) $key;
                $parameter = $registry->find($key);
                $field = 'values.'.$key;

                $problem = match (true) {
                    $parameter === null => Translate::text('This setting does not exist.'),
                    ! $this->user()?->can($parameter->capability->value) => Translate::text('You may not change this setting.'),
                    $overlay->isSetByEnvironment($parameter) => Translate::text('The server environment sets this value, so it cannot be changed here.'),
                    default => $parameter->problem($value),
                };

                if ($problem !== null) {
                    $validator->errors()->add($field, $problem);
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return array_map(fn (mixed $value): mixed => $value, (array) $this->input('values'));
    }
}

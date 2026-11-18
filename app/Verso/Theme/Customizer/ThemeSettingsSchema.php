<?php

namespace App\Verso\Theme\Customizer;

use InvalidArgumentException;

// The customizer fields a theme declares in theme.json, checked against the safe rules.
final readonly class ThemeSettingsSchema
{
    // Most fields one theme may declare.
    public const MAX_FIELDS = 40;

    /**
     * @param  array<string, ThemeSettingField>  $fields
     * @param  list<string>  $errors  problems with fields that were left out
     */
    public function __construct(
        public array $fields = [],
        public array $errors = [],
    ) {}

    /**
     * Read the settings object of theme.json. Broken fields are left out and listed in errors.
     *
     * @param  array<mixed>  $settings
     * @param  list<string>  $fonts
     */
    public static function fromArray(array $settings, array $fonts, int $textMax): self
    {
        $fields = [];
        $errors = [];

        if (count($settings) > self::MAX_FIELDS) {
            return new self([], [__('A theme can declare at most :count settings.', ['count' => self::MAX_FIELDS])]);
        }

        foreach ($settings as $key => $definition) {
            if (! is_array($definition)) {
                $errors[] = __('The setting ":key" must be an object.', ['key' => $key]);

                continue;
            }

            try {
                $fields[(string) $key] = ThemeSettingField::fromArray((string) $key, $definition, $fonts, $textMax);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return new self($fields, $errors);
    }

    /**
     * Build the schema with the safe font list and text limit from config.
     *
     * @param  array<mixed>  $settings
     */
    public static function fromConfig(array $settings): self
    {
        return self::fromArray(
            $settings,
            array_map(strval(...), array_keys((array) config('verso.themes.fonts'))),
            max(1, (int) config('verso.themes.setting_text_max')),
        );
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /**
     * Validation rules for the customizer form, under the "values" key.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = ['values' => ['array']];

        foreach ($this->fields as $key => $field) {
            $rules["values.{$key}"] = $field->rules();
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (ThemeSettingField $field): array => $field->toArray(), $this->fields));
    }
}

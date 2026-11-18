<?php

namespace App\Verso\Install\Wizard;

// Fields that hold secrets. They are never sent back to the browser; an empty field can keep the saved value.
final class SecretFields
{
    /** @var array<string, list<string>> */
    public const BY_STEP = [
        'database' => ['password'],
        'site' => ['admin_password'],
        'services' => ['mail_password', 'redis_password', 's3_secret'],
    ];

    /**
     * @return list<string>
     */
    public static function for(WizardStep $step): array
    {
        return self::BY_STEP[$step->value] ?? [];
    }

    /**
     * The values without secrets, plus the list of secrets that are saved.
     *
     * @param  array<string, mixed>  $values
     * @return array{values: array<string, mixed>, saved: list<string>}
     */
    public static function strip(WizardStep $step, array $values): array
    {
        $saved = [];

        foreach (self::for($step) as $field) {
            if (($values[$field] ?? '') !== '') {
                $saved[] = $field;
            }

            unset($values[$field], $values[$field.'_confirmation']);
        }

        unset($values['has_password']);

        return ['values' => $values, 'saved' => $saved];
    }

    /**
     * Put saved secrets back where the user left the field empty and asked to keep it.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function restore(WizardStep $step, array $input, array $stored): array
    {
        $keep = array_map(strval(...), (array) ($input['keep'] ?? []));

        foreach (self::for($step) as $field) {
            if (($input[$field] ?? '') === '' && in_array($field, $keep, true) && ($stored[$field] ?? '') !== '') {
                $input[$field] = $stored[$field];
                $input[$field.'_confirmation'] = $stored[$field];
            }
        }

        unset($input['keep']);

        return $input;
    }
}

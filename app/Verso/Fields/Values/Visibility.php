<?php

namespace App\Verso\Fields\Values;

use App\Verso\Fields\Definitions\Field;

// Decides which fields of one level show, following their conditions (a hidden field hides its watchers).
final class Visibility
{
    /**
     * Names of the visible fields of one level.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @return array<string, true>
     */
    public static function visible(array $fields, array $values): array
    {
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name] = $field;
        }

        $result = [];
        foreach ($fields as $field) {
            if (self::shows($field, $byName, $values, [])) {
                $result[$field->name] = true;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, Field>  $byName
     * @param  array<array-key, mixed>  $values
     * @param  array<string, true>  $seen
     */
    private static function shows(Field $field, array $byName, array $values, array $seen): bool
    {
        $condition = $field->condition;

        if ($condition === null) {
            return true;
        }

        $watched = $byName[$condition->field] ?? null;

        if ($watched === null || isset($seen[$field->name])) {
            return false;
        }

        $seen[$field->name] = true;

        if (! self::shows($watched, $byName, $values, $seen)) {
            return false;
        }

        return $condition->passes($values[$condition->field] ?? $watched->defaultValue());
    }
}

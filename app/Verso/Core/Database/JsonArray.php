<?php

namespace App\Verso\Core\Database;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;

/**
 * Works like the "array" cast, but the same data in a different key order is not a change.
 * MySQL sorts the keys of JSON columns, so a plain compare would see every save as an edit.
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, mixed>
 */
class JsonArray implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $decoded = self::decode($value);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = Json::encode($value);

        return is_string($encoded) ? $encoded : null;
    }

    // True when both raw values hold the same data, whatever the key order.
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::normalize(self::decode($firstValue)) === self::normalize(self::decode($secondValue));
    }

    // Decode a raw JSON value from the database or from set().
    protected static function decode(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? Json::decode($value) : $value;
    }

    // Sort the keys of every object, but keep lists in their order.
    protected static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::normalize(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}

<?php

namespace Tests\Support;

use App\Verso\Plugins\Slots\AdminSlot;
use Illuminate\Testing\TestResponse;

// Reads the admin slot items an Inertia response shares as the versoSlots prop.
class SharedSlots
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(TestResponse $response, AdminSlot $slot): array
    {
        $slots = (array) $response->inertiaProps('versoSlots');

        return array_values((array) ($slots[$slot->value] ?? []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function item(TestResponse $response, AdminSlot $slot, string $key): ?array
    {
        foreach (self::items($response, $slot) as $item) {
            if (($item['key'] ?? null) === $key) {
                return $item;
            }
        }

        return null;
    }
}

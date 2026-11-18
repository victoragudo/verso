<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Fields\Theme\EntryFieldValues;
use App\Verso\Fields\Theme\FieldPrinter;

if (! function_exists('field')) {
    // A typed custom field value of an entry. Without an entry it reads the entry of the page.
    function field(string $name, ?Entry $entry = null): mixed
    {
        $entry = app(FieldPrinter::class)->entry(null, $entry);

        return $entry === null ? null : app(EntryFieldValues::class)->get($entry, $name);
    }
}

if (! function_exists('fields')) {
    /**
     * Every typed custom field value of an entry, by name.
     *
     * @return array<string, mixed>
     */
    function fields(?Entry $entry = null): array
    {
        $entry = app(FieldPrinter::class)->entry(null, $entry);

        return $entry === null ? [] : app(EntryFieldValues::class)->all($entry);
    }
}

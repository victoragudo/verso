<?php

namespace App\Verso\Fields\Theme;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Definitions\LocationContext;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Values\FieldFormatter;
use App\Verso\Fields\Values\FieldJson;
use WeakMap;

// Reads the custom fields of an entry for themes and the API, typed by their definitions.
class EntryFieldValues
{
    /** @var WeakMap<Entry, array<string, Field>> */
    protected WeakMap $definitions;

    public function __construct(
        protected FieldGroupRegistry $groups,
        protected FieldFormatter $formatter,
        protected FieldJson $json,
    ) {
        $this->definitions = new WeakMap;
    }

    /**
     * Top level fields that apply to this entry, by name. User role rules are ignored here.
     *
     * @return array<string, Field>
     */
    public function definitions(Entry $entry): array
    {
        if (isset($this->definitions[$entry])) {
            return $this->definitions[$entry];
        }

        $meta = $entry->meta ?? [];

        return $this->definitions[$entry] = $this->groups->fields(new LocationContext(
            contentType: $entry->type,
            template: is_string($meta['template'] ?? null) ? $meta['template'] : '',
            termIds: $entry->exists ? array_values($entry->terms->map(fn (Term $term): int => $term->id)->all()) : [],
            role: null,
        ));
    }

    public function definition(Entry $entry, string $name): ?Field
    {
        return $this->definitions($entry)[$name] ?? null;
    }

    // One typed value. A dotted name like "address.city" reads inside groups. Unknown names give null.
    public function get(Entry $entry, string $name): mixed
    {
        $segments = explode('.', $name);
        $field = $this->definition($entry, (string) array_shift($segments));

        if ($field === null) {
            return null;
        }

        $value = $this->formatter->format($field, $this->stored($entry)[$field->name] ?? $field->defaultValue());

        foreach ($segments as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? null) : null;
        }

        return $value;
    }

    /**
     * Every typed value of the entry.
     *
     * @return array<string, mixed>
     */
    public function all(Entry $entry): array
    {
        return $this->formatter->level(array_values($this->definitions($entry)), $this->stored($entry));
    }

    /**
     * Plain JSON values for the REST API.
     *
     * @return array<string, mixed>
     */
    public function json(Entry $entry): array
    {
        return $this->json->level(array_values($this->definitions($entry)), $this->all($entry));
    }

    /** @return array<array-key, mixed> */
    protected function stored(Entry $entry): array
    {
        $fields = ($entry->meta ?? [])['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }
}

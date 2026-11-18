<?php

namespace App\Verso\Fields\Values;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Media\Models\Media;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

// Turns typed field values into plain JSON for the REST API.
class FieldJson
{
    public function __construct(protected SiteTime $time) {}

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $typed
     * @return array<string, mixed>
     */
    public function level(array $fields, array $typed): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field->name] = $this->value($field, $typed[$field->name] ?? null);
        }

        return $result;
    }

    public function value(Field $field, mixed $value): mixed
    {
        return match (true) {
            $value instanceof HtmlString => $value->toHtml(),
            $value instanceof CarbonInterface => $field->type === FieldType::Date ? $value->format('Y-m-d') : $this->time->iso($value),
            $value instanceof FieldImage => $value->jsonSerialize(),
            $value instanceof Media => $this->file($value),
            $value instanceof Entry => $this->entry($value),
            $value instanceof Term => $this->term($value),
            $value instanceof User => ['id' => $value->id, 'name' => $value->name],
            $value instanceof Collection => $value->map(fn (mixed $item): mixed => $this->value($field, $item))->values()->all(),
            $field->type === FieldType::Repeater && is_array($value) => array_map(
                fn (mixed $row): array => $this->level($field->fields, is_array($row) ? $row : []),
                $value,
            ),
            $field->type === FieldType::Group && is_array($value) => $this->level($field->fields, $value),
            is_array($value) => array_map(fn (mixed $item): mixed => $this->value($field, $item), $value),
            default => $value,
        };
    }

    /** @return array{id: int, url: string, filename: string, mime_type: string, size: int} */
    protected function file(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url(),
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }

    /** @return array{id: int, type: string, title: string, slug: string, url: string} */
    protected function entry(Entry $entry): array
    {
        return ['id' => $entry->id, 'type' => $entry->type, 'title' => $entry->title, 'slug' => $entry->slug, 'url' => $entry->url()];
    }

    /** @return array{id: int, taxonomy: string, name: string, slug: string, url: string} */
    protected function term(Term $term): array
    {
        return ['id' => $term->id, 'taxonomy' => $term->taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'url' => $term->url()];
    }
}

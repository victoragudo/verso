<?php

namespace App\Verso\Fields\Values;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Media\ImageUrl;
use App\Verso\Media\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

// Turns stored field values into typed values for themes: images, models, dates and HTML.
class FieldFormatter
{
    public function __construct(
        protected ImageUrl $images,
        protected SiteTime $time,
        protected string $imageSizes,
    ) {}

    /**
     * Typed values of one level, with defaults for missing fields.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public function level(array $fields, array $values): array
    {
        $visible = Visibility::visible($fields, $values);
        $result = [];

        foreach ($fields as $field) {
            $result[$field->name] = isset($visible[$field->name])
                ? $this->format($field, $values[$field->name] ?? $field->defaultValue())
                : $this->format($field, null);
        }

        return $result;
    }

    public function format(Field $field, mixed $value): mixed
    {
        return match ($field->type) {
            FieldType::Text, FieldType::Textarea, FieldType::Email, FieldType::Url,
            FieldType::Select, FieldType::Radio, FieldType::Color => is_scalar($value) && $value !== '' ? (string) $value : null,
            FieldType::RichText => is_string($value) && $value !== '' ? new HtmlString($value) : null,
            FieldType::Number => is_int($value) || is_float($value) ? $value : (is_numeric($value) ? $value + 0 : null),
            FieldType::MultiSelect, FieldType::Checkbox => is_array($value) ? array_values(array_map(strval(...), array_filter($value, is_scalar(...)))) : [],
            FieldType::Toggle => (bool) $value,
            FieldType::Date => $this->date($value, '!Y-m-d'),
            FieldType::DateTime => $this->date($value, 'Y-m-d\TH:i'),
            FieldType::Image => $this->image(FieldValidator::id($value)),
            FieldType::File => ($id = FieldValidator::id($value)) === null ? null : Media::query()->find($id),
            FieldType::Gallery => $this->gallery($value),
            FieldType::EntryRelation => $this->related($field, $value, Entry::query()->published()),
            FieldType::TermRelation => $this->related($field, $value, Term::query()->where('taxonomy', (string) $field->taxonomy)),
            FieldType::UserRelation => $this->related($field, $value, User::query()),
            FieldType::Repeater => is_array($value) ? array_values(array_map(
                fn (array $row): array => $this->level($field->fields, $row),
                array_filter($value, is_array(...)),
            )) : [],
            FieldType::Group => $this->level($field->fields, is_array($value) ? $value : []),
        };
    }

    // The label of a picked option, for printing.
    public function optionLabel(Field $field, string $value): string
    {
        foreach ($field->options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }

    // Dates are wall times in the site time zone.
    protected function date(mixed $value, string $format): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat($format, $value, $this->time->timezone());

        return $date instanceof CarbonImmutable ? $date : null;
    }

    public function image(?int $id): ?FieldImage
    {
        $media = $id === null ? null : Media::query()->find($id);

        return $media === null ? null : $this->imageFor($media);
    }

    protected function imageFor(Media $media): ?FieldImage
    {
        if (! $media->isImage()) {
            return null;
        }

        $attributes = $this->images->attributes($media, $this->imageSizes);

        return new FieldImage(
            media: $media,
            src: $attributes['src'],
            srcset: $attributes['srcset'],
            sizes: $attributes['sizes'],
            alt: $attributes['alt'],
            width: $attributes['width'],
            height: $attributes['height'],
        );
    }

    /** @return list<FieldImage> */
    protected function gallery(mixed $value): array
    {
        $ids = is_array($value) ? array_values(array_filter(array_map(FieldValidator::id(...), $value))) : [];

        if ($ids === []) {
            return [];
        }

        $media = Media::query()->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(
            fn (int $id): ?FieldImage => $media->has($id) ? $this->imageFor($media->get($id)) : null,
            $ids,
        )));
    }

    /**
     * One model, or a collection in the saved order. Missing or hidden records are left out.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|Collection<int, TModel>|null
     */
    protected function related(Field $field, mixed $value, Builder $query): Model|Collection|null
    {
        $ids = is_array($value)
            ? array_values(array_filter(array_map(FieldValidator::id(...), $value)))
            : (FieldValidator::id($value) === null ? [] : [(int) FieldValidator::id($value)]);

        if ($ids === []) {
            return $field->multiple ? $query->getModel()->newCollection() : null;
        }

        $found = $query->whereKey($ids)->get()->keyBy(fn (Model $model): int => (int) $model->getKey());
        $ordered = $query->getModel()->newCollection(array_values(array_filter(array_map(fn (int $id): ?Model => $found->get($id), $ids))));

        return $field->multiple ? $ordered : $ordered->first();
    }
}

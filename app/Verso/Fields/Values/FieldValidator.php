<?php

namespace App\Verso\Fields\Values;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Editor\Support\HtmlSanitizer;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use DateTimeImmutable;

// Checks submitted field values against their definitions and returns clean, typed values.
class FieldValidator
{
    // Block tags allowed in rich text fields, on top of the inline marks.
    public const array RICH_TEXT_TAGS = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote'];

    /** @var array<string, string> */
    protected array $errors = [];

    public function __construct(
        protected HtmlSanitizer $sanitizer,
        protected int $textMax,
        protected int $textareaMax,
        protected int $maxRows,
    ) {}

    /**
     * Check the values of these fields. Error keys start with the prefix, like "meta.fields".
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $input
     */
    public function validate(array $fields, array $input, string $prefix = 'meta.fields'): FieldValidation
    {
        $this->errors = [];
        $values = $this->level($fields, $input, $prefix);

        return new FieldValidation($values, $this->errors);
    }

    /**
     * Clean values of one level. Hidden fields are left out.
     *
     * @param  list<Field>  $fields
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     */
    protected function level(array $fields, array $input, string $path): array
    {
        $visible = Visibility::visible($fields, $input);
        $values = [];

        foreach ($fields as $field) {
            if (isset($visible[$field->name])) {
                $values[$field->name] = $this->value($field, $input[$field->name] ?? null, $path.'.'.$field->name);
            }
        }

        return $values;
    }

    protected function value(Field $field, mixed $value, string $path): mixed
    {
        if (is_string($value) && $field->type !== FieldType::Textarea && $field->type !== FieldType::RichText) {
            $value = trim($value);
        }

        if ($field->type === FieldType::Toggle) {
            return $this->toggle($field, $value, $path);
        }

        if (self::isEmpty($value)) {
            if ($field->required) {
                $this->fail($path, __('The :label field is required.', ['label' => $field->label]));
            }

            return $field->defaultValue() === false ? false : (is_array($field->defaultValue()) ? [] : null);
        }

        return match ($field->type) {
            FieldType::Text, FieldType::Textarea => $this->text($field, $value, $path),
            FieldType::RichText => $this->richText($field, $value, $path),
            FieldType::Email => $this->email($field, $value, $path),
            FieldType::Url => $this->url($field, $value, $path),
            FieldType::Number => $this->number($field, $value, $path),
            FieldType::Select, FieldType::Radio => $this->choice($field, $value, $path),
            FieldType::MultiSelect, FieldType::Checkbox => $this->choices($field, $value, $path),
            FieldType::Date => $this->date($field, $value, $path),
            FieldType::DateTime => $this->dateTime($field, $value, $path),
            FieldType::Color => $this->color($field, $value, $path),
            FieldType::Image, FieldType::File => $this->media($field, $value, $path),
            FieldType::Gallery => $this->gallery($field, $value, $path),
            FieldType::EntryRelation, FieldType::TermRelation, FieldType::UserRelation => $this->relation($field, $value, $path),
            FieldType::Repeater => $this->repeater($field, $value, $path),
            FieldType::Group => $this->group($field, $value, $path),
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function toggle(Field $field, mixed $value, string $path): bool
    {
        if ($value === null || $value === '') {
            return (bool) $field->defaultValue();
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($flag === null) {
            $this->fail($path, __('The :label field must be on or off.', ['label' => $field->label]));

            return false;
        }

        return $flag;
    }

    protected function text(Field $field, mixed $value, string $path): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return $this->fail($path, __('The :label field must be text.', ['label' => $field->label]));
        }

        $text = (string) $value;
        $max = $this->maxLength($field);

        if (mb_strlen($text) > $max) {
            return $this->fail($path, __('The :label field must not be longer than :max characters.', ['label' => $field->label, 'max' => $max]));
        }

        return $text;
    }

    protected function richText(Field $field, mixed $value, string $path): ?string
    {
        $html = $this->text($field, $value, $path);

        if ($html === null) {
            return null;
        }

        $clean = $this->sanitizer->clean($html, self::RICH_TEXT_TAGS);

        return trim($this->sanitizer->text($clean)) === '' ? null : $clean;
    }

    protected function email(Field $field, mixed $value, string $path): ?string
    {
        $text = $this->text($field, $value, $path);

        if ($text !== null && filter_var($text, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail($path, __('The :label field must be a valid email address.', ['label' => $field->label]));
        }

        return $text === null ? null : mb_strtolower($text);
    }

    protected function url(Field $field, mixed $value, string $path): ?string
    {
        $text = $this->text($field, $value, $path);
        $scheme = $text === null ? null : strtolower((string) parse_url($text, PHP_URL_SCHEME));

        if ($text !== null && (filter_var($text, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true))) {
            return $this->fail($path, __('The :label field must be a link that starts with http:// or https://.', ['label' => $field->label]));
        }

        return $text;
    }

    protected function number(Field $field, mixed $value, string $path): int|float|null
    {
        if (! is_numeric($value) || is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            return $this->fail($path, __('The :label field must be a number.', ['label' => $field->label]));
        }

        $number = is_string($value) && ! str_contains($value, '.') || is_int($value) ? (int) $value : (float) $value;

        if ($field->min !== null && $number < $field->min) {
            return $this->fail($path, __('The :label field must be at least :min.', ['label' => $field->label, 'min' => $field->min]));
        }

        if ($field->max !== null && $number > $field->max) {
            return $this->fail($path, __('The :label field must not be more than :max.', ['label' => $field->label, 'max' => $field->max]));
        }

        return $number;
    }

    protected function choice(Field $field, mixed $value, string $path): ?string
    {
        $choice = is_scalar($value) ? (string) $value : null;

        if ($choice === null || ! in_array($choice, $field->optionValues(), true)) {
            return $this->fail($path, __('Pick one of the options for :label.', ['label' => $field->label]));
        }

        return $choice;
    }

    /** @return list<string> */
    protected function choices(Field $field, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->fail($path, __('Pick options from the list for :label.', ['label' => $field->label]));

            return [];
        }

        $picked = array_values(array_unique(array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value)));

        if (array_diff($picked, $field->optionValues()) !== []) {
            $this->fail($path, __('Pick options from the list for :label.', ['label' => $field->label]));

            return [];
        }

        return $picked;
    }

    protected function date(Field $field, mixed $value, string $path): ?string
    {
        $text = is_string($value) ? $value : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        if ($date === false || $date->format('Y-m-d') !== $text) {
            return $this->fail($path, __('The :label field must be a date like 2026-03-14.', ['label' => $field->label]));
        }

        return $text;
    }

    // Date times are the wall time in the site time zone, saved as "2026-03-14T09:30".
    protected function dateTime(Field $field, mixed $value, string $path): ?string
    {
        $text = is_string($value) ? str_replace(' ', 'T', $value) : '';

        foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $text);

            if ($date !== false && $date->format(substr($format, 1)) === $text) {
                return $date->format('Y-m-d\TH:i');
            }
        }

        return $this->fail($path, __('The :label field must be a date and time like 2026-03-14T09:30.', ['label' => $field->label]));
    }

    protected function color(Field $field, mixed $value, string $path): ?string
    {
        if (! is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            return $this->fail($path, __('The :label field must be a color like #1d4ed8.', ['label' => $field->label]));
        }

        return strtolower($value);
    }

    protected function media(Field $field, mixed $value, string $path): ?int
    {
        $id = self::id($value);
        $query = Media::query()->whereKey($id);

        if ($field->type === FieldType::Image) {
            $query->ofType(MediaType::Image);
        }

        if ($id === null || ! $query->exists()) {
            return $this->fail($path, $field->type === FieldType::Image ? __('Pick an image from the library for :label.', ['label' => $field->label]) : __('Pick a file from the library for :label.', ['label' => $field->label]));
        }

        return $id;
    }

    /** @return list<int> */
    protected function gallery(Field $field, mixed $value, string $path): array
    {
        $ids = $this->ids($value);

        if ($ids === null) {
            $this->fail($path, __('Pick images from the library for :label.', ['label' => $field->label]));

            return [];
        }

        if (! $this->countFits($field, count($ids), $path, 'images')) {
            return [];
        }

        $found = Media::query()->whereKey($ids)->ofType(MediaType::Image)->count();

        if ($found !== count($ids)) {
            $this->fail($path, __('Pick images from the library for :label.', ['label' => $field->label]));

            return [];
        }

        return $ids;
    }

    /** @return int|list<int>|null */
    protected function relation(Field $field, mixed $value, string $path): int|array|null
    {
        $ids = $field->multiple ? $this->ids($value) : (self::id($value) === null ? null : [self::id($value)]);

        if ($ids === null || $field->multiple && ! $this->countFits($field, count($ids), $path, 'items')) {
            if ($ids === null) {
                $this->fail($path, __('Pick a valid item for :label.', ['label' => $field->label]));
            }

            return $field->multiple ? [] : null;
        }

        $query = match ($field->type) {
            FieldType::EntryRelation => Entry::query()->whereKey($ids)->when($field->contentTypes !== [], fn ($query) => $query->whereIn('type', $field->contentTypes)),
            FieldType::TermRelation => Term::query()->whereKey($ids)->where('taxonomy', (string) $field->taxonomy),
            default => User::query()->whereKey($ids)->when($field->roles !== [], fn ($query) => $query->whereIn('role', $field->roles)),
        };

        if ($query->count() !== count($ids)) {
            $this->fail($path, __('Pick a valid item for :label.', ['label' => $field->label]));

            return $field->multiple ? [] : null;
        }

        return $field->multiple ? $ids : $ids[0];
    }

    /** @return list<array<string, mixed>> */
    protected function repeater(Field $field, mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->fail($path, __('The :label field must be a list of rows.', ['label' => $field->label]));

            return [];
        }

        if (! $this->countFits($field, count($value), $path, 'rows')) {
            return [];
        }

        $rows = [];

        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                $this->fail("{$path}.{$index}", __('Every row of :label must be an object.', ['label' => $field->label]));

                continue;
            }

            $rows[] = $this->level($field->fields, $row, "{$path}.{$index}");
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    protected function group(Field $field, mixed $value, string $path): ?array
    {
        if (! is_array($value) || array_is_list($value)) {
            return $this->fail($path, __('The :label field must be an object.', ['label' => $field->label]));
        }

        return $this->level($field->fields, $value, $path);
    }

    // Checks min and max rows. The global max applies when the field sets none.
    protected function countFits(Field $field, int $count, string $path, string $noun): bool
    {
        $max = min($field->maxRows ?? $this->maxRows, $this->maxRows);

        if ($field->minRows !== null && $count < $field->minRows) {
            $this->fail($path, "{$field->label} needs at least {$field->minRows} {$noun}.");

            return false;
        }

        if ($count > $max) {
            $this->fail($path, "{$field->label} can hold at most {$max} {$noun}.");

            return false;
        }

        return true;
    }

    protected function maxLength(Field $field): int
    {
        $limit = in_array($field->type, [FieldType::Textarea, FieldType::RichText], true) ? $this->textareaMax : $this->textMax;

        return $field->maxLength === null ? $limit : min($field->maxLength, $limit);
    }

    public static function id(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** @return list<int>|null */
    protected function ids(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $ids = [];
        foreach ($value as $item) {
            $id = self::id($item);

            if ($id === null) {
                return null;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    protected function fail(string $path, string $message): null
    {
        $this->errors[$path] ??= $message;

        return null;
    }
}

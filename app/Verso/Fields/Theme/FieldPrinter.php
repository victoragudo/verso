<?php

namespace App\Verso\Fields\Theme;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Fields\Values\FieldFormatter;
use App\Verso\Media\Models\Media;
use App\Verso\Shortcodes\Enums\ShortcodePlace;
use App\Verso\Shortcodes\ShortcodeContext;
use App\Verso\Shortcodes\ShortcodeExpander;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

// Prints a field as safe HTML for the @field directive.
class FieldPrinter
{
    public function __construct(
        protected EntryFieldValues $values,
        protected CurrentEntry $current,
        protected FieldFormatter $formatter,
        protected SiteTime $time,
        protected string $dateFormat,
        protected string $dateTimeFormat,
    ) {}

    // The entry asked for, else the $entry of the view, else the entry of the page.
    public function entry(mixed $scope, ?Entry $entry = null): ?Entry
    {
        return $entry ?? ($scope instanceof Entry ? $scope : $this->current->get());
    }

    public function print(mixed $scope, string $name, ?Entry $entry = null): string
    {
        $entry = $this->entry($scope, $entry);

        if ($entry === null) {
            return '';
        }

        $field = $this->values->definition($entry, explode('.', $name)[0]);

        if ($field === null) {
            return '';
        }

        $leaf = $this->leaf($field, $name);

        if ($leaf === null) {
            return '';
        }

        $value = $this->values->get($entry, $name);

        // Text and textarea fields may hold shortcodes, when the custom_field place is turned on.
        if (is_string($value) && in_array($leaf->type, [FieldType::Text, FieldType::Textarea], true)) {
            $html = app(ShortcodeExpander::class)->expandText($value, ShortcodeContext::for(ShortcodePlace::CustomField, $entry));

            return $leaf->type === FieldType::Textarea ? nl2br($html) : $html;
        }

        return $this->html($leaf, $value);
    }

    // The nested field a dotted name points to.
    protected function leaf(Field $field, string $name): ?Field
    {
        foreach (array_slice(explode('.', $name), 1) as $segment) {
            $child = $field->child($segment);

            if ($child === null) {
                return null;
            }

            $field = $child;
        }

        return $field;
    }

    protected function html(Field $field, mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof Htmlable => $value->toHtml(),
            $value instanceof CarbonInterface => e($this->time->format($value, $field->type === FieldType::Date ? $this->dateFormat : $this->dateTimeFormat)),
            $value instanceof Entry => '<a href="'.e($value->url()).'">'.e($value->title).'</a>',
            $value instanceof Term => '<a href="'.e($value->url()).'">'.e($value->name).'</a>',
            $value instanceof User => e($value->name),
            $value instanceof Media => '<a href="'.e($value->url()).'">'.e($value->filename).'</a>',
            $value instanceof Collection => $value->map(fn (mixed $item): string => $this->html($field, $item))->implode(', '),
            is_bool($value) => e($value ? __('Yes') : __('No')),
            $field->type->hasSubFields() => '',
            is_array($value) && $field->type === FieldType::Gallery => implode('', array_map(fn (mixed $item): string => $this->html($field, $item), $value)),
            is_array($value) => e(implode(', ', array_map(fn (mixed $item): string => $this->formatter->optionLabel($field, is_scalar($item) ? (string) $item : ''), $value))),
            $field->type->hasOptions() && is_string($value) => e($this->formatter->optionLabel($field, $value)),
            is_scalar($value) => $field->type === FieldType::Textarea ? nl2br(e((string) $value)) : e((string) $value),
            default => '',
        };
    }
}

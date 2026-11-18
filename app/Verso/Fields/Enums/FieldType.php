<?php

namespace App\Verso\Fields\Enums;

// Every kind of custom field a field group can hold.
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'rich_text';
    case Number = 'number';
    case Email = 'email';
    case Url = 'url';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Toggle = 'toggle';
    case Date = 'date';
    case DateTime = 'date_time';
    case Color = 'color';
    case Image = 'image';
    case File = 'file';
    case Gallery = 'gallery';
    case EntryRelation = 'entry_relation';
    case TermRelation = 'term_relation';
    case UserRelation = 'user_relation';
    case Repeater = 'repeater';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Textarea => __('Text area'),
            self::RichText => __('Rich text'),
            self::Number => __('Number'),
            self::Email => __('Email'),
            self::Url => __('URL'),
            self::Select => __('Select list'),
            self::MultiSelect => __('Multi select'),
            self::Checkbox => __('Checkboxes'),
            self::Radio => __('Radio buttons'),
            self::Toggle => __('Toggle'),
            self::Date => __('Date'),
            self::DateTime => __('Date and time'),
            self::Color => __('Color'),
            self::Image => __('Image'),
            self::File => __('File'),
            self::Gallery => __('Gallery'),
            self::EntryRelation => __('Entry relation'),
            self::TermRelation => __('Term relation'),
            self::UserRelation => __('User relation'),
            self::Repeater => __('Repeater'),
            self::Group => __('Group'),
        };
    }

    // Types that pick from a list of options.
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect, self::Checkbox, self::Radio], true);
    }

    // Types whose value is a list of picked options.
    public function picksMany(): bool
    {
        return $this === self::MultiSelect || $this === self::Checkbox;
    }

    // Types that hold nested fields.
    public function hasSubFields(): bool
    {
        return $this === self::Repeater || $this === self::Group;
    }

    // Types that point to other records and may hold one or many of them.
    public function isRelation(): bool
    {
        return in_array($this, [self::EntryRelation, self::TermRelation, self::UserRelation], true);
    }

    // Types with a max length for their text.
    public function hasMaxLength(): bool
    {
        return in_array($this, [self::Text, self::Textarea, self::RichText, self::Email, self::Url], true);
    }

    /**
     * Shape sent to the admin builder.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}

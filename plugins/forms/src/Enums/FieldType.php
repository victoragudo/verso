<?php

namespace Verso\Plugins\Forms\Enums;

// Kinds of fields a form can hold.
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkboxes = 'checkboxes';
    case Radio = 'radio';
    case Date = 'date';
    case File = 'file';
    case Consent = 'consent';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('forms::Text'),
            self::Email => __('forms::Email'),
            self::Phone => __('forms::Phone'),
            self::Textarea => __('forms::Paragraph'),
            self::Select => __('forms::Dropdown'),
            self::Checkboxes => __('forms::Checkboxes'),
            self::Radio => __('forms::Radio buttons'),
            self::Date => __('forms::Date'),
            self::File => __('forms::File upload'),
            self::Consent => __('forms::Consent checkbox'),
            self::Hidden => __('forms::Hidden value'),
        };
    }

    // Fields that offer a list of choices.
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Checkboxes, self::Radio], true);
    }

    // Fields with a free text length limit.
    public function hasLength(): bool
    {
        return in_array($this, [self::Text, self::Textarea], true);
    }

    // Fields shown as a group of inputs, so they get a fieldset and legend.
    public function isGroup(): bool
    {
        return in_array($this, [self::Checkboxes, self::Radio], true);
    }
}

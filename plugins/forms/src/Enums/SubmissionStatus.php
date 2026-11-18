<?php

namespace Verso\Plugins\Forms\Enums;

// Where a submission is in the inbox.
enum SubmissionStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Spam = 'spam';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => __('forms::New'),
            self::Read => __('forms::Read'),
            self::Spam => __('forms::Spam'),
            self::Archived => __('forms::Archived'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}

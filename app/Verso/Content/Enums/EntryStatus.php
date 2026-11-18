<?php

namespace App\Verso\Content\Enums;

// The life cycle state of an entry.
enum EntryStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Trash = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Published => __('Published'),
            self::Trash => __('Trash'),
        };
    }

    // True for states that need the publish capability.
    public function needsPublishCapability(): bool
    {
        return $this === self::Published || $this === self::Scheduled;
    }

    /**
     * States a user can pick in the editor.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return [self::Draft, self::Scheduled, self::Published];
    }
}

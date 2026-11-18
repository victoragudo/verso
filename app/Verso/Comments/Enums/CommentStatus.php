<?php

namespace App\Verso\Comments\Enums;

// Where a comment is in moderation.
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Trash = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Spam => __('Spam'),
            self::Trash => __('Trash'),
        };
    }

    // True for comments that wait or are live (not spam, not trash).
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}

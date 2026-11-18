<?php

namespace App\Verso\Media\Enums;

// The kind of a media file, found from its mime type.
enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            default => self::Document,
        };
    }

    // The start of the mime types of this kind, or null for documents.
    public function mimePrefix(): ?string
    {
        return $this === self::Document ? null : $this->value.'/';
    }

    public function label(): string
    {
        return match ($this) {
            self::Image => __('Images'),
            self::Video => __('Videos'),
            self::Audio => __('Audio'),
            self::Document => __('Documents'),
        };
    }
}

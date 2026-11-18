<?php

namespace App\Verso\Importer\Content;

// A file that was imported: its media id (when the media table exists) and its new URL.
final readonly class ImportedMedia
{
    public function __construct(
        public ?int $mediaId,
        public string $url,
        public string $path = '',
        public string $alt = '',
    ) {}
}

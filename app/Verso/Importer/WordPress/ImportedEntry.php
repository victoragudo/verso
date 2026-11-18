<?php

namespace App\Verso\Importer\WordPress;

use Carbon\CarbonImmutable;

// An entry made from a WordPress item, kept so redirects can be added at the end.
final readonly class ImportedEntry
{
    public function __construct(
        public int $entryId,
        public int $wordpressId,
        public string $postType,
        public string $link,
        public string $slug,
        public ?CarbonImmutable $localDate,
    ) {}
}

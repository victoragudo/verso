<?php

namespace App\Verso\Importer\Wxr;

// A category, tag or other term from the export file. The taxonomy uses WordPress names.
final readonly class WxrTerm
{
    public function __construct(
        public string $taxonomy,
        public string $slug,
        public string $name,
        public string $parentSlug = '',
        public string $description = '',
    ) {}
}

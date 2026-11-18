<?php

namespace App\Verso\Site\Permalinks;

// The permalink form values: structures and archive bases by type, bases by taxonomy, and the switches.
final readonly class PermalinkValues
{
    /**
     * @param  array<string, string>  $structures
     * @param  array<string, string>  $archiveBases
     * @param  array<string, string>  $taxonomyBases
     */
    public function __construct(
        public array $structures,
        public array $archiveBases,
        public array $taxonomyBases,
        public bool $trailingSlash = false,
        public bool $lowercase = false,
    ) {}
}

<?php

namespace App\Verso\Seo\Links;

// Where an internal link leads: its status and the entry it shows, when it shows one.
final readonly class LinkTarget
{
    public function __construct(
        public LinkStatus $status,
        public ?int $entryId = null,
    ) {}
}

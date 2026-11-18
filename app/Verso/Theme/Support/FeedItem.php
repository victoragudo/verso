<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use Carbon\CarbonInterface;

// One <item> of the RSS feed. $entry is null for items a plugin adds.
final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $link,
        public CarbonInterface $publishedAt,
        public string $content = '',
        public ?string $description = null,
        public ?string $creator = null,
        public ?Entry $entry = null,
    ) {}
}

<?php

namespace App\Verso\Seo\Links;

// The links found in the HTML of one page: internal paths (each once) and how many go to other sites.
final readonly class FoundLinks
{
    /**
     * @param  list<string>  $internal  paths like "/blog/tram-28"
     */
    public function __construct(
        public array $internal,
        public int $external,
    ) {}
}

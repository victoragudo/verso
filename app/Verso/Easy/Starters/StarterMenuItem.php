<?php

namespace App\Verso\Easy\Starters;

// One link of a starter menu: a starter page, an archive (like the blog), a named route or an address.
final readonly class StarterMenuItem
{
    /**
     * @param  list<StarterMenuItem>  $children
     */
    public function __construct(
        public string $label,
        public ?string $page = null,
        public ?string $archive = null,
        public ?string $route = null,
        public ?string $url = null,
        public array $children = [],
    ) {}

    // A link to the starter page with this slug. The label falls back to the page title.
    public static function page(string $slug, string $label = ''): self
    {
        return new self($label, page: $slug);
    }

    // A link to the list of a content type, like "post" for the blog.
    public static function archive(string $type, string $label): self
    {
        return new self($label, archive: $type);
    }

    // A link to a named route, like the shop page of the Shop plugin. Skipped when the route does not exist.
    public static function route(string $name, string $label): self
    {
        return new self($label, route: $name);
    }

    public static function link(string $label, string $url): self
    {
        return new self($label, url: $url);
    }
}

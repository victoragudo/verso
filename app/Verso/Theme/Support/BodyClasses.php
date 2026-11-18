<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters\BodyClassFilter;
use App\Verso\Site\Permalinks\ResolvedPath;
use App\Verso\Theme\Http\Controllers\PublicPathController;
use Illuminate\Http\Request;

// Builds the CSS classes of the <body> of a public page, then lets plugins change them.
class BodyClasses
{
    /** Route name prefixes and the classes they add (plugin routes may still use them). */
    protected const ROUTE_CLASSES = [
        'theme.archive.' => ['archive', 'type-'],
        'theme.term.' => ['taxonomy', 'taxonomy-'],
    ];

    public function __construct(protected Request $request) {}

    public function for(?Entry $entry = null): string
    {
        return implode(' ', BodyClassFilter::apply($this->base($entry), $this->request, $entry));
    }

    /**
     * Classes from the entry, or from the route of a list page.
     *
     * @return list<string>
     */
    public function base(?Entry $entry = null): array
    {
        // "single" is used and not "entry", so the theme's .entry article rules never hit <body>.
        if ($entry !== null) {
            return ['single', 'type-'.$entry->type, 'slug-'.$entry->slug];
        }

        $resolved = $this->request->attributes->get(PublicPathController::ATTRIBUTE);

        if ($resolved instanceof ResolvedPath && $resolved->type !== null) {
            return ['archive', 'type-'.$resolved->type->name];
        }

        if ($resolved instanceof ResolvedPath && $resolved->taxonomy !== null) {
            return ['taxonomy', 'taxonomy-'.$resolved->taxonomy->name];
        }

        $route = (string) $this->request->route()?->getName();

        foreach (self::ROUTE_CLASSES as $prefix => [$kind, $classPrefix]) {
            if (str_starts_with($route, $prefix)) {
                return [$kind, $classPrefix.substr($route, strlen($prefix))];
            }
        }

        return match ($route) {
            'home' => ['home'],
            'theme.search' => ['search'],
            default => [],
        };
    }
}

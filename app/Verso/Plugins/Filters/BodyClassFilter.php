<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use Illuminate\Http\Request;

// Changes the CSS classes of the <body> of public pages. Callbacks: fn (array $classes, Request $request, ?Entry $entry): array.
// Pages are cached, so classes must not depend on the visitor.
final class BodyClassFilter implements Filter
{
    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    public static function apply(array $classes, Request $request, ?Entry $entry = null): array
    {
        $classes = (array) app(FilterRegistry::class)->apply(self::class, $classes, $request, $entry);
        $clean = [];

        foreach ($classes as $class) {
            if (is_string($class) && preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $class) === 1) {
                $clean[$class] = $class;
            }
        }

        return array_values($clean);
    }
}

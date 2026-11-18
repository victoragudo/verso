<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\View\HeadTag;
use Illuminate\Http\Request;

// Adds tags to the <head> of every public page. Callbacks: fn (array $tags, Request $request): array.
// Each tag is a HeadTag. Pages are cached, so tags must not depend on the visitor.
final class HeadTagsFilter implements Filter
{
    /**
     * @param  list<HeadTag>  $tags
     * @return list<HeadTag>
     */
    public static function apply(array $tags, Request $request): array
    {
        $tags = (array) app(FilterRegistry::class)->apply(self::class, $tags, $request);

        return array_values(array_filter($tags, fn (mixed $tag): bool => $tag instanceof HeadTag));
    }
}

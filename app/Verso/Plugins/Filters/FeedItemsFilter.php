<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Theme\Support\FeedItem;

// Changes the items of the RSS feed: add, remove, reorder or edit them.
// Callbacks: fn (array $items, string $feedUrl): array. Each item is a FeedItem.
final class FeedItemsFilter implements Filter
{
    /**
     * @param  list<FeedItem>  $items
     * @return list<FeedItem>
     */
    public static function apply(array $items, string $feedUrl): array
    {
        $items = (array) app(FilterRegistry::class)->apply(self::class, $items, $feedUrl);

        return array_values(array_filter($items, fn (mixed $item): bool => $item instanceof FeedItem));
    }
}

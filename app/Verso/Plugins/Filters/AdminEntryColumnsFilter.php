<?php

namespace App\Verso\Plugins\Filters;

use App\Verso\Content\ContentType;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Plugins\Contracts\Filter;
use App\Verso\Plugins\FilterRegistry;

// Changes the optional columns of the admin entries table of one content type, like price and stock for products.
// Put the values in $row['columns'][$key] with the AdminEntryRowFilter.
// Callbacks: fn (array $columns, ContentType $type): array (a list of ListColumn).
final class AdminEntryColumnsFilter implements Filter
{
    /**
     * @param  list<ListColumn>  $columns
     * @return list<ListColumn>
     */
    public static function apply(array $columns, ContentType $type): array
    {
        $result = app(FilterRegistry::class)->apply(self::class, $columns, $type);

        return array_values(array_filter((array) $result, fn (mixed $column): bool => $column instanceof ListColumn));
    }
}

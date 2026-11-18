<?php

namespace App\Verso\Core\Database;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

// Finds rows where any column contains a piece of text, ignoring case, on SQLite, MySQL and Postgres.
// Postgres needs ilike because its like is case sensitive.
final class TextSearch
{
    /**
     * Add a grouped "column contains text" condition for the given columns.
     *
     * @template TQuery of EloquentBuilder<*>|QueryBuilder
     *
     * @param  TQuery  $query
     * @param  list<literal-string>  $columns  plain column names written in code, never user input
     * @return TQuery
     */
    public static function apply(BuilderContract $query, array $columns, string $term): BuilderContract
    {
        if ($term === '' || $columns === []) {
            return $query;
        }

        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $caseInsensitiveLike = $base->getGrammar() instanceof PostgresGrammar;
        $pattern = self::pattern($term);

        return $query->where(function (BuilderContract $group) use ($columns, $caseInsensitiveLike, $pattern): void {
            foreach ($columns as $column) {
                $group->orWhereRaw(
                    $caseInsensitiveLike ? $column." ilike ? escape '!'" : $column." like ? escape '!'",
                    [$pattern],
                );
            }
        });
    }

    // The LIKE pattern for "contains this text". "!" escapes % and _ so they match as plain text.
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }
}

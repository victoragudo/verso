<?php

namespace App\Verso\Users\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Finds and moves the content that belongs to a user (entries, revisions, media...).
class ContentOwnership
{
    /**
     * @param  array<string, string>  $columns  table => user column
     */
    public function __construct(protected array $columns) {}

    // How many rows in all known tables point to the user.
    public function count(User $user): int
    {
        $total = 0;

        foreach ($this->existingColumns() as $table => $column) {
            $total += DB::table($table)->where($column, $user->getKey())->count();
        }

        return $total;
    }

    public function hasContent(User $user): bool
    {
        return $this->count($user) > 0;
    }

    // Give all content of $from to $to. Returns the number of moved rows.
    public function reassign(User $from, User $to): int
    {
        $moved = 0;

        foreach ($this->existingColumns() as $table => $column) {
            $moved += DB::table($table)->where($column, $from->getKey())->update([$column => $to->getKey()]);
        }

        return $moved;
    }

    /**
     * Only the tables that exist, because some modules may not be installed.
     *
     * @return array<string, string>
     */
    protected function existingColumns(): array
    {
        return array_filter(
            $this->columns,
            fn (string $column, string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}

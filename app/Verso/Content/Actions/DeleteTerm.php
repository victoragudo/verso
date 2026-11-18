<?php

namespace App\Verso\Content\Actions;

use App\Verso\Content\Models\Term;
use Illuminate\Support\Facades\DB;

// Deletes a term. Its children move up to its parent.
class DeleteTerm
{
    public function handle(Term $term): void
    {
        DB::transaction(function () use ($term): void {
            Term::query()->where('parent_id', $term->id)->update(['parent_id' => $term->parent_id]);
            $term->entries()->detach();
            $term->delete();
        });
    }
}

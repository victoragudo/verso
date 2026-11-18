<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Response;

// Lists the published entries of one term, like "/category/travel".
class TermController extends Controller
{
    public function __invoke(EntryListing $listing, ThemeResponse $theme, Taxonomy $taxonomy, Term $term): Response
    {
        // A plain id list from the pivot uses the term_id index instead of checking every entry.
        $query = Entry::query()->whereIn('id', fn (QueryBuilder $pivot) => $pivot
            ->select('entry_id')
            ->from('entry_term')
            ->where('term_id', $term->id));

        return $theme->term($taxonomy, $term, [
            'entries' => $listing->paginate($query, ArchiveContext::taxonomy($taxonomy, $term)),
        ]);
    }
}

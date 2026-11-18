<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Plugins\Filters\SearchResultsFilter;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

// Searches the titles and excerpts of published entries.
class SearchController extends Controller
{
    public function __invoke(Request $request, EntryListing $listing, ThemeResponse $theme): Response
    {
        $search = Str::limit(trim((string) $request->query('q')), (int) config('verso.themes.search_max_length'), '');
        $searching = mb_strlen($search) >= (int) config('verso.themes.search_min_length');

        $entries = null;

        if ($searching) {
            $query = TextSearch::apply(Entry::query(), ['title', 'excerpt'], $search);
            $entries = SearchResultsFilter::apply($listing->paginate($query, ArchiveContext::search($search)), $search);
        }

        return $theme->search([
            'search' => $search,
            'searching' => $searching,
            'entries' => $entries,
        ]);
    }
}

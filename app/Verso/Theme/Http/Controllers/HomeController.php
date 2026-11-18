<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\FrontPage;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\Response;

// The home page: the chosen front page, or the latest posts.
class HomeController extends Controller
{
    public function __invoke(FrontPage $frontPage, EntryListing $listing, ThemeResponse $theme, ContentTypeRegistry $types): Response
    {
        $entry = $frontPage->entry();

        if ($entry !== null) {
            return $theme->entry($entry);
        }

        $type = (string) config('verso.themes.home_content_type');

        return $theme->home([
            'entries' => $listing->paginate(Entry::query()->ofType($type), ArchiveContext::home($types->find($type))),
        ]);
    }
}

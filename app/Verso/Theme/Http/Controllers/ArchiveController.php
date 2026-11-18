<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\Response;

// Lists the published entries of one content type, like "/blog".
class ArchiveController extends Controller
{
    public function __invoke(EntryListing $listing, ThemeResponse $theme, ContentType $contentType): Response
    {
        abort_unless($contentType->hasArchive, 404);

        return $theme->archive($contentType, [
            'entries' => $listing->paginate(Entry::query()->ofType($contentType->name), ArchiveContext::archive($contentType)),
        ]);
    }
}

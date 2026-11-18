<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Easy\Sections\SectionContent;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Sections\SectionPictures;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

// Builds a ready section for the "Add section" gallery, so its blocks, ids and texts come from the server.
class SectionController extends Controller
{
    public function build(string $key, SectionLibrary $library, SectionPictures $pictures, SectionKit $kit, SiteDetails $details): JsonResponse
    {
        Gate::authorize('create', Entry::class);

        $section = $library->find($key);
        abort_if($section === null || ! $library->isAvailable($section), 404);

        // Sections that show contact details start with the ones saved in "Your site details".
        $content = $pictures->fill($section)->withDefaults(new SectionContent(extra: $details->sectionExtra()));

        return response()->json(['blocks' => $section->build($content, $kit)]);
    }
}

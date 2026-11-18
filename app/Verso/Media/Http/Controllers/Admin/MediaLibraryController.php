<?php

namespace App\Verso\Media\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaLibraryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// JSON list of media files for the media picker.
class MediaLibraryController extends Controller
{
    public function __invoke(Request $request, MediaLibraryQuery $library, MediaPresenter $presenter): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $user = $request->user();

        return response()->json(
            $library->paginate($library->filters($request))
                ->through(fn (Media $media): array => $presenter->item($media, $user)),
        );
    }
}

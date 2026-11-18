<?php

namespace App\Verso\Media\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\I18n\Translate;
use App\Verso\Media\Actions\DeleteMedia;
use App\Verso\Media\Actions\UpdateMedia;
use App\Verso\Media\Actions\UploadMedia;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Exceptions\MediaInUseException;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Http\Requests\StoreMediaRequest;
use App\Verso\Media\Http\Requests\UpdateMediaRequest;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaLibraryQuery;
use App\Verso\Media\Support\MediaUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// The media library admin page: list, upload, edit and delete files.
class MediaController extends Controller
{
    public function __construct(protected MediaPresenter $presenter) {}

    public function index(Request $request, MediaLibraryQuery $library): Response
    {
        Gate::authorize('viewAny', Media::class);

        $filters = $library->filters($request);
        $user = $request->user();

        return Inertia::render('admin/media/index', [
            'media' => $library->paginate($filters)
                ->through(fn (Media $media): array => $this->presenter->item($media, $user)),
            'filters' => [
                'search' => $filters['search'],
                'type' => $filters['type']?->value,
            ],
            'types' => array_map(
                fn (MediaType $type): array => ['value' => $type->value, 'label' => $type->label()],
                MediaType::cases(),
            ),
            'upload' => [
                'max_kb' => (int) config('verso.media.max_upload_kb'),
                'accept' => array_values((array) config('verso.media.allowed_mimes')),
            ],
        ]);
    }

    public function show(Request $request, MediaUsage $usage, Media $media): JsonResponse
    {
        Gate::authorize('view', $media);

        return response()->json($this->presenter->detail($media, $usage->entriesUsing($media), $request->user()));
    }

    public function store(StoreMediaRequest $request, UploadMedia $uploadMedia): JsonResponse|RedirectResponse
    {
        $media = $uploadMedia->handle($request->uploadedFile(), $request->user(), $request->mediaData());

        if ($request->expectsJson()) {
            return response()->json($this->presenter->item($media, $request->user()), 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File uploaded.')]);

        return back();
    }

    public function update(UpdateMediaRequest $request, UpdateMedia $updateMedia, Media $media): JsonResponse|RedirectResponse
    {
        $updateMedia->handle($media, $request->mediaData());

        if ($request->expectsJson()) {
            return response()->json($this->presenter->item($media, $request->user()));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File details saved.')]);

        return back();
    }

    public function destroy(Request $request, DeleteMedia $deleteMedia, Media $media): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $media);

        try {
            $deleteMedia->handle($media, $request->boolean('force'));
        } catch (MediaInUseException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'usage' => $exception->entries->count()], 409);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => Translate::icu(
                '{count, plural, one {This file is used in # entry.} other {This file is used in # entries.}} Remove it there first.',
                ['count' => $exception->entries->count()],
            )]);

            return back();
        }

        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }
}

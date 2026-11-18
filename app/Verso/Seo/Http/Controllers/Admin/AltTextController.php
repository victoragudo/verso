<?php

namespace App\Verso\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\I18n\Translate;
use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Filters\AltTextSuggesterFilter;
use App\Verso\Seo\Audit\Checks\ImagesWithoutAlt;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Http\Requests\AltTextRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Bulk "Add alt text" screen: images of the media library with a text field each, and optional suggestions.
class AltTextController extends Controller
{
    public const FILTERS = ['missing', 'all'];

    public function index(Request $request, MediaPresenter $presenter): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = in_array($request->query('show'), self::FILTERS, true) ? (string) $request->query('show') : self::FILTERS[0];
        $search = trim((string) $request->query('search'));

        $images = ($filter === 'missing' ? ImagesWithoutAlt::missingQuery() : Media::query()->ofType(MediaType::Image))
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->with('uploader')
            ->latest('id')
            ->paginate(max(1, (int) config('verso.seo.alt_text.per_page')))
            ->withQueryString();

        return Inertia::render('admin/seo/alt-text', [
            'images' => $images->through(fn (Media $media): array => $presenter->item($media, $user)),
            'filters' => ['show' => $filter, 'search' => $search],
            'missing' => ImagesWithoutAlt::missingQuery()->count(),
            'maxLength' => (int) config('verso.seo.alt_text.max_length'),
            'suggester' => AltTextSuggesterFilter::apply(null, $user)?->toArray(),
        ]);
    }

    public function update(AltTextRequest $request, SeoAudit $audit): RedirectResponse
    {
        /** @var list<array{id: int|string, alt: string|null}> $items */
        $items = $request->validated('items');
        $media = Media::query()->whereKey(array_column($items, 'id'))->get()->keyBy('id');
        $saved = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $file = $media->get((int) $item['id']);

            if (! $file instanceof Media || ! $request->user()?->can('update', $file)) {
                $skipped++;

                continue;
            }

            $alt = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $item['alt'])));
            $file->alt = $alt === '' ? null : $alt;

            if ($file->isDirty('alt')) {
                $file->save();
                $saved++;
            }
        }

        $audit->flush();

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => $skipped > 0
                ? Translate::icu('Saved alt text for {saved, plural, one {# image} other {# images}}. {skipped, plural, one {# image} other {# images}} could not be changed with your role.', ['saved' => $saved, 'skipped' => $skipped])
                : Translate::icu('Saved alt text for {saved, plural, one {# image} other {# images}}.', ['saved' => $saved]),
        ]);

        return back();
    }
}

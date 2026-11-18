<?php

namespace App\Verso\Media\Support;

use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// The shared search, type filter and paging of the media library and the picker.
class MediaLibraryQuery
{
    public function __construct(protected int $perPage) {}

    /**
     * Reads the search and type filters from the query string.
     * "accept=image" from the picker works like "type=image".
     *
     * @return array{search: string, type: MediaType|null}
     */
    public function filters(Request $request): array
    {
        return [
            'search' => trim($this->text($request, 'search')),
            'type' => MediaType::tryFrom($this->text($request, 'type') ?: $this->text($request, 'accept')),
        ];
    }

    // A query string value, or an empty string when it is missing or not text.
    protected function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array{search: string, type: MediaType|null}  $filters
     * @return LengthAwarePaginator<int, Media>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Media::query()
            ->with('uploader')
            ->search($filters['search'])
            ->when($filters['type'], fn ($query, MediaType $type) => $query->ofType($type))
            ->latest()
            ->latest('id')
            ->paginate($this->perPage)
            ->withQueryString();
    }
}

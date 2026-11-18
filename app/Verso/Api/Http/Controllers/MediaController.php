<?php

namespace App\Verso\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Api\Http\Resources\MediaResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Public API for the media library. It reads the media table directly.
class MediaController extends Controller
{
    // Allowed values for ?type, matched against the start of the MIME type.
    protected const TYPES = ['image' => 'image/', 'video' => 'video/', 'audio' => 'audio/', 'document' => 'application/'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('verso.api.max_per_page')],
            'type' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
        ]);

        $type = self::TYPES[(string) $request->string('type')] ?? null;

        $media = $this->query()
            ->when($type !== null, fn (Builder $query) => $query->where('mime_type', 'like', $type.'%'))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', (int) config('verso.api.per_page')))
            ->withQueryString();

        return MediaResource::collection($media);
    }

    public function show(int $media): MediaResource
    {
        $item = $this->query()->where('id', $media)->first();

        abort_if($item === null, 404);

        return MediaResource::make($item);
    }

    protected function query(): Builder
    {
        abort_unless(Schema::hasTable('media'), 404);

        return DB::table('media')->select([
            'id', 'disk', 'path', 'filename', 'mime_type', 'size', 'width', 'height', 'alt', 'caption', 'created_at',
        ]);
    }
}

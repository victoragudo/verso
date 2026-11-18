<?php

namespace App\Verso\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

// Public JSON for one row of the media table.
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var object{id: int|string, disk: string, path: string, filename: string, mime_type: string, size: int|string, width: int|string|null, height: int|string|null, alt: string|null, caption: string|null, created_at: string|null} $media */
        $media = $this->resource;

        return [
            'id' => (int) $media->id,
            'url' => Storage::disk($media->disk)->url($media->path),
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'size' => (int) $media->size,
            'width' => $media->width === null ? null : (int) $media->width,
            'height' => $media->height === null ? null : (int) $media->height,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'created_at' => $media->created_at,
        ];
    }
}

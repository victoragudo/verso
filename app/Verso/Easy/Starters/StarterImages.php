<?php

namespace App\Verso\Easy\Starters;

use App\Models\User;
use App\Verso\Media\Models\Media;
use Database\Seeders\Support\DemoImagePainter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Paints the sample pictures of a starter site and keeps them as media files. A second run reuses the same rows.
class StarterImages
{
    public function __construct(
        protected int $width,
        protected int $height,
        protected int $quality,
        protected int $portraitSize,
        protected string $disk,
        protected string $folder,
    ) {}

    /**
     * Paints and stores every picture of the starter. Pictures that cannot be painted (no GD) are left out.
     *
     * @return array<string, array{mediaId: int, src: string, alt: string, width: int, height: int}> image attrs by picture name
     */
    public function store(StarterSite $site, ?User $uploader = null): array
    {
        $painter = new DemoImagePainter($this->width, $this->height, $this->quality);
        $images = [];

        foreach ($site->images as $seed => $image) {
            try {
                $media = $this->save($site, $image, $painter, $seed + 1, $uploader);
            } catch (Throwable $exception) {
                Log::warning("Verso could not paint the sample picture \"{$image->name}\".", ['exception' => $exception]);

                continue;
            }

            $images[$image->name] = [
                'mediaId' => (int) $media->id,
                'src' => $media->url(),
                'alt' => (string) $media->alt,
                'width' => (int) $media->width,
                'height' => (int) $media->height,
            ];
        }

        return $images;
    }

    // Path of a picture on the media disk, like "easy/restaurant/restaurant-loaves.jpg".
    public function path(StarterSite $site, StarterImage $image): string
    {
        return trim($this->folder, '/').'/'.$site->key.'/'.$image->name.'.jpg';
    }

    protected function save(StarterSite $site, StarterImage $image, DemoImagePainter $painter, int $seed, ?User $uploader): Media
    {
        $width = $image->isPortrait() ? $this->portraitSize : $this->width;
        $height = $image->isPortrait() ? $this->portraitSize : $this->height;
        $bytes = $painter->paintSized($image->palette, $seed, $image->style, $width, $height);
        $path = $this->path($site, $image);

        Storage::disk($this->disk)->put($path, $bytes);

        $media = Media::query()->firstOrNew(['disk' => $this->disk, 'path' => $path]);
        $media->fill([
            'filename' => $image->name.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'alt' => $image->alt,
            'caption' => '',
            'meta' => [],
        ]);
        $media->uploaded_by ??= $uploader?->id;
        $media->save();

        return $media;
    }
}

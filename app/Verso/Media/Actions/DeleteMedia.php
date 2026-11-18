<?php

namespace App\Verso\Media\Actions;

use App\Verso\Media\Exceptions\MediaInUseException;
use App\Verso\Media\Images\ImageServerFactory;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaUsage;
use Illuminate\Support\Facades\Storage;

// Deletes a media file, its resized copies and its database row.
class DeleteMedia
{
    public function __construct(
        protected MediaUsage $usage,
        protected ImageServerFactory $images,
    ) {}

    /**
     * Refuses to delete a file that entries use, unless $force is true.
     *
     * @throws MediaInUseException
     */
    public function handle(Media $media, bool $force = false): void
    {
        if (! $force) {
            $entries = $this->usage->entriesUsing($media);

            if ($entries->isNotEmpty()) {
                throw new MediaInUseException($entries);
            }
        }

        if ($media->isResizable()) {
            $this->images->forget($media->path);
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}

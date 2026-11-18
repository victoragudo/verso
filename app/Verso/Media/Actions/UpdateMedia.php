<?php

namespace App\Verso\Media\Actions;

use App\Verso\Media\Models\Media;

/**
 * Changes the alt text and caption of a media file.
 *
 * @phpstan-type MediaData array{alt?: string|null, caption?: string|null}
 */
class UpdateMedia
{
    /**
     * @param  MediaData  $data
     */
    public function handle(Media $media, array $data): Media
    {
        if (array_key_exists('alt', $data)) {
            $media->alt = $this->clean($data['alt']);
        }

        if (array_key_exists('caption', $data)) {
            $media->caption = $this->clean($data['caption']);
        }

        $media->save();

        return $media;
    }

    // Empty text is stored as null.
    protected function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

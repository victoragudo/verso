<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Media\Actions\UploadMedia;
use App\Verso\Media\Models\Media;
use Illuminate\Http\UploadedFile;

// Sets or removes the profile photo. A new photo goes to the media library, so Glide makes its sizes.
// The old photo stays in the library, because it may be used in posts too.
class ChangeAvatar
{
    public function __construct(protected UploadMedia $upload) {}

    public function upload(User $user, UploadedFile $file): Media
    {
        $media = $this->upload->handle($file, $user, ['alt' => $user->name]);
        $user->forceFill(['avatar_media_id' => $media->id])->save();

        return $media;
    }

    public function remove(User $user): void
    {
        $user->forceFill(['avatar_media_id' => null])->save();
    }
}

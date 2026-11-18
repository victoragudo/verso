<?php

namespace App\Verso\Media\Policies;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Media\Models\Media;

// Who may see, upload, edit and delete media files.
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(Capability::UploadFiles);
    }

    public function view(User $user, Media $media): bool
    {
        return $user->hasCapability(Capability::UploadFiles);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(Capability::UploadFiles);
    }

    // Everyone with upload rights may change their own files. "Edit others' media" allows any file.
    public function update(User $user, Media $media): bool
    {
        return $user->hasCapability(Capability::UploadFiles)
            && ($media->uploaded_by === $user->id || $user->hasCapability(Capability::EditOthersMedia));
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }
}

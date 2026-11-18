<?php

namespace App\Verso\Comments\Policies;

use App\Models\User;
use App\Verso\Comments\Models\Comment;
use App\Verso\Core\Enums\Capability;

// Only users with the moderate comments capability can see and change comments in the admin.
class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(Capability::ModerateComments);
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->hasCapability(Capability::ModerateComments);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasCapability(Capability::ModerateComments);
    }
}

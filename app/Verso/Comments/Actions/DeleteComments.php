<?php

namespace App\Verso\Comments\Actions;

use App\Verso\Comments\Models\Comment;

// Deletes comments for good. Only comments in spam or trash are deleted.
class DeleteComments
{
    /**
     * Returns the number of deleted comments.
     *
     * @param  list<int>  $ids
     */
    public function handle(array $ids): int
    {
        $deleted = 0;

        foreach (Comment::query()->whereKey($ids)->get() as $comment) {
            if ($comment->canBeDeleted()) {
                $comment->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}

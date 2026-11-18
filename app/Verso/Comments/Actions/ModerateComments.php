<?php

namespace App\Verso\Comments\Actions;

use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use Illuminate\Support\Facades\DB;

// Gives many comments the same status at once (approve, spam, trash...).
class ModerateComments
{
    /**
     * Returns the number of comments that changed.
     *
     * @param  list<int>  $ids
     */
    public function handle(array $ids, CommentStatus $status): int
    {
        return DB::transaction(function () use ($ids, $status): int {
            $changed = 0;

            foreach (Comment::query()->whereKey($ids)->get() as $comment) {
                if ($comment->status !== $status) {
                    $comment->moveTo($status);
                    $changed++;
                }
            }

            return $changed;
        });
    }
}

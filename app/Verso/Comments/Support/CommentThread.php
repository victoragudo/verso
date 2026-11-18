<?php

namespace App\Verso\Comments\Support;

use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Collection;

// Loads the approved comments of an entry as a tree, with one query.
class CommentThread
{
    /**
     * Top level comments. Each one has its replies in the "replies" relation.
     *
     * @return Collection<int, Comment>
     */
    public function forEntry(Entry $entry): Collection
    {
        $comments = Comment::query()
            ->where('entry_id', $entry->id)
            ->approved()
            ->oldest()
            ->oldest('id')
            ->get();

        return $this->build($comments);
    }

    /**
     * Turn a flat list into a tree. A reply whose parent is missing becomes a top comment.
     *
     * @param  Collection<int, Comment>  $comments
     * @return Collection<int, Comment>
     */
    public function build(Collection $comments): Collection
    {
        $byId = $comments->keyBy('id');
        $children = [];
        $roots = new Collection;

        foreach ($comments as $comment) {
            if ($comment->parent_id !== null && $byId->has($comment->parent_id)) {
                $children[$comment->parent_id][] = $comment;
            } else {
                $roots->push($comment);
            }
        }

        foreach ($comments as $comment) {
            $comment->setRelation('replies', new Collection($children[$comment->id] ?? []));
        }

        return $roots;
    }
}

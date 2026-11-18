<?php

namespace App\Verso\Comments\Admin;

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;

// The newest comments that wait for a moderator, for the dashboard.
class PendingCommentsPanel
{
    public function __construct(
        protected int $limit,
        protected int $excerptLength = 90,
    ) {}

    /**
     * @return list<array{key: string, title: string, meta: string|null, href: string|null, status: string|null}>
     */
    public function __invoke(User $user): array
    {
        $comments = Comment::query()
            ->withStatus(CommentStatus::Pending)
            ->with('entry:id,title')
            ->latest()
            ->latest('id')
            ->limit($this->limit)
            ->get();

        $href = route('admin.comments.index', ['status' => CommentStatus::Pending->value], false);

        return array_values($comments->map(fn (Comment $comment): array => [
            'key' => 'comment-'.$comment->id,
            'title' => $comment->excerpt($this->excerptLength),
            'meta' => $comment->entry === null
                ? $comment->author_name
                : __(':author on :title', ['author' => $comment->author_name, 'title' => $comment->entry->title]),
            'href' => $href,
            'status' => $comment->status->value,
        ])->all());
    }
}

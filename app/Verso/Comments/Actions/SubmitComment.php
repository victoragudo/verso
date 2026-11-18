<?php

namespace App\Verso\Comments\Actions;

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Events\CommentSubmitted;
use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\CommentAvailability;
use App\Verso\Comments\Support\SpamScore;
use App\Verso\Content\Models\Entry;
use LogicException;

// Saves a new comment and picks its status: approved for users, spam for many links, else pending.
class SubmitComment
{
    public function __construct(
        protected CommentAvailability $availability,
        protected SpamScore $spamScore,
        protected int $maxDepth,
        protected string $ipSecret,
    ) {}

    /**
     * @param  array{body: string, author_name?: string|null, author_email?: string|null, author_url?: string|null, parent_id?: int|null, ip?: string|null, user_agent?: string|null}  $data
     */
    public function handle(Entry $entry, array $data, ?User $user = null): Comment
    {
        if (! $this->availability->isOpenFor($entry)) {
            throw new LogicException('Comments are closed for this entry.');
        }

        $comment = new Comment([
            'entry_id' => $entry->id,
            'parent_id' => $this->replyTarget($entry, $data['parent_id'] ?? null)?->id,
            'user_id' => $user?->id,
            'author_name' => $user->name ?? trim((string) ($data['author_name'] ?? '')),
            'author_email' => $user->email ?? ($data['author_email'] ?? null),
            'author_url' => $user === null ? ($data['author_url'] ?? null) : null,
            'body' => trim($data['body']),
            'ip_hash' => $this->hashIp($data['ip'] ?? null),
            'user_agent' => isset($data['user_agent']) ? mb_substr($data['user_agent'], 0, 512) : null,
        ]);

        $comment->status = $this->statusFor($comment, $user);
        $comment->save();

        CommentSubmitted::dispatch($comment);

        return $comment;
    }

    protected function statusFor(Comment $comment, ?User $user): CommentStatus
    {
        if ($user !== null) {
            return CommentStatus::Approved;
        }

        if ($this->spamScore->isSpam($comment->body, $comment->author_name)) {
            return CommentStatus::Spam;
        }

        return CommentStatus::Pending;
    }

    // The approved parent on the same entry. Too deep replies go to the deepest allowed level.
    protected function replyTarget(Entry $entry, ?int $parentId): ?Comment
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::query()->where('entry_id', $entry->id)->approved()->find($parentId);

        while ($parent !== null && $parent->depth() >= max($this->maxDepth, 1)) {
            $parent = $parent->parent;
        }

        return $parent;
    }

    // Keep a private fingerprint of the IP instead of the IP itself.
    protected function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', $ip, $this->ipSecret);
    }
}

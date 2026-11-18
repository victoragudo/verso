<?php

namespace App\Verso\Comments\Models;

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Policies\CommentPolicy;
use App\Verso\Content\Models\Entry;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A comment on an entry, from a visitor or a signed in user.
 *
 * @property int $id
 * @property int $entry_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $author_name
 * @property string|null $author_email
 * @property string|null $author_url
 * @property string $body
 * @property CommentStatus $status
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entry|null $entry
 * @property-read Comment|null $parent
 * @property-read User|null $user
 * @property-read Collection<int, Comment> $replies
 */
#[Fillable(['entry_id', 'parent_id', 'user_id', 'author_name', 'author_email', 'author_url', 'body', 'ip_hash', 'user_agent'])]
#[UseFactory(CommentFactory::class)]
#[UsePolicy(CommentPolicy::class)]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    // Stops endless loops when walking up a broken parent chain.
    protected const MAX_DEPTH = 64;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class)->withTrashed();
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest()->oldest('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Comment> $query */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    /** @param Builder<Comment> $query */
    #[Scope]
    protected function withStatus(Builder $query, CommentStatus $status): void
    {
        $query->where('status', $status);
    }

    // Pending and approved comments, the "All" tab of the admin.
    /** @param Builder<Comment> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [CommentStatus::Pending, CommentStatus::Approved]);
    }

    public function approve(): static
    {
        return $this->moveTo(CommentStatus::Approved);
    }

    public function unapprove(): static
    {
        return $this->moveTo(CommentStatus::Pending);
    }

    public function markAsSpam(): static
    {
        return $this->moveTo(CommentStatus::Spam);
    }

    public function moveToTrash(): static
    {
        return $this->moveTo(CommentStatus::Trash);
    }

    // Save a new status. Nothing is written when it does not change.
    public function moveTo(CommentStatus $status): static
    {
        if ($this->status !== $status) {
            $this->status = $status;
            $this->save();
        }

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === CommentStatus::Pending;
    }

    // Comments in spam or trash can be deleted for good.
    public function canBeDeleted(): bool
    {
        return ! $this->status->isActive();
    }

    public function isFromMember(): bool
    {
        return $this->user_id !== null;
    }

    // Reply level: 1 for a top comment, 2 for a reply, and so on.
    public function depth(): int
    {
        $depth = 1;
        $current = $this->parent;

        while ($current !== null && $depth < self::MAX_DEPTH) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    // Short plain text version of the body for lists.
    public function excerpt(int $length = 140): string
    {
        return str((string) preg_replace('/\s+/', ' ', $this->body))->trim()->limit($length)->toString();
    }

    // Body as safe HTML: escaped text with line breaks.
    public function bodyHtml(): string
    {
        return nl2br(e($this->body), false);
    }

    // The author website, only when it is an http or https link.
    public function safeAuthorUrl(): ?string
    {
        $url = $this->author_url;

        if ($url === null || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * Number of comments for each status.
     *
     * @return array<string, int>
     */
    public static function countsByStatus(): array
    {
        $counts = static::query()
            ->toBase()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (CommentStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }
}

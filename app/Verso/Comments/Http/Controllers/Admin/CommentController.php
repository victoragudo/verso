<?php

namespace App\Verso\Comments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Database\TextSearch;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin moderation page: lists comments by status with counts and search.
class CommentController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        Gate::authorize('viewAny', Comment::class);

        $status = CommentStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));

        $comments = Comment::query()
            ->with('entry:id,type,title,slug,status,published_at,parent_id,deleted_at')
            ->when($status !== null, fn (Builder $query) => $query->withStatus($status), fn (Builder $query) => $query->active())
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['body', 'author_name', 'author_email'], $search))
            ->latest()
            ->latest('id')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString()
            ->through(fn (Comment $comment): array => $this->row($comment));

        $counts = Comment::countsByStatus();

        return Inertia::render('admin/comments/index', [
            'comments' => $comments,
            'filters' => ['status' => $status?->value, 'search' => $search],
            'counts' => ['all' => $counts['pending'] + $counts['approved'], ...$counts],
            'listView' => AdminPreferences::of($user)->listViewProps('comments'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Comment $comment): array
    {
        $entry = $comment->entry;

        return [
            'id' => $comment->id,
            'status' => $comment->status->value,
            'author_name' => $comment->author_name,
            'author_email' => $comment->author_email,
            'author_url' => $comment->safeAuthorUrl(),
            'is_member' => $comment->isFromMember(),
            'body' => $comment->body,
            'excerpt' => $comment->excerpt(),
            'parent_id' => $comment->parent_id,
            'entry' => $entry === null ? null : [
                'id' => $entry->id,
                'title' => $entry->title,
                'url' => $entry->isPublished() ? $entry->url() : null,
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}

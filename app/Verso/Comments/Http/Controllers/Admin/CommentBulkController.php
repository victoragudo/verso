<?php

namespace App\Verso\Comments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Comments\Actions\DeleteComments;
use App\Verso\Comments\Actions\ModerateComments;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\I18n\Translate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// Approves, marks as spam, trashes or deletes many comments at once.
class CommentBulkController extends Controller
{
    // Bulk action name => new status. "delete" removes comments for good.
    protected const ACTIONS = [
        'approve' => CommentStatus::Approved,
        'unapprove' => CommentStatus::Pending,
        'spam' => CommentStatus::Spam,
        'trash' => CommentStatus::Trash,
        'restore' => CommentStatus::Pending,
    ];

    protected const DELETE = 'delete';

    public function __invoke(Request $request, ModerateComments $moderateComments, DeleteComments $deleteComments): RedirectResponse
    {
        Gate::authorize('viewAny', Comment::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in([...array_keys(self::ACTIONS), self::DELETE])],
            'ids' => ['required', 'array', 'max:'.(int) config('verso.admin.per_page')],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_map(intval(...), $validated['ids']));
        $action = (string) $validated['action'];

        $done = $action === self::DELETE
            ? $deleteComments->handle($ids)
            : $moderateComments->handle($ids, self::ACTIONS[$action]);

        $message = match ($action) {
            'approve' => '{count, plural, one {# comment approved.} other {# comments approved.}}',
            'unapprove' => '{count, plural, one {# comment unapproved.} other {# comments unapproved.}}',
            'spam' => '{count, plural, one {# comment marked as spam.} other {# comments marked as spam.}}',
            'trash' => '{count, plural, one {# comment moved to the trash.} other {# comments moved to the trash.}}',
            'restore' => '{count, plural, one {# comment restored.} other {# comments restored.}}',
            default => '{count, plural, one {# comment deleted.} other {# comments deleted.}}',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu($message, ['count' => $done])]);

        return back();
    }
}

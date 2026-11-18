<?php

namespace App\Verso\Comments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Comments\Actions\SubmitComment;
use App\Verso\Comments\Http\Requests\SubmitCommentRequest;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

// Public endpoint that receives comments from the entry page.
class CommentController extends Controller
{
    // Session key read by the comments component to show a notice.
    public const FLASH_KEY = 'verso_comment';

    // A fresh CSRF token for the comment form. Never cached, because it belongs to the visitor session.
    public function token(): JsonResponse
    {
        return new JsonResponse(['token' => csrf_token()], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function store(SubmitCommentRequest $request, SubmitComment $submitComment, Entry $entry): JsonResponse|RedirectResponse
    {
        $comment = $request->isBot()
            ? null
            : $submitComment->handle($entry, $request->commentData(), $request->user());

        $approved = $comment !== null && $comment->isApproved();
        $message = $approved
            ? __('Thanks! Your comment is now live.')
            : __('Thanks! Your comment is waiting for moderation.');

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $message,
                'status' => $approved ? 'approved' : 'pending',
                'id' => $approved ? $comment->id : null,
            ], 201);
        }

        return redirect()
            ->to($entry->url().$this->anchor($comment))
            ->with(self::FLASH_KEY, ['status' => $approved ? 'approved' : 'pending', 'message' => $message]);
    }

    // Approved comments jump to themselves, others to the form.
    protected function anchor(?Comment $comment): string
    {
        return $comment !== null && $comment->isApproved() ? '#comment-'.$comment->id : '#respond';
    }
}

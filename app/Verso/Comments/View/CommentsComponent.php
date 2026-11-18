<?php

namespace App\Verso\Comments\View;

use App\Verso\Comments\Http\Controllers\CommentController;
use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\CommentAvailability;
use App\Verso\Comments\Support\CommentThread;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

// The <x-verso-comments :entry="$entry" /> component: threaded list and form.
class CommentsComponent extends Component
{
    /** @var Collection<int, Comment> */
    public Collection $comments;

    public int $count;

    public bool $enabled;

    public bool $open;

    public ?Comment $replyTo;

    /** @var array{status: string, message: string}|null */
    public ?array $notice;

    public function __construct(
        public Entry $entry,
        CommentAvailability $availability,
        CommentThread $thread,
    ) {
        $this->enabled = $availability->enabled();
        $this->open = $availability->isOpenFor($entry);
        $this->comments = $this->enabled ? $thread->forEntry($entry) : new Collection;
        $this->count = $this->enabled ? Comment::query()->where('entry_id', $entry->id)->approved()->count() : 0;
        $this->replyTo = $this->findReplyTarget();
        $this->notice = $this->flashNotice();
    }

    public function shouldRender(): bool
    {
        return $this->enabled;
    }

    public function render(): View
    {
        return view('verso-comments::comments', [
            'action' => route('comments.store', $this->entry),
            'tokenUrl' => route('comments.token'),
            'honeypotField' => (string) config('verso.comments.honeypot_field'),
            'timerField' => FormTimer::FIELD,
            'timerToken' => app(FormTimer::class)->token(),
            'user' => auth()->user(),
            'maxLength' => (int) config('verso.comments.max_length'),
            'heading' => Translate::icu('{count, plural, =0 {No comments yet} one {# comment} other {# comments}}', ['count' => $this->count]),
        ]);
    }

    // The comment named in ?reply_to=, when it is an approved comment of this entry.
    protected function findReplyTarget(): ?Comment
    {
        $id = request()->integer('reply_to', (int) old('parent_id'));

        if ($id === 0 || ! $this->open) {
            return null;
        }

        return Comment::query()->where('entry_id', $this->entry->id)->approved()->find($id);
    }

    /**
     * @return array{status: string, message: string}|null
     */
    protected function flashNotice(): ?array
    {
        $notice = session(CommentController::FLASH_KEY);

        return is_array($notice) && isset($notice['status'], $notice['message'])
            ? ['status' => (string) $notice['status'], 'message' => (string) $notice['message']]
            : null;
    }
}

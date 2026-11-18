<?php

use App\Models\User;
use App\Verso\Comments\Http\Controllers\CommentController;
use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    $this->entry = Entry::factory()->published()->create(['title' => 'A Slow Weekend in Porto']);
    view()->share('errors', new ViewErrorBag);
});

function renderComments(Entry $entry): string
{
    return Blade::render('<x-verso-comments :entry="$entry" />', ['entry' => $entry]);
}

it('renders the threaded list and the form', function () {
    $top = Comment::factory()->on($this->entry)->approved()->create(['author_name' => 'Duarte Faria', 'body' => 'The francesinha place was amazing.']);
    Comment::factory()->replyTo($top)->approved()->create(['author_name' => 'Inês Lopes', 'body' => 'Which one did you try?']);
    Comment::factory()->on($this->entry)->create(['body' => 'Waiting for moderation']);

    $html = renderComments($this->entry);

    expect($html)->toContain('2 comments')
        ->toContain('id="comment-'.$top->id.'"')
        ->toContain('Duarte Faria')
        ->toContain('verso-comment__replies')
        ->toContain('Inês Lopes')
        ->not->toContain('Waiting for moderation')
        ->toContain('action="'.route('comments.store', $this->entry).'"')
        ->toContain('name="'.FormTimer::FIELD.'"')
        ->toContain('name="'.config('verso.comments.honeypot_field').'"')
        ->toContain('name="author_email"')
        ->toContain('Leave a comment');
});

it('shows a friendly title when there are no comments', function () {
    expect(renderComments($this->entry))->toContain('No comments yet');
});

it('escapes comment text and author links', function () {
    Comment::factory()->on($this->entry)->approved()->create([
        'author_name' => '<b>Bold</b>',
        'author_url' => 'javascript:alert(1)',
        'body' => '<img src=x onerror=alert(1)>',
    ]);

    $html = renderComments($this->entry);

    expect($html)->not->toContain('<img src=x')
        ->not->toContain('javascript:alert')
        ->toContain('&lt;b&gt;Bold&lt;/b&gt;');
});

it('marks author links as user content', function () {
    Comment::factory()->on($this->entry)->approved()->create(['author_url' => 'https://porto-walks.example']);

    expect(renderComments($this->entry))->toContain('href="https://porto-walks.example" rel="nofollow ugc noopener"');
});

it('hides the name fields for signed in users', function () {
    $this->actingAs(User::factory()->create(['name' => 'Carla Pinto']));

    expect(renderComments($this->entry))->toContain('Signed in as Carla Pinto')
        ->not->toContain('name="author_email"');
});

it('prepares a reply when reply_to names an approved comment', function () {
    $top = Comment::factory()->on($this->entry)->approved()->create(['author_name' => 'Duarte Faria']);
    request()->query->set('reply_to', (string) $top->id);

    expect(renderComments($this->entry))->toContain('Reply to Duarte Faria')
        ->toContain('name="parent_id" value="'.$top->id.'"');
});

it('ignores reply_to for comments of other entries', function () {
    $elsewhere = Comment::factory()->approved()->create();
    request()->query->set('reply_to', (string) $elsewhere->id);

    expect(renderComments($this->entry))->not->toContain('name="parent_id"');
});

it('says when comments are closed but keeps the list', function () {
    Comment::factory()->on($this->entry)->approved()->create(['body' => 'An older comment.']);
    $this->entry->update(['meta' => ['comments_open' => false]]);

    expect(renderComments($this->entry))->toContain('Comments are closed.')
        ->toContain('An older comment.')
        ->not->toContain('<form')
        ->not->toContain('>Reply<');
});

it('renders nothing when comments are off for the site', function () {
    Comment::factory()->on($this->entry)->approved()->create();
    app(Settings::class)->set('comments.enabled', false);

    expect(trim(renderComments($this->entry)))->toBe('');
});

it('shows the notice after sending', function () {
    session()->flash(CommentController::FLASH_KEY, ['status' => 'pending', 'message' => 'Thanks! Your comment is waiting for moderation.']);

    expect(renderComments($this->entry))->toContain('verso-comments__notice--pending')
        ->toContain('waiting for moderation');
});

it('shows form errors from the comment bag', function () {
    $errors = new ViewErrorBag;
    $errors->put('comment', new MessageBag(['body' => 'Please write a comment.', 'form' => 'This form has expired.']));
    view()->share('errors', $errors);

    expect(renderComments($this->entry))->toContain('Please write a comment.')
        ->toContain('This form has expired.');
});

it('links field errors to their fields', function () {
    view()->share('errors', (new ViewErrorBag)->put('comment', new MessageBag(['body' => 'Write a comment first.'])));

    $html = renderComments($this->entry);

    expect($html)->toContain('aria-invalid="true" aria-describedby="verso-comment-body-error"')
        ->toContain('<span class="verso-comments__error" id="verso-comment-body-error">Write a comment first.</span>')
        ->not->toContain('aria-describedby="verso-comment-name-error"');
});

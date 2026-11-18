<?php

use App\Models\User;
use App\Verso\Comments\Actions\DeleteComments;
use App\Verso\Comments\Actions\ModerateComments;
use App\Verso\Comments\Actions\SubmitComment;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Events\CommentSubmitted;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->entry = Entry::factory()->published()->create(['title' => 'Cooking Paella for Twelve People']);
});

it('saves a guest comment as pending', function () {
    Event::fake([CommentSubmitted::class]);

    $comment = app(SubmitComment::class)->handle($this->entry, [
        'body' => '  We made it for a family party and it worked great.  ',
        'author_name' => 'Miguel Torres',
        'author_email' => 'miguel@torres.example',
        'author_url' => 'https://miguel-cooks.example',
        'ip' => '203.0.113.42',
        'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0',
    ]);

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and($comment->body)->toBe('We made it for a family party and it worked great.')
        ->and($comment->author_name)->toBe('Miguel Torres')
        ->and($comment->author_url)->toBe('https://miguel-cooks.example')
        ->and($comment->user_id)->toBeNull()
        ->and($comment->ip_hash)->toHaveLength(64)
        ->and($comment->ip_hash)->not->toContain('203.0.113.42')
        ->and($comment->user_agent)->toContain('Firefox');

    Event::assertDispatched(CommentSubmitted::class, fn (CommentSubmitted $event) => $event->comment->is($comment));
});

it('hashes the same ip the same way', function () {
    $data = ['body' => 'Lovely.', 'author_name' => 'Ana', 'author_email' => 'ana@example.org', 'ip' => '198.51.100.7'];

    $first = app(SubmitComment::class)->handle($this->entry, $data);
    $second = app(SubmitComment::class)->handle($this->entry, $data);
    $other = app(SubmitComment::class)->handle($this->entry, [...$data, 'ip' => '198.51.100.8']);

    expect($first->ip_hash)->toBe($second->ip_hash)->not->toBe($other->ip_hash);
});

it('approves comments from signed in users', function () {
    $member = User::factory()->create(['name' => 'Beatriz Nunes', 'email' => 'beatriz@nunes.example']);

    $comment = app(SubmitComment::class)->handle($this->entry, [
        'body' => 'Saving this for the summer.',
        'author_name' => 'Someone Else',
        'author_url' => 'https://ignored.example',
    ], $member);

    expect($comment->status)->toBe(CommentStatus::Approved)
        ->and($comment->user_id)->toBe($member->id)
        ->and($comment->author_name)->toBe('Beatriz Nunes')
        ->and($comment->author_email)->toBe('beatriz@nunes.example')
        ->and($comment->author_url)->toBeNull();
});

it('sends comments with many links to spam', function () {
    $comment = app(SubmitComment::class)->handle($this->entry, [
        'body' => 'Buy now https://pills.example https://watches.example www.loans.example',
        'author_name' => 'Best Deals',
        'author_email' => 'deals@spam.example',
    ]);

    expect($comment->status)->toBe(CommentStatus::Spam);
});

it('attaches a reply to its parent', function () {
    $parent = Comment::factory()->on($this->entry)->approved()->create();

    $reply = app(SubmitComment::class)->handle($this->entry, [
        'body' => 'Agreed!', 'author_name' => 'Rita', 'author_email' => 'rita@example.org', 'parent_id' => $parent->id,
    ]);

    expect($reply->parent_id)->toBe($parent->id);
});

it('ignores parents that are not approved or on another entry', function () {
    $pending = Comment::factory()->on($this->entry)->create();
    $elsewhere = Comment::factory()->approved()->create();
    $data = ['body' => 'Agreed!', 'author_name' => 'Rita', 'author_email' => 'rita@example.org'];

    expect(app(SubmitComment::class)->handle($this->entry, [...$data, 'parent_id' => $pending->id])->parent_id)->toBeNull()
        ->and(app(SubmitComment::class)->handle($this->entry, [...$data, 'parent_id' => $elsewhere->id])->parent_id)->toBeNull();
});

it('keeps replies within the maximum depth', function () {
    config()->set('verso.comments.max_depth', 2);
    app()->forgetInstance(SubmitComment::class);
    $top = Comment::factory()->on($this->entry)->approved()->create();
    $reply = Comment::factory()->replyTo($top)->approved()->create();

    $deep = app(SubmitComment::class)->handle($this->entry, [
        'body' => 'One more thought.', 'author_name' => 'Rita', 'author_email' => 'rita@example.org', 'parent_id' => $reply->id,
    ]);

    expect($deep->parent_id)->toBe($top->id)->and($deep->depth())->toBe(2);
});

it('refuses comments when they are closed', function () {
    app(Settings::class)->set('comments.enabled', false);

    app(SubmitComment::class)->handle($this->entry, ['body' => 'Hello', 'author_name' => 'Rita', 'author_email' => 'rita@example.org']);
})->throws(LogicException::class, 'closed');

it('moderates many comments and counts only real changes', function () {
    $pending = Comment::factory()->count(2)->create();
    $approved = Comment::factory()->approved()->create();

    $changed = app(ModerateComments::class)->handle([...$pending->pluck('id')->all(), $approved->id], CommentStatus::Approved);

    expect($changed)->toBe(2)
        ->and(Comment::query()->approved()->count())->toBe(3);
});

it('deletes only comments in spam or trash', function () {
    $spam = Comment::factory()->spam()->create();
    $trash = Comment::factory()->trashed()->create();
    $live = Comment::factory()->approved()->create();

    $deleted = app(DeleteComments::class)->handle([$spam->id, $trash->id, $live->id]);

    expect($deleted)->toBe(2)
        ->and(Comment::query()->pluck('id')->all())->toBe([$live->id]);
});

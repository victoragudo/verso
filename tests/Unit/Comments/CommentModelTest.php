<?php

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;

it('belongs to an entry, a parent and a user', function () {
    $reader = User::factory()->create(['name' => 'Joana Sousa']);
    $parent = Comment::factory()->approved()->create();
    $reply = Comment::factory()->replyTo($parent)->by($reader)->create();

    expect($reply->entry->is($parent->entry))->toBeTrue()
        ->and($reply->parent->is($parent))->toBeTrue()
        ->and($reply->user->is($reader))->toBeTrue()
        ->and($parent->replies)->toHaveCount(1)
        ->and($reply->isFromMember())->toBeTrue()
        ->and($parent->isFromMember())->toBeFalse();
});

it('still finds the entry when it is in the trash', function () {
    $comment = Comment::factory()->create();
    $comment->entry->moveToTrash();

    expect($comment->fresh()->entry)->not->toBeNull();
});

it('is pending by default', function () {
    $comment = Comment::factory()->create();

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and($comment->isPending())->toBeTrue()
        ->and($comment->isApproved())->toBeFalse();
});

it('moves between statuses', function () {
    $comment = Comment::factory()->create();

    expect($comment->approve()->fresh()->status)->toBe(CommentStatus::Approved)
        ->and($comment->unapprove()->fresh()->status)->toBe(CommentStatus::Pending)
        ->and($comment->markAsSpam()->fresh()->status)->toBe(CommentStatus::Spam)
        ->and($comment->moveToTrash()->fresh()->status)->toBe(CommentStatus::Trash);
});

it('does not save when the status stays the same', function () {
    $comment = Comment::factory()->approved()->create(['updated_at' => now()->subDay()]);
    $before = $comment->updated_at->toIso8601String();

    $comment->approve();

    expect($comment->fresh()->updated_at->toIso8601String())->toBe($before);
});

it('can only be deleted from spam or trash', function () {
    expect(Comment::factory()->make()->canBeDeleted())->toBeFalse()
        ->and(Comment::factory()->approved()->make()->canBeDeleted())->toBeFalse()
        ->and(Comment::factory()->spam()->make()->canBeDeleted())->toBeTrue()
        ->and(Comment::factory()->trashed()->make()->canBeDeleted())->toBeTrue();
});

it('knows its reply depth', function () {
    $top = Comment::factory()->approved()->create();
    $reply = Comment::factory()->approved()->replyTo($top)->create();
    $deeper = Comment::factory()->approved()->replyTo($reply)->create();

    expect($top->depth())->toBe(1)
        ->and($reply->depth())->toBe(2)
        ->and($deeper->depth())->toBe(3);
});

it('filters by scopes', function () {
    Comment::factory()->approved()->count(2)->create();
    Comment::factory()->create();
    Comment::factory()->spam()->create();
    Comment::factory()->trashed()->create();

    expect(Comment::query()->approved()->count())->toBe(2)
        ->and(Comment::query()->active()->count())->toBe(3)
        ->and(Comment::query()->withStatus(CommentStatus::Spam)->count())->toBe(1);
});

it('counts comments by status', function () {
    Comment::factory()->approved()->count(3)->create();
    Comment::factory()->spam()->create();

    expect(Comment::countsByStatus())->toBe(['pending' => 0, 'approved' => 3, 'spam' => 1, 'trash' => 0]);
});

it('makes a short excerpt on one line', function () {
    $comment = Comment::factory()->make(['body' => "We loved the trip.\n\nThe train along the Douro river was the best part of the whole week."]);

    expect($comment->excerpt(30))->toBe('We loved the trip. The train a...');
});

it('escapes the body and keeps line breaks', function () {
    $comment = Comment::factory()->make(['body' => "Great post!\n<script>alert('hi')</script>"]);

    expect($comment->bodyHtml())->toBe("Great post!<br>\n&lt;script&gt;alert(&#039;hi&#039;)&lt;/script&gt;");
});

it('only keeps http and https author links', function () {
    expect(Comment::factory()->make(['author_url' => 'https://lisbon-cycling.example'])->safeAuthorUrl())->toBe('https://lisbon-cycling.example')
        ->and(Comment::factory()->make(['author_url' => 'javascript:alert(1)'])->safeAuthorUrl())->toBeNull()
        ->and(Comment::factory()->make(['author_url' => null])->safeAuthorUrl())->toBeNull();
});

it('adds a comments relation to entries', function () {
    $entry = Entry::factory()->published()->create();
    Comment::factory()->on($entry)->count(2)->create();

    expect($entry->comments)->toHaveCount(2);
});

it('is removed with its entry and keeps replies when a parent goes', function () {
    $parent = Comment::factory()->approved()->create();
    $reply = Comment::factory()->replyTo($parent)->create();

    $parent->delete();
    expect($reply->fresh()->parent_id)->toBeNull();

    $reply->entry->forceDelete();
    expect(Comment::query()->count())->toBe(0);
});

<?php

use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\CommentThread;
use App\Verso\Content\Models\Entry;

it('builds a tree of approved comments', function () {
    $entry = Entry::factory()->published()->create();
    $first = Comment::factory()->on($entry)->approved()->create(['created_at' => now()->subHours(3)]);
    $second = Comment::factory()->on($entry)->approved()->create(['created_at' => now()->subHours(2)]);
    $reply = Comment::factory()->replyTo($first)->approved()->create(['created_at' => now()->subHour()]);
    $nested = Comment::factory()->replyTo($reply)->approved()->create();
    Comment::factory()->replyTo($first)->create();
    Comment::factory()->approved()->create();

    $tree = app(CommentThread::class)->forEntry($entry);

    expect($tree->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($tree[0]->replies->pluck('id')->all())->toBe([$reply->id])
        ->and($tree[0]->replies[0]->replies->pluck('id')->all())->toBe([$nested->id])
        ->and($tree[1]->replies)->toBeEmpty();
});

it('shows replies of hidden parents as top comments', function () {
    $entry = Entry::factory()->published()->create();
    $spam = Comment::factory()->on($entry)->spam()->create();
    $reply = Comment::factory()->replyTo($spam)->approved()->create();

    $tree = app(CommentThread::class)->forEntry($entry);

    expect($tree->pluck('id')->all())->toBe([$reply->id]);
});

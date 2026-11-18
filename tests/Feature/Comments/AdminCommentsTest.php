<?php

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create();
    $this->entry = Entry::factory()->published()->create(['title' => 'Our First Month with Solar Panels']);
});

it('lists pending and approved comments with counts', function () {
    Comment::factory()->on($this->entry)->approved()->create(['author_name' => 'Paula Reis', 'created_at' => now()->subDay()]);
    Comment::factory()->on($this->entry)->create(['author_name' => 'Jorge Lima']);
    Comment::factory()->on($this->entry)->spam()->create();
    Comment::factory()->on($this->entry)->trashed()->count(2)->create();

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/comments/index')
            ->has('comments.data', 2)
            ->where('comments.data.0.author_name', 'Jorge Lima')
            ->where('comments.data.0.status', 'pending')
            ->where('comments.data.0.entry.title', 'Our First Month with Solar Panels')
            ->where('comments.data.0.entry.url', $this->entry->url())
            ->where('counts', ['all' => 2, 'pending' => 1, 'approved' => 1, 'spam' => 1, 'trash' => 2])
            ->where('filters.status', null)
        );
});

it('filters by status', function () {
    Comment::factory()->on($this->entry)->spam()->count(3)->create();
    Comment::factory()->on($this->entry)->create();

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index', ['status' => 'spam']))
        ->assertInertia(fn (Assert $page) => $page->has('comments.data', 3)->where('filters.status', 'spam'));
});

it('searches body, name and email', function () {
    Comment::factory()->on($this->entry)->create(['body' => 'How many panels did you install?', 'author_name' => 'Rafa']);
    Comment::factory()->on($this->entry)->create(['body' => 'Nice roof!', 'author_email' => 'lucas@greenhome.example']);
    Comment::factory()->on($this->entry)->create(['body' => 'Thanks for writing this.', 'author_name' => 'Vera']);

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index', ['search' => 'panels']))
        ->assertInertia(fn (Assert $page) => $page->has('comments.data', 1));

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index', ['search' => 'greenhome']))
        ->assertInertia(fn (Assert $page) => $page->has('comments.data', 1));

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index', ['search' => 'PANELS']))
        ->assertInertia(fn (Assert $page) => $page->has('comments.data', 1));
});

it('hides links to entries that are not live', function () {
    $draft = Entry::factory()->create();
    Comment::factory()->on($draft)->create();

    $this->actingAs($this->editor)
        ->get(route('admin.comments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('comments.data.0.entry.url', null));
});

it('approves, spams and trashes in bulk', function (string $action, CommentStatus $status) {
    $comments = Comment::factory()->on($this->entry)->count(2)->create();
    $other = Comment::factory()->on($this->entry)->create();

    $this->actingAs($this->editor)
        ->post(route('admin.comments.bulk'), ['action' => $action, 'ids' => $comments->pluck('id')->all()])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect($comments->every(fn (Comment $comment) => $comment->fresh()->status === $status))->toBeTrue()
        ->and($other->fresh()->status)->toBe(CommentStatus::Pending);
})->with([
    'approve' => ['approve', CommentStatus::Approved],
    'spam' => ['spam', CommentStatus::Spam],
    'trash' => ['trash', CommentStatus::Trash],
]);

it('unapproves and restores', function () {
    $approved = Comment::factory()->on($this->entry)->approved()->create();
    $trashed = Comment::factory()->on($this->entry)->trashed()->create();

    $this->actingAs($this->editor)->post(route('admin.comments.bulk'), ['action' => 'unapprove', 'ids' => [$approved->id]]);
    $this->actingAs($this->editor)
        ->post(route('admin.comments.bulk'), ['action' => 'restore', 'ids' => [$trashed->id]])
        ->assertInertiaFlash('toast.message', '1 comment restored.');

    expect($approved->fresh()->status)->toBe(CommentStatus::Pending)
        ->and($trashed->fresh()->status)->toBe(CommentStatus::Pending);
});

it('deletes for good only from spam and trash', function () {
    $spam = Comment::factory()->on($this->entry)->spam()->create();
    $live = Comment::factory()->on($this->entry)->approved()->create();

    $this->actingAs($this->editor)
        ->post(route('admin.comments.bulk'), ['action' => 'delete', 'ids' => [$spam->id, $live->id]])
        ->assertInertiaFlash('toast.message', '1 comment deleted.');

    expect(Comment::query()->pluck('id')->all())->toBe([$live->id]);
});

it('validates bulk requests', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.comments.bulk'), ['action' => 'publish', 'ids' => []])
        ->assertSessionHasErrors(['action', 'ids']);

    config()->set('verso.admin.per_page', 2);
    $this->actingAs($this->editor)
        ->post(route('admin.comments.bulk'), ['action' => 'approve', 'ids' => [1, 2, 3]])
        ->assertSessionHasErrors('ids');
});

it('forbids authors and below', function () {
    $author = User::factory()->author()->create();
    $comment = Comment::factory()->on($this->entry)->create();

    $this->actingAs($author)->get(route('admin.comments.index'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.comments.bulk'), ['action' => 'approve', 'ids' => [$comment->id]])->assertForbidden();

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending);
});

it('shows the nav item and the pending count on the dashboard', function () {
    Comment::factory()->on($this->entry)->count(3)->create();

    $this->actingAs($this->editor)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'comments'))
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'comments.pending')['value'] === 3)
        );
});

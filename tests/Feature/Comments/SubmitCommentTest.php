<?php

use App\Models\User;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('verso-comments');
    $this->entry = Entry::factory()->published()->create(['title' => 'The Best Coffee Shops in Lisbon', 'slug' => 'best-coffee-lisbon']);
    $this->token = app(FormTimer::class)->token();
    $this->travel(10)->seconds();
    $this->form = fn (array $overrides = []): array => [
        'author_name' => 'Helena Martins',
        'author_email' => 'helena@martins.example',
        'author_url' => 'https://helena-travels.example',
        'body' => 'You forgot the little kiosk in Príncipe Real, best espresso in town!',
        FormTimer::FIELD => $this->token,
        ...$overrides,
    ];
});

it('stores a guest comment as pending and goes back to the form', function () {
    $this->post(route('comments.store', $this->entry), ($this->form)())
        ->assertRedirect($this->entry->url().'#respond')
        ->assertSessionHas('verso_comment', ['status' => 'pending', 'message' => 'Thanks! Your comment is waiting for moderation.']);

    $comment = Comment::query()->sole();
    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and($comment->entry_id)->toBe($this->entry->id)
        ->and($comment->author_name)->toBe('Helena Martins')
        ->and($comment->ip_hash)->not->toBeNull();
});

it('approves comments from signed in users and jumps to them', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post(route('comments.store', $this->entry), ['body' => 'Agree about the kiosk.', FormTimer::FIELD => $this->token])
        ->assertRedirect($this->entry->url().'#comment-'.Comment::query()->sole()->id)
        ->assertSessionHas('verso_comment.status', 'approved');

    expect(Comment::query()->sole()->user_id)->toBe($member->id);
});

it('answers with json when asked', function () {
    $this->postJson(route('comments.store', $this->entry), ($this->form)())
        ->assertCreated()
        ->assertJson(['status' => 'pending', 'id' => null]);
});

it('validates the fields', function () {
    $this->post(route('comments.store', $this->entry), ($this->form)([
        'author_name' => '',
        'author_email' => 'not-an-email',
        'author_url' => 'javascript:alert(1)',
        'body' => '',
    ]))->assertSessionHasErrorsIn('comment', ['author_name', 'author_email', 'author_url', 'body']);

    expect(Comment::query()->count())->toBe(0);
});

it('limits the comment length from config', function () {
    config()->set('verso.comments.max_length', 20);

    $this->post(route('comments.store', $this->entry), ($this->form)(['body' => str_repeat('Lovely place. ', 5)]))
        ->assertSessionHasErrorsIn('comment', ['body']);
});

it('silently drops comments that fill the honeypot', function () {
    $this->post(route('comments.store', $this->entry), ($this->form)([config('verso.comments.honeypot_field') => 'https://bot.example']))
        ->assertRedirect($this->entry->url().'#respond')
        ->assertSessionHas('verso_comment.status', 'pending');

    expect(Comment::query()->count())->toBe(0);
});

it('rejects forms sent too fast', function () {
    $fresh = app(FormTimer::class)->token();

    $this->post(route('comments.store', $this->entry), ($this->form)([FormTimer::FIELD => $fresh]))
        ->assertSessionHasErrorsIn('comment', ['form']);

    expect(Comment::query()->count())->toBe(0);
});

it('rejects forms without a valid time token', function () {
    $this->post(route('comments.store', $this->entry), ($this->form)([FormTimer::FIELD => 'forged']))
        ->assertSessionHasErrorsIn('comment', ['form']);

    $this->travel(2)->days();
    $this->post(route('comments.store', $this->entry), ($this->form)())
        ->assertSessionHasErrorsIn('comment', ['form']);
});

it('puts link heavy comments in spam', function () {
    $this->post(route('comments.store', $this->entry), ($this->form)([
        'body' => 'Great https://a.example https://b.example https://c.example',
    ]))->assertSessionHas('verso_comment.status', 'pending');

    expect(Comment::query()->sole()->status)->toBe(CommentStatus::Spam);
});

it('throttles visitors using the config', function () {
    config()->set('verso.comments.throttle_attempts', 2);

    $this->post(route('comments.store', $this->entry), ($this->form)())->assertRedirect();
    $this->post(route('comments.store', $this->entry), ($this->form)())->assertRedirect();
    $this->post(route('comments.store', $this->entry), ($this->form)())->assertTooManyRequests();

    expect(Comment::query()->count())->toBe(2);
});

it('refuses comments when the site turns them off', function () {
    app(Settings::class)->set('comments.enabled', false);

    $this->post(route('comments.store', $this->entry), ($this->form)())->assertForbidden();
});

it('refuses comments on closed entries', function () {
    $this->entry->update(['meta' => ['comments_open' => false]]);

    $this->post(route('comments.store', $this->entry), ($this->form)())->assertForbidden();
});

it('refuses comments on drafts and returns 404 for missing entries', function () {
    $draft = Entry::factory()->create();

    $this->post(route('comments.store', $draft), ($this->form)())->assertForbidden();
    $this->post('/comments/999999', ($this->form)())->assertNotFound();
});

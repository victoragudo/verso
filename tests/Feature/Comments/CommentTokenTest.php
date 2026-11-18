<?php

use App\Verso\Comments\Models\Comment;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('verso-comments');
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $this->entry = Entry::factory()->published()->create(['title' => 'Night Trains Across the Alps', 'slug' => 'night-trains-alps']);
});

// Turn the CSRF check on, the way it runs outside of tests.
function enforceCsrf(): void
{
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app->make('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

function commentForm(string $csrfToken, string $timer): array
{
    return [
        '_token' => $csrfToken,
        'author_name' => 'Lena Hofer',
        'author_email' => 'lena@hofer.example',
        'body' => 'The sleeper from Zurich to Vienna is worth every cent.',
        FormTimer::FIELD => $timer,
    ];
}

it('returns the session csrf token without caching it', function () {
    $response = $this->getJson(route('comments.token'))->assertOk();

    expect($response->json('token'))->toBe(session()->token())
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get(PageCache::HEADER))->toBeNull();
});

it('gives each visitor their own token', function () {
    $first = $this->getJson(route('comments.token'))->json('token');

    $this->flushSession();

    expect($this->getJson(route('comments.token'))->json('token'))->not->toBe($first);
});

it('adds the fresh token script and url to the comment form', function () {
    $this->get('/blog/night-trains-alps')
        ->assertOk()
        ->assertSee('id="verso-comment-form"', false)
        ->assertSee('data-token-url="'.route('comments.token').'"', false)
        ->assertSee("form.querySelector('input[name=\"_token\"]')", false);
});

it('accepts a comment sent with a fresh token from a cached page', function () {
    enforceCsrf();
    $this->get('/blog/night-trains-alps')->assertHeader(PageCache::HEADER, 'MISS');
    $this->flushSession();

    $this->get('/blog/night-trains-alps')->assertHeader(PageCache::HEADER, 'HIT');
    $fresh = $this->getJson(route('comments.token'))->json('token');
    $timer = app(FormTimer::class)->token();
    $this->travel(10)->seconds();

    $this->post(route('comments.store', $this->entry), commentForm($fresh, $timer))
        ->assertRedirect($this->entry->url().'#respond');

    expect(Comment::query()->sole()->author_name)->toBe('Lena Hofer');
});

it('still refuses a comment sent with a stale token', function () {
    enforceCsrf();
    $stale = $this->getJson(route('comments.token'))->json('token');
    $this->flushSession();
    $this->getJson(route('comments.token'));
    $timer = app(FormTimer::class)->token();
    $this->travel(10)->seconds();

    $this->post(route('comments.store', $this->entry), commentForm($stale, $timer))->assertStatus(419);

    expect(Comment::query()->count())->toBe(0);
});

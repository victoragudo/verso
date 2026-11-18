<?php

use App\Models\User;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-11-17 12:00:00');
    app(Settings::class)->set('site.timezone', 'Europe/Madrid');
    $this->editor = User::factory()->editor()->create(['name' => 'Elena Sanz']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shares the site time zone with the admin', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('siteTimezone', 'Europe/Madrid'));
});

it('stores a scheduled date picked in Madrid time as UTC', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Winter market opens in Plaza Mayor',
        'status' => 'scheduled',
        'published_at' => '2026-12-01T09:30',
    ])->assertSessionHasNoErrors();

    $entry = Entry::firstWhere('slug', 'winter-market-opens-in-plaza-mayor');

    expect($entry->status)->toBe(EntryStatus::Scheduled)
        ->and($entry->getRawOriginal('published_at'))->toBe('2026-12-01 08:30:00');
});

it('stores a summer date with the summer offset', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Night swimming at the Retiro pools',
        'status' => 'scheduled',
        'published_at' => '2027-07-10T21:00',
    ])->assertSessionHasNoErrors();

    expect(Entry::firstWhere('slug', 'night-swimming-at-the-retiro-pools')->getRawOriginal('published_at'))->toBe('2027-07-10 19:00:00');
});

it('sends dates to the editor in UTC so the browser can show them in the site zone', function () {
    $entry = Entry::factory()->create(['title' => 'Chestnut season in Madrid', 'published_at' => '2026-12-01 08:30:00']);

    $this->actingAs($this->editor)
        ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]))
        ->assertInertia(fn (Assert $page) => $page->where('entry.published_at', '2026-12-01T08:30:00+00:00'));
});

it('checks scheduled dates in the past using the site time zone', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Lunch plans for today',
        'status' => 'scheduled',
        'published_at' => '2026-11-17T12:30',
    ])->assertSessionHasErrors(['published_at' => 'The scheduled date must be in the future.']);
});

it('keeps an update in the site time zone', function () {
    $entry = Entry::factory()->create(['title' => 'Book fair in the Retiro park']);

    $this->actingAs($this->editor)->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $entry->id]), [
        'title' => 'Book fair in the Retiro park',
        'status' => 'scheduled',
        'published_at' => '2026-12-24T18:00',
    ])->assertSessionHasNoErrors();

    expect($entry->fresh()->getRawOriginal('published_at'))->toBe('2026-12-24 17:00:00');
});

it('shows post dates in the site time zone on the public site', function () {
    $entry = Entry::factory()->published()->create([
        'title' => 'Late train from Valencia',
        'published_at' => '2026-11-14 23:30:00',
    ]);

    $this->get($entry->path())
        ->assertOk()
        ->assertSee('<time datetime="2026-11-15T00:30:00+01:00">November 15, 2026</time>', false)
        ->assertSee('content="2026-11-15T00:30:00+01:00"', false);
});

it('writes feed dates with the site offset', function () {
    Entry::factory()->published()->create(['title' => 'Late train from Valencia', 'published_at' => '2026-11-14 23:30:00']);

    $this->get('/feed')
        ->assertOk()
        ->assertSee('<pubDate>Sun, 15 Nov 2026 00:30:00 +0100</pubDate>', false);
});

it('writes sitemap lastmod in ISO 8601 with the site offset', function () {
    Carbon::setTestNow('2026-07-01 10:00:00');
    Entry::factory()->published()->create(['title' => 'Summer in Cadiz', 'published_at' => '2026-06-30 08:00:00']);

    $this->get('/sitemap-post.xml')->assertOk()->assertSee('<lastmod>2026-07-01T12:00:00+02:00</lastmod>', false);
    $this->get('/sitemap.xml')->assertOk()->assertSee('<lastmod>2026-07-01T12:00:00+02:00</lastmod>', false);
});

it('shows comment dates in the site time zone', function () {
    $entry = Entry::factory()->published()->create(['title' => 'Late train from Valencia', 'published_at' => '2026-11-10 10:00:00']);
    $comment = Comment::factory()->on($entry)->approved()->create(['author_name' => 'Nuria Pons']);
    $comment->forceFill(['created_at' => '2026-11-14 23:30:00'])->save();

    $this->get($entry->path())->assertSee('datetime="2026-11-15T00:30:00+01:00"', false);
});

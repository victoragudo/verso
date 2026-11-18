<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $this->directory = storage_path('framework/testing/export-'.uniqid());
    $this->author = User::factory()->author()->create(['name' => 'Elena Castro']);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('writes every public page as a static file', function () {
    $post = Entry::factory()->published()->by($this->author)->create(['title' => 'A Slow Weekend in Porto', 'slug' => 'slow-weekend-porto']);
    $about = Entry::factory()->page()->published()->by($this->author)->create(['title' => 'About Us', 'slug' => 'about']);
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Our Team', 'slug' => 'team', 'parent_id' => $about->id]);
    Entry::factory()->by($this->author)->create(['title' => 'Unpublished Draft', 'slug' => 'unpublished-draft']);
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    $post->terms()->attach($travel);

    $this->artisan('verso:export', ['path' => $this->directory])
        ->expectsOutputToContain('files written to')
        ->assertSuccessful();

    foreach (['index.html', 'blog/index.html', 'blog/slow-weekend-porto/index.html', 'about/index.html', 'about/team/index.html', 'category/travel/index.html', 'feed.xml', '404.html'] as $file) {
        expect(File::exists($this->directory.'/'.$file))->toBeTrue("Missing {$file}");
    }

    expect(File::exists($this->directory.'/blog/unpublished-draft/index.html'))->toBeFalse()
        ->and(File::get($this->directory.'/about/team/index.html'))->toContain('Our Team')
        ->and(File::get($this->directory.'/404.html'))->toContain('This page could not be found')
        ->and(File::get($this->directory.'/feed.xml'))->toContain('<rss version="2.0"');
});

it('writes list pages as folders and rewrites their links', function () {
    app(Settings::class)->set('reading.posts_per_page', 1);
    Entry::factory()->published()->by($this->author)->create(['title' => 'Newest Notes on Bread', 'published_at' => now()->subDay()]);
    Entry::factory()->published()->by($this->author)->create(['title' => 'Oldest Notes on Coffee', 'published_at' => now()->subWeek()]);

    $this->artisan('verso:export', ['path' => $this->directory])->assertSuccessful();

    expect(File::get($this->directory.'/blog/page/2/index.html'))->toContain('Oldest Notes on Coffee')
        ->and(File::get($this->directory.'/blog/index.html'))->toContain('href="'.url('/blog').'/page/2/"')
        ->and(File::get($this->directory.'/blog/index.html'))->not->toContain('?page=2')
        ->and(File::get($this->directory.'/page/2/index.html'))->toContain('Oldest Notes on Coffee');
});

it('uses the given public URL in links', function () {
    Entry::factory()->published()->by($this->author)->create(['slug' => 'harbour-walk']);

    $this->artisan('verso:export', ['path' => $this->directory, '--url' => 'https://journal.example.org'])->assertSuccessful();

    expect(File::get($this->directory.'/index.html'))->toContain('https://journal.example.org/blog/harbour-walk');
});

it('rejects an invalid public URL', function () {
    $this->artisan('verso:export', ['path' => $this->directory, '--url' => 'not a url'])->assertFailed();

    expect(File::isDirectory($this->directory))->toBeFalse();
});

it('uses the configured folder by default', function () {
    config(['verso.export.path' => $this->directory]);

    $this->artisan('verso:export')->assertSuccessful();

    expect(File::exists($this->directory.'/index.html'))->toBeTrue();
});

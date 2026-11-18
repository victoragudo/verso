<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\Cache\PageCache;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\QueryBudget;

// Extra published posts with a featured image, an image block and a category, like a busy blog.
function addBusyPosts(int $count): void
{
    $author = User::firstWhere('email', 'clara@verso.test');
    $category = Term::inTaxonomy('category')->firstWhere('slug', 'food');
    $photos = Media::query()->pluck('id')->all();
    $titles = ['Sourdough at high altitude', 'Ten markets in Valencia', 'Slow mornings in Kyoto', 'Fermenting hot sauce', 'A week of tapas', 'Olive harvest in Jaén'];

    for ($i = 0; $i < $count; $i++) {
        $photo = $photos[$i % count($photos)];
        $entry = Entry::factory()->published()->by($author)->create([
            'title' => $titles[$i % count($titles)].' part '.($i + 1),
            'published_at' => now()->subDays(30 + $i),
            'meta' => ['featured_image' => ['mediaId' => $photo]],
            'blocks' => [
                ['id' => 'b_img'.$i, 'type' => 'image', 'attrs' => ['mediaId' => $photo, 'alt' => 'Market stall'], 'content' => '', 'children' => []],
                ['id' => 'b_par'.$i, 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>We arrived before the stalls opened.</p>', 'children' => []],
            ],
        ]);
        $entry->terms()->attach($category);
    }
}

// Queries each key page runs, by budget name.
function pageQueryCounts(object $test): array
{
    $post = Entry::firstWhere('slug', 'cooking-paella-for-twelve-people');
    $admin = User::firstWhere('email', 'admin@verso.test');

    $public = [
        'home' => '/',
        'entry' => $post->path(),
        'page' => '/about/our-team',
        'archive' => '/blog',
        'term' => '/category/food',
        'feed' => '/feed',
        'sitemap' => '/sitemap.xml',
    ];

    $counts = [];

    // The first visit fills the menu and sitemap caches. Budgets are for the visits after it.
    foreach ($public as $name => $path) {
        $test->get($path)->assertOk();
        $counts[$name] = QueryBudget::count(fn () => $test->get($path)->assertOk());
    }

    $test->actingAs($admin);
    $counts['admin.entries'] = QueryBudget::count(fn () => $test->get('/admin/entries/post')->assertOk());
    $counts['admin.dashboard'] = QueryBudget::count(fn () => $test->get('/admin')->assertOk());
    auth()->logout();

    return $counts;
}

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
    config(['verso.page_cache.enabled' => false]);
    app()->forgetInstance(PageCache::class);
});

it('keeps every key page within its query budget', function () {
    addBusyPosts(20);

    foreach (pageQueryCounts($this) as $page => $count) {
        expect($count)->toBeLessThanOrEqual(QueryBudget::for($page), "The {$page} page ran {$count} queries.");
    }
});

it('runs the same number of queries with many more entries', function () {
    $before = pageQueryCounts($this);

    addBusyPosts(25);

    expect(pageQueryCounts($this))->toBe($before);
});

it('serves a cached page with almost no queries', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $this->get('/')->assertHeader(PageCache::HEADER, 'MISS');

    $count = QueryBudget::count(fn () => $this->get('/')->assertHeader(PageCache::HEADER, 'HIT'));

    expect($count)->toBeLessThanOrEqual(QueryBudget::CACHED_PAGE);
});

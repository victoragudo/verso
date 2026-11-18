<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Seo\Models\NotFoundHit;
use App\Verso\Seo\NotFound\NotFoundMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

it('counts a public 404 without storing the IP address', function () {
    $this->get('/blog/riding-tram-28-old', ['Referer' => 'https://news.example.org/lisbon/trams?ref=123', 'REMOTE_ADDR' => '203.0.113.7'])->assertNotFound();
    $this->get('/blog/riding-tram-28-old')->assertNotFound();

    $hit = NotFoundHit::query()->sole();

    expect($hit->path)->toBe('/blog/riding-tram-28-old')
        ->and($hit->hits)->toBe(2)
        ->and($hit->referrer_host)->toBe('news.example.org')
        ->and($hit->first_seen_at)->not->toBeNull()
        ->and(json_encode($hit->getAttributes()))->not->toContain('203.0.113.7');
});

it('does not count pages that exist, admin paths, ignored paths or other methods', function () {
    $post = Entry::factory()->published()->create(['slug' => 'harbor-walk']);

    $this->get($post->path())->assertOk();
    $this->get('/wp-login.php')->assertNotFound();
    $this->get('/.env')->assertNotFound();
    $this->post('/missing-form-target');
    $this->getJson('/api-that-is-missing')->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get('/admin/not-a-page')->assertNotFound();

    expect(NotFoundHit::query()->count())->toBe(0);
});

it('can be turned off', function () {
    config(['verso.seo.not_found.enabled' => false]);

    $this->get('/old-menu-2023')->assertNotFound();

    expect(NotFoundHit::query()->count())->toBe(0);
});

it('limits hits per visitor and for the whole site', function () {
    config(['verso.seo.not_found.per_visitor_per_minute' => 2, 'verso.seo.not_found.per_minute' => 3]);

    foreach (range(1, 4) as $number) {
        $this->get('/scan-'.$number, ['REMOTE_ADDR' => '198.51.100.20']);
    }
    foreach (range(1, 4) as $number) {
        $this->get('/other-'.$number, ['REMOTE_ADDR' => '198.51.100.'.(30 + $number)]);
    }

    expect(NotFoundHit::query()->count())->toBe(3)
        ->and(NotFoundHit::query()->where('path', 'like', '/scan-%')->count())->toBe(2);
});

it('stops adding new addresses when the list is full, but still counts known ones', function () {
    config(['verso.seo.not_found.max_rows' => 1]);

    $this->get('/first-missing-page');
    $this->get('/second-missing-page');
    $this->get('/first-missing-page');

    expect(NotFoundHit::query()->pluck('hits', 'path')->all())->toBe(['/first-missing-page' => 2]);
});

it('cuts very long paths', function () {
    config(['verso.seo.not_found.path_max_length' => 20]);

    $this->get('/'.str_repeat('pastel-de-nata-', 10));

    expect(mb_strlen(NotFoundHit::query()->sole()->path))->toBe(20);
});

it('prunes addresses older than the retention time', function () {
    config(['verso.seo.not_found.retention_days' => 30]);
    NotFoundHit::query()->create(['path' => '/old-campaign', 'hits' => 4, 'first_seen_at' => Carbon::now()->subDays(60), 'last_seen_at' => Carbon::now()->subDays(31)]);
    NotFoundHit::query()->create(['path' => '/recent-typo', 'hits' => 1, 'first_seen_at' => Carbon::now()->subDays(2), 'last_seen_at' => Carbon::now()->subDay()]);

    Artisan::call('verso:seo-prune-404');

    expect(NotFoundHit::query()->pluck('path')->all())->toBe(['/recent-typo']);
});

it('matches ignore patterns with and without a leading slash', function () {
    $monitor = app(NotFoundMonitor::class);

    expect($monitor->ignored('/wp-admin/install.php'))->toBeTrue()
        ->and($monitor->ignored('/apple-touch-icon-precomposed.png'))->toBeTrue()
        ->and($monitor->ignored('/blog/menu-de-verano'))->toBeFalse()
        ->and($monitor->normalize('blog/Menu-de-Verano/'))->toBe('/blog/Menu-de-Verano');
});

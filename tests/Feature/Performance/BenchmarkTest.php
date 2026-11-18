<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Site\Models\Menu;
use App\Verso\Theme\Benchmark\BenchmarkSeeder;
use App\Verso\Theme\Benchmark\RequestTimer;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\DB;

describe('seeder', function () {
    it('seeds published posts with terms, nested pages and a primary menu', function () {
        $paths = app(BenchmarkSeeder::class)->seed(25);

        expect(Entry::ofType('post')->published()->count())->toBe(25)
            ->and(Entry::ofType('page')->published()->count())->toBe(2)
            ->and(DB::table('entry_term')->count())->toBe(75)
            ->and(Menu::forLocation('primary')?->items()->count())->toBe(3)
            ->and($paths)->toHaveKeys(['home', 'entry', 'archive', 'term', 'page', 'feed']);
    });

    it('returns paths that answer 200', function () {
        $paths = app(BenchmarkSeeder::class)->seed(12);

        foreach ($paths as $path) {
            $this->get($path)->assertOk();
        }
    });
});

describe('request timer', function () {
    beforeEach(function () {
        $this->paths = app(BenchmarkSeeder::class)->seed(15);
        $this->timer = app(RequestTimer::class);
    });

    it('times requests through the HTTP kernel', function () {
        $this->timer->usePageCache(false);

        $samples = $this->timer->measure($this->paths['home'], requests: 4, warmup: 1);

        expect($samples->count())->toBe(4)
            ->and($samples->percentile(50))->toBeGreaterThan(0.0);
    });

    it('checks that cached measurements are hits', function () {
        $this->timer->usePageCache(true);

        $samples = $this->timer->measure($this->paths['entry'], requests: 3, warmup: 1, expectedCacheHeader: 'HIT');

        expect($samples->count())->toBe(3);
    });

    it('fails when a cached measurement is not a hit', function () {
        $this->timer->usePageCache(true);

        $this->timer->measure($this->paths['feed'], requests: 1, warmup: 0, expectedCacheHeader: 'HIT');
    })->throws(RuntimeException::class, 'was not served from the page cache');

    it('fails when a page does not answer 200', function () {
        $this->timer->send('/blog/a-post-that-was-never-written');
    })->throws(RuntimeException::class, 'answered 404');

    it('turns the page cache on and off', function () {
        $this->timer->usePageCache(false);
        expect(app(PageCache::class)->enabled())->toBeFalse();

        $this->timer->usePageCache(true);
        expect(app(PageCache::class)->enabled())->toBeTrue();
    });
});

describe('command', function () {
    it('refuses to run the worker on the real database', function () {
        putenv('VERSO_BENCHMARK_DATABASE');

        $this->artisan('verso:benchmark', ['--worker' => true])
            ->expectsOutputToContain('only runs on a temporary benchmark database')
            ->assertExitCode(1);
    });

    it('refuses a worker database that is not the one in use', function () {
        $file = storage_path('framework/verso-benchmark-unrelated.sqlite');
        touch($file);
        putenv('VERSO_BENCHMARK_DATABASE='.$file);

        try {
            $this->artisan('verso:benchmark', ['--worker' => true])->assertExitCode(1);
        } finally {
            putenv('VERSO_BENCHMARK_DATABASE');
            unlink($file);
        }
    });

    it('seeds a temporary database, prints a table and removes the database', function () {
        // This test checks how the command works, not how fast the machine is, so budgets are loose.
        config([
            'verso.benchmark.cached_budget_ms' => 60000.0,
            'verso.benchmark.uncached_budget_ms' => 60000.0,
        ]);
        $before = glob(storage_path('framework/verso-benchmark-*')) ?: [];

        $this->artisan('verso:benchmark', ['--entries' => 30, '--requests' => 3, '--warmup' => 1])
            ->expectsOutputToContain('Seeding 30 posts')
            ->expectsOutputToContain('Every route is within budget at p95.')
            ->assertExitCode(0);

        expect(glob(storage_path('framework/verso-benchmark-*')) ?: [])->toBe($before)
            ->and(Entry::query()->count())->toBe(0);
    });

    it('fails when the budgets are impossible to meet', function () {
        config(['verso.benchmark.cached_budget_ms' => 0.0001]);

        $this->artisan('verso:benchmark', ['--entries' => 10, '--requests' => 2, '--warmup' => 1])
            ->expectsOutputToContain('over budget at p95')
            ->assertExitCode(1);
    });

});

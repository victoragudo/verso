<?php

use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\ThemeVerifier;
use App\Verso\Extensions\Verification\VerificationReport;
use Tests\Support\ExtensionSandbox;
use Tests\Support\ThemeFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => false]);

    $this->verify = fn (string $path, bool $withSandbox = false): VerificationReport => app(ThemeVerifier::class)->verify($path, $withSandbox);

    // Rules of the findings, without the information ones.
    $this->rules = fn (VerificationReport $report): array => array_values(array_unique(array_map(
        fn (Finding $finding): string => $finding->rule,
        array_filter($report->findings, fn (Finding $finding): bool => $finding->severity->value !== 'info'),
    )));
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox->destroy();
});

it('gives a complete theme with a screenshot a low risk and nothing that blocks the install', function () {
    $report = ($this->verify)(ThemeFixtures::withScreenshot('harbor-light'));

    expect($report->slug)->toBe('harbor-light')
        ->and($report->version)->toBe('1.2.0')
        ->and($report->risk->value)->toBe('low')
        ->and(($this->rules)($report))->toBe([])
        ->and($report->details['blocking'])->toBe([])
        ->and($report->details['name'])->toBe('Harbor Light')
        ->and($report->details['theme'])->toMatchArray([
            'parent' => null,
            'requires' => '^0.1',
            'php' => '^8.4',
            'screenshot' => 'screenshot.png',
            'network' => ['cdn.jsdelivr.net'],
            'quality' => ['seo' => true, 'head' => true, 'footer' => true, 'skipLink' => true, 'lang' => true],
        ])
        ->and($report->details['files'])->toBe(9);
});

it('lets a child theme use the required views and layout of its installed parent', function () {
    $report = ($this->verify)(ThemeFixtures::withScreenshot('saltmarsh'));

    expect(($this->rules)($report))->toBe([])
        ->and($report->details['theme']['parent'])->toBe('default')
        ->and($report->details['theme']['quality'])->toBe(['seo' => true, 'head' => true, 'footer' => true, 'skipLink' => true, 'lang' => true]);
});

it('blocks a theme whose parent is not installed and does not render it', function () {
    config(['verso.extensions.verification.sandbox.enabled' => true]);

    $report = ($this->verify)(ThemeFixtures::path('orphan-tide'), true);

    expect($report->risk->value)->toBe('high')
        ->and($report->details['blocking'])->toBe(['theme.parent-missing', 'theme.missing-view'])
        ->and($report->details['sandbox']['status'])->toBe('skipped')
        ->and(($this->rules)($report))->not->toContain('theme.no-seo')
        ->and($report->findings[0]->message)->toContain('seaside-base');
});

it('blocks a theme with missing required views and names each missing file', function () {
    $report = ($this->verify)(ThemeFixtures::path('half-built'));
    $missing = array_values(array_filter($report->findings, fn (Finding $finding): bool => $finding->rule === 'theme.missing-view'));

    expect($report->details['blocking'])->toBe(['theme.missing-view'])
        ->and(array_map(fn (Finding $finding): ?string => $finding->file, $missing))->toBe([
            'views/404.blade.php',
            'views/archive.blade.php',
            'views/search.blade.php',
            'views/taxonomy.blade.php',
        ]);
});

it('finds unsafe and low quality code in a risky theme with file and line', function () {
    $report = ($this->verify)(ThemeFixtures::path('driftwood'));
    $at = fn (string $rule): array => array_values(array_map(
        fn (Finding $finding): string => $finding->file.':'.$finding->line,
        array_filter($report->findings, fn (Finding $finding): bool => $finding->rule === $rule),
    ));

    expect($report->risk->value)->toBe('high')
        ->and($report->details['blocking'])->toBe([])
        ->and($at('theme.raw-user-output'))->toBe(['views/layouts/app.blade.php:10'])
        ->and($at('php.shell'))->toBe(['views/layouts/app.blade.php:17'])
        ->and($at('theme.inline-handler'))->toBe(['views/layouts/app.blade.php:11'])
        ->and($at('theme.external-script'))->toBe(['views/layouts/app.blade.php:6'])
        ->and($at('theme.render-blocking-script'))->toBe(['views/layouts/app.blade.php:6'])
        ->and($at('theme.external-form'))->toBe(['views/layouts/app.blade.php:12'])
        ->and($at('theme.image-size'))->toBe(['views/layouts/app.blade.php:15'])
        ->and($at('theme.no-lang'))->toBe(['views/layouts/app.blade.php:2'])
        ->and($at('theme.remote-font'))->toBe(['assets/theme.css:1', 'views/layouts/app.blade.php:5'])
        ->and($at('theme.external-style'))->toBe(['assets/theme.css:2'])
        ->and($at('theme.php-file'))->toBe(['src/Helpers.php:'])
        ->and(($this->rules)($report))->toContain('theme.no-head-hook', 'theme.no-footer-hook', 'theme.no-skip-link')
        ->and(($this->rules)($report))->not->toContain('theme.no-seo', 'blade.external-script', 'blade.raw-output')
        ->and($report->details['theme']['hosts'])->toContain('cdn.adnetwork-tracker.example', 'newsletter-capture.example');
});

it('reports a missing or broken theme.json as a blocking finding', function () {
    $path = ThemeFixtures::copy('harbor-light', ['theme.json' => '{"name": "Harbor Light", "version": ']);

    $report = ($this->verify)($path);

    expect($report->risk->value)->toBe('high')
        ->and($report->findings)->toHaveCount(1)
        ->and($report->findings[0]->rule)->toBe('theme.manifest-invalid')
        ->and($report->details['blocking'])->toBe(['theme.manifest-invalid']);
});

it('renders a complete theme in the sandbox with demo content', function () {
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.sandbox.timeout' => 60]);

    $report = ($this->verify)(ThemeFixtures::withScreenshot('harbor-light'), true);
    $pages = $report->details['sandbox']['pages'];

    expect($report->details['sandbox']['status'])->toBe('passed')
        ->and($report->details['blocking'])->toBe([])
        ->and(array_column($pages, 'name'))->toBe(['home', 'entry', 'archive'])
        ->and(array_column($pages, 'status'))->toBe([200, 200, 200])
        ->and($pages[1]['html'])->toContain('Slow travel through the Basque Country')
        ->and($pages[0]['html'])->toContain('Skip to content');
});

it('blocks a theme whose home page breaks in the sandbox and points to the view', function () {
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.sandbox.timeout' => 60]);

    $report = ($this->verify)(ThemeFixtures::path('broken-pier'), true);
    $failure = collect($report->findings)->firstWhere('rule', 'theme.render-failed');

    expect($report->details['blocking'])->toBe(['theme.render-failed'])
        ->and($failure?->file)->toBe('views/home.blade.php')
        ->and($failure?->message)->toContain('Undefined variable $featuredStory')
        ->and($failure?->message)->not->toContain(storage_path())
        ->and(array_column($report->details['sandbox']['pages'], 'status'))->toBe([500, 200, 200]);
});

it('passes the bundled themes with no blocking findings', function (string $slug) {
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.sandbox.timeout' => 60]);

    $report = ($this->verify)(base_path('themes/'.$slug), true);

    expect($report->details['blocking'])->toBe([])
        ->and($report->risk->value)->toBe('low')
        ->and($report->details['sandbox']['status'])->toBe('passed')
        ->and(array_column($report->details['sandbox']['pages'], 'status'))->toBe([200, 200, 200])
        ->and($report->details['theme']['quality'])->toBe(['seo' => true, 'head' => true, 'footer' => true, 'skipLink' => true, 'lang' => true])
        ->and(array_filter(array_column($report->findings, 'rule'), fn (string $rule): bool => str_contains($rule, 'screenshot')))->toBe([]);
})->with(['default', 'ashgrove']);

it('ships a screenshot of the home page within the limits for the bundled themes', function (string $slug) {
    $file = base_path("themes/{$slug}/screenshot.png");
    [$width, $height] = getimagesize($file) ?: [0, 0];

    expect(filesize($file))->toBeLessThanOrEqual((int) config('verso.extensions.verification.themes.screenshot_max_kb') * 1024)
        ->and($width)->toBe(1200)->toBeLessThanOrEqual((int) config('verso.extensions.verification.themes.screenshot_max_width'))
        ->and($height)->toBe(900)->toBeLessThanOrEqual((int) config('verso.extensions.verification.themes.screenshot_max_height'));
})->with(['default', 'ashgrove']);

it('keeps no preview HTML when the limit is zero', function () {
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.themes.preview_max_kb' => 0]);

    $report = ($this->verify)(ThemeFixtures::path('saltmarsh'), true);

    expect(array_column($report->details['sandbox']['pages'], 'html'))->toBe(['', '', ''])
        ->and($report->details['sandbox']['pages'][0]['bytes'])->toBeGreaterThan(1000);
});

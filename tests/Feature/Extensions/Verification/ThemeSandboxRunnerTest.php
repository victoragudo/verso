<?php

use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\ExtensionSandbox;
use Tests\Support\ThemeFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.sandbox.timeout' => 60]);
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox->destroy();
});

it('renders the home page, a post and the archive and leaves nothing behind', function () {
    $result = app(SandboxRunner::class)->runTheme(ThemeFixtures::path('harbor-light'), 'harbor-light');

    expect($result->status)->toBe(SandboxResult::PASSED)
        ->and($result->errors)->toBe([])
        ->and(array_column($result->pages, 'path'))->toBe(['/', '/blog/slow-travel-through-the-basque-country-part-1', '/blog'])
        ->and($result->pages[0]['html'])->toContain('<a class="skip-link" href="#content">')
        ->and($result->pages[2]['bytes'])->toBeGreaterThan(0)
        ->and(File::directories($this->sandbox->root.'/storage/sandbox'))->toBe([]);
});

it('copies the installed parent theme next to a child theme', function () {
    $default = app(ThemeManager::class)->find('default');

    $result = app(SandboxRunner::class)->runTheme(ThemeFixtures::path('saltmarsh'), 'saltmarsh', ['default' => $default->path]);

    expect($result->status)->toBe(SandboxResult::PASSED)
        ->and($result->pages[0]['html'])->toContain('class="page-title"');
});

it('writes the configured number of demo posts', function () {
    config(['verso.extensions.verification.themes.preview_posts' => 2]);

    $result = app(SandboxRunner::class)->runTheme(ThemeFixtures::path('harbor-light'), 'harbor-light');

    expect(substr_count($result->pages[2]['html'], '<article class="card">'))->toBe(2);
});

it('returns a skipped result when the sandbox is turned off', function () {
    config(['verso.extensions.verification.sandbox.enabled' => false]);

    expect(app(SandboxRunner::class)->runTheme(ThemeFixtures::path('harbor-light'), 'harbor-light')->status)->toBe(SandboxResult::SKIPPED);
});

it('refuses to run the theme sandbox command outside the sandbox', function () {
    Artisan::call('verso:sandbox-theme', ['path' => ThemeFixtures::path('harbor-light'), '--database' => database_path('database.sqlite')]);

    expect(Artisan::output())->toContain('This command only runs inside the verification sandbox.');
});

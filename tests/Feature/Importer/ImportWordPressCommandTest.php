<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['verso.importer.download_retry_delay' => 0]);
    Storage::fake('public');
    Http::fake(['*' => Http::response('', 404)]);
    $this->fixture = base_path('tests/Fixtures/wordpress-export.xml');
});

it('imports a file and prints a summary', function () {
    User::factory()->admin()->create();

    $this->artisan('verso:import-wordpress', ['file' => $this->fixture])
        ->expectsTable(['What', 'Created', 'Updated', 'Skipped', 'Failed'], [
            ['Authors', 2, 0, 0, 0],
            ['Terms', 6, 0, 1, 0],
            ['Media', 0, 0, 0, 3],
            ['Entries', 8, 0, 2, 0],
            ['Comments', 2, 0, 2, 0],
            ['Redirects', 12, 0, 0, 0],
        ])
        ->expectsOutputToContain('some items failed')
        ->assertSuccessful();

    expect(Entry::query()->count())->toBe(8);
});

it('gives posts with unknown authors to the first admin', function () {
    $first = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->artisan('verso:import-wordpress', ['file' => $this->fixture])->assertSuccessful();

    expect(User::query()->where('role', 'author')->count())->toBe(2)
        ->and($first->exists)->toBeTrue();
});

it('accepts a user by email or id', function () {
    $editor = User::factory()->editor()->create(['email' => 'beatriz.sousa@verso.example']);

    $this->artisan('verso:import-wordpress', ['file' => $this->fixture, '--user' => 'beatriz.sousa@verso.example'])->assertSuccessful();
    $this->artisan('verso:import-wordpress', ['file' => $this->fixture, '--user' => (string) $editor->id])->assertSuccessful();
});

it('fails without a user to own the content', function () {
    $this->artisan('verso:import-wordpress', ['file' => $this->fixture])
        ->expectsOutputToContain('No user found')
        ->assertFailed();

    $this->artisan('verso:import-wordpress', ['file' => $this->fixture, '--user' => 'nobody@verso.example'])->assertFailed();
});

it('fails clearly for a missing or wrong file', function () {
    User::factory()->admin()->create();
    $rss = tempnam(sys_get_temp_dir(), 'rss');
    file_put_contents($rss, '<?xml version="1.0"?><rss><channel><title>Daily News</title></channel></rss>');

    $this->artisan('verso:import-wordpress', ['file' => '/tmp/harbourlight-missing.xml'])
        ->expectsOutputToContain('cannot be read')
        ->assertFailed();

    $this->artisan('verso:import-wordpress', ['file' => $rss])
        ->expectsOutputToContain('not a WordPress export')
        ->assertFailed();
});

it('reports a clean run without failures', function () {
    User::factory()->admin()->create();
    config(['verso.importer.download_attachments' => false]);

    $this->artisan('verso:import-wordpress', ['file' => $this->fixture])
        ->expectsOutputToContain('The import finished.')
        ->assertSuccessful();
});

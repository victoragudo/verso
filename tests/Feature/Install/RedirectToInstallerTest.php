<?php

use App\Models\User;
use App\Verso\Install\InstallerMode;
use App\Verso\Install\InstallState;
use Tests\Support\InstallFixtures;

beforeEach(function () {
    $this->paths = InstallFixtures::fresh();
    config(['verso.install.redirect' => true, 'session.driver' => 'database', 'cache.default' => 'database', 'queue.default' => 'database']);
});

it('sends every page to the installer while Verso is not installed', function (string $path) {
    $this->get($path)->assertRedirect('/install');
})->with(['/', '/blog', '/admin', '/about-us']);

it('answers JSON clients with 503 and the installer address', function () {
    $this->getJson('/api/v1/entries')
        ->assertStatus(503)
        ->assertJsonPath('install_url', url('/install'));
});

it('forces safe drivers while not installed', function () {
    $this->get('/install/welcome')->assertOk();

    expect(config('session.driver'))->toBe('file')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('cache.default'))->toBe('file')
        ->and(config('queue.default'))->toBe('sync')
        ->and(app(InstallerMode::class)->active())->toBeTrue();
});

it('leaves the health check open', function () {
    $this->get('/up')->assertOk();
});

it('does nothing once the lock file exists', function () {
    app(InstallState::class)->lock();

    $this->get('/install')->assertNotFound();
    $this->get('/')->assertOk();

    expect(config('session.driver'))->toBe('database');
});

it('locks an older install that already has an admin', function () {
    User::factory()->admin()->create();

    $this->get('/')->assertOk();

    expect(file_exists($this->paths['lock']))->toBeTrue();
});

it('can be turned off', function () {
    config(['verso.install.redirect' => false]);

    $this->get('/')->assertOk();
});

<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Jobs\CheckForUpdates;
use App\Verso\Updates\Models\CoreUpdate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create('1.4.2');
    Http::preventStrayRequests();
    Process::fake();
});

afterEach(function () {
    $this->sandbox->destroy();
});

function fakeDailyFeed(UpdateSandbox $sandbox, array $releases, ?string $packageVersion = null): void
{
    $fakes = ['api.github.com/*' => Http::response($releases)];

    if ($packageVersion !== null) {
        $zip = $sandbox->package($packageVersion);
        $fakes[UpdateSandbox::packageUrl($packageVersion).'.sha256'] = Http::response(hash('sha256', $zip));
        $fakes[UpdateSandbox::packageUrl($packageVersion)] = Http::response($zip);
        $fakes[UpdateSandbox::SITE_URL.'/*'] = Http::response('<html>Harbor Notes</html>');
    }

    Http::fake($fakes);
}

it('is scheduled every day', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'verso:check-for-updates');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('17 4 * * *');
});

it('stores the newest release in the cache for the admin', function () {
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.5.0')]);

    app()->call([new CheckForUpdates, 'handle']);

    $status = app(UpdateChecker::class)->status();

    expect($status->latest?->version)->toBe('1.5.0')
        ->and($status->updateAvailable())->toBeTrue()
        ->and($status->checkedAt)->not->toBeNull()
        ->and(CoreUpdate::query()->count())->toBe(0);
});

it('keeps the releases found before when the feed fails', function () {
    Http::fakeSequence('api.github.com/*')
        ->push([UpdateSandbox::githubRelease('1.5.0')])
        ->push('Bad gateway', 502);
    app(UpdateChecker::class)->check();

    $status = app(UpdateChecker::class)->check();

    expect($status->latest?->version)->toBe('1.5.0')
        ->and($status->error)->toContain('could not be reached');
});

it('installs a minor update on its own when automatic minor updates are on', function () {
    app(Settings::class)->set('updates.automatic', 'minor');
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.5.0')], '1.5.0');

    app()->call([new CheckForUpdates, 'handle']);

    $update = CoreUpdate::query()->sole();

    expect($update->trigger)->toBe(UpdateTrigger::Automatic)
        ->and($update->status)->toBe(UpdateStatus::Completed)
        ->and($this->sandbox->read('VERSION'))->toBe("1.5.0\n");
});

it('skips a security minor release in security mode', function () {
    app(Settings::class)->set('updates.automatic', 'security');
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.5.0', ['name' => 'Verso 1.5.0 [security]'])]);

    app()->call([new CheckForUpdates, 'handle']);

    expect(CoreUpdate::query()->count())->toBe(0);
});

it('installs a security patch in security mode', function () {
    app(Settings::class)->set('updates.automatic', 'security');
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.4.3', ['name' => 'Verso 1.4.3 [security]'])], '1.4.3');

    app()->call([new CheckForUpdates, 'handle']);

    expect(CoreUpdate::query()->sole()->to_version)->toBe('1.4.3');
});

it('never installs a new major version on its own', function () {
    app(Settings::class)->set('updates.automatic', 'minor');
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('2.0.0')]);

    app()->call([new CheckForUpdates, 'handle']);

    expect(CoreUpdate::query()->count())->toBe(0);
});

it('does not install automatic updates without a database backup', function () {
    app(Settings::class)->set('updates.automatic', 'minor');
    config(['database.connections.'.UpdateSandbox::CONNECTION.'.database' => ':memory:']);
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.5.0')]);

    app()->call([new CheckForUpdates, 'handle']);

    expect(CoreUpdate::query()->count())->toBe(0);
});

it('follows the beta channel setting', function () {
    fakeDailyFeed($this->sandbox, [UpdateSandbox::githubRelease('1.5.0-beta.1'), UpdateSandbox::githubRelease('1.4.2')]);
    app(UpdateChecker::class)->check();

    expect(app(UpdateChecker::class)->status()->updateAvailable())->toBeFalse();

    app(Settings::class)->set('updates.channel', 'beta');

    expect(app(UpdateChecker::class)->status()->availableRelease()?->version)->toBe('1.5.0-beta.1');
});

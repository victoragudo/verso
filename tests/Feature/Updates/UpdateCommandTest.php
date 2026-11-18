<?php

use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Models\CoreUpdate;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
    Http::preventStrayRequests();
    Process::fake();
});

afterEach(function () {
    $this->sandbox->destroy();
});

function fakeFeedAndPackage(UpdateSandbox $sandbox, string $version): void
{
    $zip = $sandbox->package($version);

    Http::fake([
        'api.github.com/*' => Http::response([UpdateSandbox::githubRelease($version)]),
        UpdateSandbox::packageUrl($version).'.sha256' => Http::response(hash('sha256', $zip)),
        UpdateSandbox::packageUrl($version) => Http::response($zip),
        UpdateSandbox::SITE_URL.'/*' => Http::response('<html>Harbor Notes</html>'),
    ]);
}

it('shows the newest version with --check and changes nothing', function () {
    fakeFeedAndPackage($this->sandbox, '1.2.0');

    $this->artisan('verso:update --check')
        ->expectsOutputToContain('0.1.0')
        ->expectsOutputToContain('Verso 1.2.0 is available.')
        ->assertSuccessful();

    expect(CoreUpdate::query()->count())->toBe(0);
    Http::assertSentCount(1);
});

it('says when the site is up to date', function () {
    Http::fake(['api.github.com/*' => Http::response([UpdateSandbox::githubRelease('0.1.0')])]);

    $this->artisan('verso:update')
        ->expectsOutputToContain('Verso 0.1.0 is up to date.')
        ->assertSuccessful();
});

it('fails when the feed cannot be read', function () {
    Http::fake(['api.github.com/*' => Http::response('Service Unavailable', 503)]);

    $this->artisan('verso:update --check')
        ->expectsOutputToContain('The release feed could not be reached')
        ->assertFailed();
});

it('warns when no feed url is set', function () {
    config(['verso.updates.feed_url' => '']);

    $this->artisan('verso:update')
        ->expectsOutputToContain('Update checks are off')
        ->assertSuccessful();
});

it('asks before updating and stops when the answer is no', function () {
    fakeFeedAndPackage($this->sandbox, '1.2.0');

    $this->artisan('verso:update')
        ->expectsConfirmation('Update Verso from 0.1.0 to 1.2.0 now?', 'no')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertSuccessful();

    expect(CoreUpdate::query()->count())->toBe(0)
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");
});

it('installs the update with --yes and logs it as a command line run', function () {
    fakeFeedAndPackage($this->sandbox, '1.2.0');

    $this->artisan('verso:update --yes')
        ->expectsOutputToContain('Verso 1.2.0 is installed.')
        ->assertSuccessful();

    $update = CoreUpdate::query()->sole();

    expect($update->status)->toBe(UpdateStatus::Completed)
        ->and($update->trigger)->toBe(UpdateTrigger::Cli)
        ->and($this->sandbox->read('VERSION'))->toBe("1.2.0\n");
});

it('fails with the reason when the update is rolled back', function () {
    fakeFeedAndPackage($this->sandbox, '1.2.0');
    Process::fake(fn (PendingProcess $process) => in_array('migrate', (array) $process->command, true)
        ? Process::result(errorOutput: 'Table entries is locked', exitCode: 1)
        : Process::result());

    $this->artisan('verso:update --yes')
        ->expectsOutputToContain('The update was rolled back: php artisan migrate failed: Table entries is locked')
        ->assertFailed();

    expect($this->sandbox->read('VERSION'))->toBe("0.1.0\n");
});

it('warns and goes on without a database backup when it cannot make one', function () {
    config(['database.connections.'.UpdateSandbox::CONNECTION.'.database' => ':memory:']);
    fakeFeedAndPackage($this->sandbox, '1.2.0');

    $this->artisan('verso:update --yes')
        ->expectsOutputToContain('The database will not be backed up.')
        ->assertSuccessful();

    expect(CoreUpdate::query()->sole()->allow_without_database_backup)->toBeTrue();
});

it('rolls back the last update with --rollback', function () {
    fakeFeedAndPackage($this->sandbox, '1.2.0');
    $this->artisan('verso:update --yes')->assertSuccessful();

    $this->artisan('verso:update --rollback')
        ->expectsConfirmation('Roll back from Verso 1.2.0 to 0.1.0?', 'yes')
        ->expectsOutputToContain('Verso 0.1.0 is back.')
        ->assertSuccessful();

    expect(CoreUpdate::query()->sole()->status)->toBe(UpdateStatus::RolledBack)
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");
});

it('has nothing to roll back without a completed update', function () {
    $this->artisan('verso:update --rollback --yes')
        ->expectsOutputToContain('There is no completed update with a backup to roll back.')
        ->assertSuccessful();
});

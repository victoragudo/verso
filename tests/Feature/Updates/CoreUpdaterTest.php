<?php

use App\Models\User;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateStep;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\Release;
use App\Verso\Updates\Models\CoreUpdate;
use App\Verso\Updates\Updater\CoreFiles;
use App\Verso\Updates\Updater\UpdateLock;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
    Http::preventStrayRequests();
    fakeArtisan();
});

afterEach(function () {
    $this->sandbox->destroy();
});

// Fake the package, its checksum file and a healthy site. Overrides win over the defaults.
function fakeReleaseDownloads(UpdateSandbox $sandbox, string $version, array $overrides = []): string
{
    $zip = $sandbox->package($version);
    $defaults = [
        UpdateSandbox::packageUrl($version).'.sha256' => Http::response(hash('sha256', $zip).'  verso-'.$version.'.zip'),
        UpdateSandbox::packageUrl($version).'.sig' => Http::response('', 404),
        UpdateSandbox::packageUrl($version) => Http::response($zip),
        UpdateSandbox::SITE_URL.'/*' => Http::response('<html>Harbor Notes</html>'),
    ];

    Http::fake(array_merge($overrides, array_diff_key($defaults, $overrides)));

    return $zip;
}

// Fake artisan processes. A handler whose key is in the command line answers it; anything else succeeds.
function fakeArtisan(array $handlers = []): void
{
    Process::fake(function (PendingProcess $process) use ($handlers) {
        $command = implode(' ', (array) $process->command);

        foreach ($handlers as $needle => $handler) {
            if (str_contains($command, $needle)) {
                return $handler($process);
            }
        }

        return Process::result();
    });
}

/** @return list<string> */
function stepStatuses(CoreUpdate $update): array
{
    return array_map(fn (array $entry) => $entry['step'].':'.$entry['status'], $update->fresh()->steps ?? []);
}

function artisanCommand(string $command): Closure
{
    return fn (PendingProcess $process) => in_array($command, (array) $process->command, true);
}

it('installs a release step by step and keeps site data', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $admin = User::factory()->admin()->create(['name' => 'Ines Navarro']);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin, $admin));

    expect($update->status)->toBe(UpdateStatus::Completed)
        ->and($update->error)->toBeNull()
        ->and($update->user_id)->toBe($admin->id)
        ->and($update->from_version)->toBe('0.1.0')
        ->and($update->to_version)->toBe('1.2.0')
        ->and(stepStatuses($update))->toBe(array_map(fn (UpdateStep $step) => $step->value.':done', UpdateStep::plan()))
        ->and($this->sandbox->read('VERSION'))->toBe("1.2.0\n")
        ->and($this->sandbox->read('routes/web.php'))->toContain('routes 1.2.0')
        ->and($this->sandbox->read('.env'))->toBe('APP_KEY=base64:site-secret-key')
        ->and($this->sandbox->read('storage/app/notes.txt'))->toBe('notes written by the site')
        ->and($this->sandbox->read('plugins/reading-time/verso.json'))->toBe('{"name":"Reading Time"}')
        ->and($this->sandbox->read('themes/aurora/theme.json'))->toBe('{"name":"Aurora by Lena Fischer"}')
        ->and($this->sandbox->read('themes/default/theme.json'))->toContain('1.2.0')
        ->and($this->sandbox->read('public/uploads/2026/11/harbor-sunrise.jpg'))->toBe('jpeg bytes of the harbor')
        ->and($this->sandbox->guestbook())->toBe(['Marta Ruiz', 'Jonas Becker'])
        ->and(is_dir($this->sandbox->storage('runs/'.$update->id)))->toBeFalse()
        ->and(is_file($this->sandbox->storage('backups/'.$update->id.'/database.sqlite')))->toBeTrue()
        ->and(is_file($this->sandbox->storage('backups/'.$update->id.'/files/routes/web.php')))->toBeTrue();

    Process::assertRan(artisanCommand('down'));
    Process::assertRan(artisanCommand('migrate'));
    Process::assertRan(artisanCommand('optimize:clear'));
    Process::assertRan(artisanCommand('up'));

    Process::assertRan(fn (PendingProcess $process) => $process->path === $this->sandbox->site());
    Http::assertSent(fn ($request) => $request->url() === UpdateSandbox::SITE_URL.'/');
    Http::assertSent(fn ($request) => $request->url() === UpdateSandbox::SITE_URL.'/login');
});

it('stops before touching anything when the checksum does not match', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0', [
        UpdateSandbox::packageUrl('1.2.0').'.sha256' => Http::response(hash('sha256', 'a different package')),
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toContain('does not match its sha256 sum')
        ->and(stepStatuses($update))->toBe(['lock:done', 'download:done', 'verify:failed'])
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");

    Process::assertNothingRan();
});

it('refuses a release without a checksum file', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $release = UpdateSandbox::release('1.2.0');
    $release = new Release($release->version, $release->name, $release->notes, null, false, false, $release->packageUrl, null, null);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start($release, UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toContain('has no sha256 file');
});

it('checks the Ed25519 signature when a public key is set', function () {
    $keys = sodium_crypto_sign_keypair();
    config(['verso.updates.public_key' => base64_encode(sodium_crypto_sign_publickey($keys))]);
    $zip = $this->sandbox->package('1.2.0');
    fakeReleaseDownloads($this->sandbox, '1.2.0', [
        UpdateSandbox::packageUrl('1.2.0').'.sig' => Http::response(base64_encode(sodium_crypto_sign_detached($zip, sodium_crypto_sign_secretkey($keys)))),
        UpdateSandbox::packageUrl('1.2.0').'.sha256' => Http::response(hash('sha256', $zip)),
        UpdateSandbox::packageUrl('1.2.0') => Http::response($zip),
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0', signed: true), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::Completed)
        ->and(collect($update->steps)->firstWhere('step', 'verify')['message'])->toBe('The sha256 sum and the Ed25519 signature match.');
});

it('refuses a package with a bad signature', function () {
    $keys = sodium_crypto_sign_keypair();
    $intruder = sodium_crypto_sign_keypair();
    config(['verso.updates.public_key' => base64_encode(sodium_crypto_sign_publickey($keys))]);
    $zip = $this->sandbox->package('1.2.0');
    fakeReleaseDownloads($this->sandbox, '1.2.0', [
        UpdateSandbox::packageUrl('1.2.0').'.sig' => Http::response(base64_encode(sodium_crypto_sign_detached($zip, sodium_crypto_sign_secretkey($intruder)))),
        UpdateSandbox::packageUrl('1.2.0').'.sha256' => Http::response(hash('sha256', $zip)),
        UpdateSandbox::packageUrl('1.2.0') => Http::response($zip),
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0', signed: true), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toContain('signature does not match')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");
});

it('refuses an unsigned release when a public key is set', function () {
    config(['verso.updates.public_key' => base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))]);
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->error)->toBe('A public key is set, but this release has no signature.');
});

it('refuses a package bigger than the download limit', function () {
    config(['verso.updates.max_download_kb' => 1]);
    fakeReleaseDownloads($this->sandbox, '1.2.0', [
        UpdateSandbox::packageUrl('1.2.0') => Http::response(str_repeat('x', 4096)),
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toContain('is bigger than 1 KB')
        ->and(stepStatuses($update))->toBe(['lock:done', 'download:failed']);
});

it('refuses a too big package from its feed size before downloading', function () {
    config(['verso.updates.max_download_kb' => 1]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0', size: 50_000_000), UpdateTrigger::Admin));

    expect($update->error)->toContain('is bigger than');
    Http::assertNothingSent();
});

it('rolls back files and database when the migrations fail', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    fakeArtisan([
        ' migrate ' => function () {
            DB::connection(UpdateSandbox::CONNECTION)->table('guestbook')->insert(['name' => 'Half migrated row']);

            return Process::result(errorOutput: 'SQLSTATE[42S21]: Column already exists: reading_minutes', exitCode: 1);
        },
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::RolledBack)
        ->and($update->error)->toContain('php artisan migrate failed: SQLSTATE[42S21]')
        ->and(stepStatuses($update))->toContain('migrate:failed', 'rollback:done')
        ->and($update->fresh()->status)->toBe(UpdateStatus::RolledBack)
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n")
        ->and($this->sandbox->read('routes/web.php'))->toContain('routes 0.1.0')
        ->and($this->sandbox->read('app/Legacy/OldWidget.php'))->toContain('removed in the next release')
        ->and(file_exists($this->sandbox->site('app/Verso/Updates/Banner.php')))->toBeFalse()
        ->and($this->sandbox->read('.env'))->toBe('APP_KEY=base64:site-secret-key')
        ->and($this->sandbox->guestbook())->toBe(['Marta Ruiz', 'Jonas Becker'])
        ->and(collect($update->steps)->firstWhere('step', 'rollback')['message'])->toBe('Restored the core files and the database. The site runs Verso 0.1.0 again.');

    Process::assertRan(artisanCommand('up'));
});

it('rolls back when the site is broken after the update', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0', [
        UpdateSandbox::SITE_URL.'/login' => Http::response('Whoops, something went wrong', 500),
    ]);
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::RolledBack)
        ->and($update->error)->toBe('The site answered 500 at '.UpdateSandbox::SITE_URL.'/login.')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n")
        ->and($this->sandbox->guestbook())->toBe(['Marta Ruiz', 'Jonas Becker']);
});

it('rolls back the files when copying the new files fails', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('copyDirectory')->andReturnUsing(function (string $from, string $to) {
        if (str_ends_with($to, '/vendor')) {
            return false;
        }

        return (new Filesystem)->copyDirectory($from, $to);
    });
    app()->bind(CoreFiles::class, fn () => new CoreFiles($files, ['.env', 'storage', 'plugins', 'themes', 'public/uploads', 'database/database.sqlite'], ['themes/default']));
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    expect($update->status)->toBe(UpdateStatus::RolledBack)
        ->and($update->error)->toBe('Could not copy vendor into the site.')
        ->and(stepStatuses($update))->toContain('replace_files:failed', 'rollback:done')
        ->and($this->sandbox->read('vendor/autoload.php'))->toContain('autoload 0.1.0')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");

    Process::assertDidntRun(artisanCommand('migrate'));
});

it('leaves maintenance mode when it fails before changing files', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    config(['database.connections.'.UpdateSandbox::CONNECTION.'.database' => ':memory:']);
    $updater = $this->sandbox->updater();
    $update = $updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin, allowWithoutDatabaseBackup: true);
    $update->forceFill(['allow_without_database_backup' => false])->save();

    $update = $updater->run($update);

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toBe('The SQLite database is not a file on disk.')
        ->and(stepStatuses($update))->toContain('maintenance:done', 'backup_database:failed')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n");

    Process::assertRan(artisanCommand('up'));
});

it('needs consent to update without a database backup and logs a warning', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    config(['database.connections.'.UpdateSandbox::CONNECTION.'.database' => ':memory:']);
    $updater = $this->sandbox->updater();

    expect(fn () => $updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin))
        ->toThrow(UpdateFailed::class, 'Confirm that you want to update without a database backup.');

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin, allowWithoutDatabaseBackup: true));

    expect($update->status)->toBe(UpdateStatus::Completed)
        ->and(stepStatuses($update))->toContain('backup_database:warning');
});

it('refuses a second update while one is waiting', function () {
    $updater = $this->sandbox->updater();
    $updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin);

    $updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Cli);
})->throws(UpdateFailed::class, 'Another update is already waiting or running.');

it('refuses a release that is not newer', function () {
    $this->sandbox->updater()->start(UpdateSandbox::release('0.1.0'), UpdateTrigger::Admin);
})->throws(UpdateFailed::class, 'is not newer than the running version 0.1.0');

it('fails without changes when another process holds the lock', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $other = new UpdateLock(new Filesystem, $this->sandbox->storage('update.lock'));
    $other->acquire();
    $updater = $this->sandbox->updater();

    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));
    $other->release();

    expect($update->status)->toBe(UpdateStatus::Failed)
        ->and($update->error)->toBe('Another update is running right now.')
        ->and(stepStatuses($update))->toBe(['lock:failed']);

    Http::assertNothingSent();
});

it('releases the lock after a run', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $updater = $this->sandbox->updater();
    $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    $lock = new UpdateLock(new Filesystem, $this->sandbox->storage('update.lock'));

    expect($lock->acquire())->toBeTrue();
    $lock->release();
});

it('does not run an update twice', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $updater = $this->sandbox->updater();
    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));
    $steps = $update->steps;

    expect($updater->run($update)->steps)->toBe($steps);
});

it('rolls back a completed update by hand', function () {
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $updater = $this->sandbox->updater();
    $update = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));
    DB::connection(UpdateSandbox::CONNECTION)->table('guestbook')->insert(['name' => 'Written by the new version']);

    expect($updater->lastRollbackCandidate()?->is($update))->toBeTrue();

    $updater->rollback($update);

    expect($update->status)->toBe(UpdateStatus::RolledBack)
        ->and($update->error)->toBe('Rolled back to Verso 0.1.0 by hand.')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n")
        ->and($this->sandbox->guestbook())->toBe(['Marta Ruiz', 'Jonas Becker'])
        ->and($updater->lastRollbackCandidate())->toBeNull();
});

it('only rolls back completed updates with a backup', function () {
    $updater = $this->sandbox->updater();
    $update = $updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin);

    expect(fn () => $updater->rollback($update))->toThrow(UpdateFailed::class, 'Only a completed update can be rolled back.');

    $update->forceFill(['status' => UpdateStatus::Completed, 'backup_path' => null])->save();

    expect(fn () => $updater->rollback($update))->toThrow(UpdateFailed::class, 'backup of this update is gone');
});

it('keeps only the newest backups', function () {
    config(['verso.updates.backups_kept' => 1]);
    fakeReleaseDownloads($this->sandbox, '1.2.0');
    $updater = $this->sandbox->updater();
    $first = $updater->run($updater->start(UpdateSandbox::release('1.2.0'), UpdateTrigger::Admin));

    config(['verso.version' => '1.2.0']);
    fakeReleaseDownloads($this->sandbox, '1.3.0');
    $updater = $this->sandbox->updater();
    $second = $updater->run($updater->start(UpdateSandbox::release('1.3.0'), UpdateTrigger::Admin));

    expect($second->status)->toBe(UpdateStatus::Completed)
        ->and($first->fresh()->backup_path)->toBeNull()
        ->and(is_dir($this->sandbox->storage('backups/'.$first->id)))->toBeFalse()
        ->and(is_dir($this->sandbox->storage('backups/'.$second->id)))->toBeTrue()
        ->and($this->sandbox->read('VERSION'))->toBe("1.3.0\n");
});

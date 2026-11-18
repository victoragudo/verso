<?php

use App\Models\User;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Tests\Support\InstallFixtures;

beforeEach(function () {
    $this->lockFile = InstallFixtures::useFreshLockFile();
    $this->state = app(InstallState::class);
});

it('is not installed on a fresh site', function () {
    expect($this->state->isInstalled())->toBeFalse()
        ->and($this->state->isLocked())->toBeFalse()
        ->and($this->state->lockFile())->toBe($this->lockFile);
});

it('is installed once an admin exists', function () {
    User::factory()->editor()->create();
    expect($this->state->adminExists())->toBeFalse();

    User::factory()->admin()->create();
    expect($this->state->adminExists())->toBeTrue()
        ->and($this->state->isInstalled())->toBeTrue();
});

it('is installed once locked', function () {
    $this->state->lock();

    expect(file_exists($this->lockFile))->toBeTrue()
        ->and(file_get_contents($this->lockFile))->toStartWith('Installed at ')
        ->and($this->state->isInstalled())->toBeTrue();
});

it('says no admin exists when the users table is missing', function () {
    User::factory()->admin()->create();
    $connection = DB::connection();
    $prefix = $connection->getTablePrefix();

    // A prefix points every query at tables that do not exist. Dropping users would fail on Postgres
    // (other tables reference it) and would commit the open test transaction on MySQL.
    $connection->setTablePrefix('not_installed_');

    try {
        expect($this->state->adminExists())->toBeFalse();
    } finally {
        $connection->setTablePrefix($prefix);
    }
});

it('is not installed while an install is running, even with an admin', function () {
    $progress = new InstallProgress(new Filesystem, dirname($this->lockFile).'/progress.json');
    $state = new InstallState(new Filesystem, $this->lockFile, $progress);
    User::factory()->admin()->create();
    $progress->start(['environment', 'admin']);

    expect($state->inProgress())->toBeTrue()
        ->and($state->isInstalled())->toBeFalse();

    $state->lock();

    expect($state->isInstalled())->toBeTrue();
});

<?php

use App\Verso\Updates\Updater\UpdateLock;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->path = sys_get_temp_dir().'/verso-lock-'.bin2hex(random_bytes(4)).'/update.lock';
});

afterEach(function () {
    (new Filesystem)->deleteDirectory(dirname($this->path));
});

it('lets only one holder take the lock', function () {
    $first = new UpdateLock(new Filesystem, $this->path);
    $second = new UpdateLock(new Filesystem, $this->path);

    expect($first->acquire())->toBeTrue()
        ->and($first->isHeld())->toBeTrue()
        ->and($second->acquire())->toBeFalse()
        ->and($second->isHeld())->toBeFalse();

    $first->release();

    expect($first->isHeld())->toBeFalse()
        ->and($second->acquire())->toBeTrue();

    $second->release();
});

it('can take the lock again while it already holds it', function () {
    $lock = new UpdateLock(new Filesystem, $this->path);

    expect($lock->acquire())->toBeTrue()
        ->and($lock->acquire())->toBeTrue();

    $lock->release();
    $lock->release();

    expect($lock->isHeld())->toBeFalse();
});

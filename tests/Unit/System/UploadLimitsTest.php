<?php

use App\Verso\System\Server\RuntimeLimits;
use App\Verso\System\Server\UploadLimits;
use Tests\Support\FakePhpIni;

it('uses the Verso setting when it is the lowest limit', function () {
    $limit = (new UploadLimits(FakePhpIni::sharedHosting(['upload_max_filesize' => '64M', 'post_max_size' => '72M']), 256))->effective(10240);

    expect($limit->bytes)->toBe(10 * 1024 ** 2)
        ->and($limit->bottleneck)->toBe('verso')
        ->and($limit->isLimitedByServer())->toBeFalse()
        ->and($limit->explanation())->toBe('Uploads up to 10 MB work. The Verso setting is the lowest limit.');
});

it('names upload_max_filesize as the bottleneck', function () {
    $limit = (new UploadLimits(FakePhpIni::sharedHosting(['upload_max_filesize' => '2M', 'post_max_size' => '8M']), 256))->effective(51200);

    expect($limit->bytes)->toBe(2 * 1024 ** 2)
        ->and($limit->bottleneck)->toBe('upload_max_filesize')
        ->and($limit->explanation())->toBe('Uploads stop at 2 MB because of PHP upload_max_filesize. Raise it to allow bigger files.');
});

it('keeps room for other form fields inside post_max_size', function () {
    $limit = (new UploadLimits(FakePhpIni::sharedHosting(['upload_max_filesize' => '64M', 'post_max_size' => '8M']), 512))->effective(51200);

    expect($limit->bottleneck)->toBe('post_max_size')
        ->and($limit->bytes)->toBe(8 * 1024 ** 2 - 512 * 1024)
        ->and($limit->toArray()['human'])->toBe('7.5 MB');
});

it('uses a known web server limit', function () {
    $limit = (new UploadLimits(FakePhpIni::sharedHosting(['upload_max_filesize' => '128M', 'post_max_size' => '130M']), 256, webServerMb: 32))->effective(102400);

    expect($limit->bottleneck)->toBe('web_server')
        ->and($limit->bytes)->toBe(32 * 1024 ** 2);
});

it('treats 0 as no limit for PHP values', function () {
    $limit = (new UploadLimits(FakePhpIni::sharedHosting(['upload_max_filesize' => '0', 'post_max_size' => '0']), 256))->effective(4096);

    expect($limit->bottleneck)->toBe('verso')
        ->and($limit->bytes)->toBe(4096 * 1024);
});

it('raises memory and time for heavy tasks only when PHP allows it', function () {
    $ini = FakePhpIni::sharedHosting();

    $changed = (new RuntimeLimits($ini, memoryMb: 512, timeSeconds: 600))->raiseForHeavyTask();

    expect($changed)->toBe(['memory_limit' => '512M', 'max_execution_time' => '600']);
});

it('never lowers limits and skips values that cannot change at runtime', function () {
    $ini = FakePhpIni::sharedHosting(['memory_limit' => '1G', 'max_execution_time' => '0']);
    expect((new RuntimeLimits($ini, 512, 600))->raiseForHeavyTask())->toBe([]);

    $locked = FakePhpIni::sharedHosting();
    $locked->accessBits['memory_limit'] = FakePhpIni::ACCESS_SYSTEM;
    $locked->accessBits['max_execution_time'] = FakePhpIni::ACCESS_PERDIR | FakePhpIni::ACCESS_SYSTEM;
    expect((new RuntimeLimits($locked, 512, 600))->raiseForHeavyTask())->toBe([])
        ->and($locked->changed)->toBe([]);
});

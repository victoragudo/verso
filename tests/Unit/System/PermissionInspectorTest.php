<?php

use App\Verso\System\Health\HealthCheck;
use App\Verso\System\Health\PermissionFixer;
use App\Verso\System\Health\PermissionInspector;
use App\Verso\System\Health\PlannedFix;
use App\Verso\System\Health\ProcessIdentity;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/verso-health-'.Str::random(8);
    $this->outside = sys_get_temp_dir().'/verso-outside-'.Str::random(8);
    foreach (['storage/app/public', 'storage/logs', 'bootstrap/cache', 'public', 'plugins'] as $folder) {
        File::ensureDirectoryExists($this->root.'/'.$folder);
    }
    File::ensureDirectoryExists($this->outside);
    file_put_contents($this->root.'/.env', "APP_KEY=base64:c2VjcmV0\n");
    chmod($this->root.'/.env', 0640);
    $this->linked = false;
});

afterEach(function () {
    File::deleteDirectory($this->root);
    File::deleteDirectory($this->outside);
});

function inspector(string $root, array $overrides = []): PermissionInspector
{
    return new PermissionInspector(...[
        'basePath' => $root,
        'writable' => ['storage', 'storage/app/public', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache'],
        'optional' => ['plugins', 'themes'],
        'scan' => ['storage', 'bootstrap/cache', 'plugins', 'public'],
        'scanLimit' => 1000,
        'identity' => ProcessIdentity::current(),
        ...$overrides,
    ]);
}

function check(array $checks, string $key): HealthCheck
{
    return collect($checks)->firstOrFail(fn (HealthCheck $check): bool => $check->key === $key);
}

it('reports missing folders, an open .env, world writable files and a missing storage link', function () {
    chmod($this->root.'/.env', 0644);
    file_put_contents($this->root.'/storage/logs/laravel.log', 'Mail sent to Lucía Ortega');
    chmod($this->root.'/storage/logs/laravel.log', 0666);

    $checks = inspector($this->root)->checks();

    expect(check($checks, 'folder:storage/framework/cache')->status->value)->toBe('danger')
        ->and(check($checks, 'folder:storage/logs')->status->value)->toBe('ok')
        ->and(check($checks, 'folder:themes')->status->value)->toBe('info')
        ->and(check($checks, 'env')->status->value)->toBe('danger')
        ->and(check($checks, 'env')->mode)->toBe('0644')
        ->and(check($checks, 'storage-link')->status->value)->toBe('warning')
        ->and(check($checks, 'world-writable')->detail)->toContain('storage/logs/laravel.log');
});

it('finds a web root at the project folder, debug in production and a public .env', function () {
    $checks = inspector($this->root, ['documentRoot' => $this->root, 'debug' => true, 'production' => true])->checks();

    expect(check($checks, 'web-root')->status->value)->toBe('danger')
        ->and(check($checks, 'debug')->status->value)->toBe('danger');

    file_put_contents($this->root.'/public/.env', 'APP_KEY=leaked');
    expect(check(inspector($this->root)->checks(), 'web-root')->detail)->toContain('inside public/');
});

it('plans fixes as a dry run without changing anything', function () {
    chmod($this->root.'/.env', 0644);
    $inspector = inspector($this->root);
    $fixer = new PermissionFixer($inspector, fn (): bool => $this->linked = true);

    $results = $fixer->run($inspector->plan(), dryRun: true);

    expect(array_column($results, 'description'))->toContain(
        'Create the folder storage/framework/cache with mode 0775',
        'Change the mode of .env to 0640',
        'Link public/storage to storage/app/public',
    )
        ->and(array_column($results, 'done'))->each->toBeFalse()
        ->and(is_dir($this->root.'/storage/framework/cache'))->toBeFalse()
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0644)
        ->and($this->linked)->toBeFalse();
});

it('runs the fixes inside the project', function () {
    chmod($this->root.'/.env', 0644);
    $inspector = inspector($this->root);

    $results = (new PermissionFixer($inspector, fn (): bool => $this->linked = true))->run($inspector->plan(), dryRun: false);

    expect(array_column($results, 'error'))->each->toBeNull()
        ->and(is_dir($this->root.'/storage/framework/cache'))->toBeTrue()
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0640)
        ->and($this->linked)->toBeTrue();
});

it('never changes paths outside the project or behind links', function () {
    chmod($this->outside, 0755);
    symlink($this->outside, $this->root.'/plugins/linked-out');
    $fixer = new PermissionFixer(inspector($this->root), fn (): bool => true);

    $results = $fixer->run([
        new PlannedFix(PlannedFix::CHMOD, '../'.basename($this->outside), 0777),
        new PlannedFix(PlannedFix::CHMOD, 'plugins/linked-out', 0777),
        new PlannedFix(PlannedFix::MKDIR, 'plugins/linked-out/new-folder', 0775),
        new PlannedFix(PlannedFix::CHMOD, '/etc/hosts', 0777),
    ], dryRun: false);

    expect(array_column($results, 'done'))->each->toBeFalse()
        ->and(array_column($results, 'error'))->toBe([
            'The path must stay inside the project.',
            'Links are never changed.',
            'The folder would be outside the project.',
            'The path must stay inside the project.',
        ])
        ->and(fileperms($this->outside) & 0777)->toBe(0755)
        ->and(is_dir($this->outside.'/new-folder'))->toBeFalse();
});

it('does not scan through links that leave the project', function () {
    file_put_contents($this->outside.'/open.txt', 'shared');
    chmod($this->outside.'/open.txt', 0666);
    symlink($this->outside, $this->root.'/plugins/linked-out');

    expect(check(inspector($this->root)->checks(), 'world-writable')->status->value)->toBe('ok');
});

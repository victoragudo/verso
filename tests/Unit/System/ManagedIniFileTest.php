<?php

use App\Verso\System\Server\IniTarget;
use App\Verso\System\Server\ManagedIniFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/verso-ini-'.Str::random(8);
    File::ensureDirectoryExists($this->root.'/public');
    $this->file = new ManagedIniFile($this->root.'/public', $this->root.'/backups');
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('creates .user.ini with a managed block', function () {
    $written = $this->file->write(IniTarget::UserIni, ['upload_max_filesize' => '64M', 'post_max_size' => '65M']);

    expect($written['backup'])->toBeNull()
        ->and(file_get_contents($this->root.'/public/.user.ini'))->toBe(
            "; BEGIN Verso managed PHP limits\n; Written by Verso (System settings). Lines inside this block are replaced.\nupload_max_filesize = 64M\npost_max_size = 65M\n; END Verso managed PHP limits\n",
        );
});

it('replaces only its own block, keeps other lines and makes a backup', function () {
    file_put_contents($this->root.'/public/.user.ini', "; Set by the hosting panel\ndisplay_errors = Off\n");
    $this->file->write(IniTarget::UserIni, ['upload_max_filesize' => '32M']);

    $written = $this->file->write(IniTarget::UserIni, ['upload_max_filesize' => '128M', 'memory_limit' => '512M']);
    $content = (string) file_get_contents($this->root.'/public/.user.ini');

    expect($content)->toStartWith("; Set by the hosting panel\ndisplay_errors = Off\n\n; BEGIN Verso managed PHP limits")
        ->and($content)->toContain("upload_max_filesize = 128M\nmemory_limit = 512M\n")
        ->and($content)->not->toContain('32M')
        ->and(substr_count($content, 'BEGIN Verso managed PHP limits'))->toBe(1)
        ->and($written['backup'])->toStartWith($this->root.'/backups/user.ini.')
        ->and(file_get_contents((string) $written['backup']))->toContain('upload_max_filesize = 32M');
});

it('writes php_value lines for Apache and keeps the rewrite rules', function () {
    file_put_contents($this->root.'/public/.htaccess', "<IfModule mod_rewrite.c>\n    RewriteEngine On\n</IfModule>\n");

    $this->file->write(IniTarget::Htaccess, ['upload_max_filesize' => '64M']);
    $content = (string) file_get_contents($this->root.'/public/.htaccess');

    expect($content)->toStartWith("<IfModule mod_rewrite.c>\n    RewriteEngine On\n</IfModule>\n")
        ->and($content)->toContain("<IfModule mod_php.c>\n    php_value upload_max_filesize 64M\n</IfModule>\n# END Verso managed PHP limits");
});

it('refuses unknown names and values that could inject lines', function (string $name, string $value) {
    $this->file->write(IniTarget::UserIni, [$name => $value]);
})->with([
    ['disable_functions', ''],
    ['upload_max_filesize', "64M\nauto_prepend_file = /tmp/evil.php"],
    ['memory_limit', '512 M'],
])->throws(InvalidArgumentException::class);

it('does not write when the folder is read only', function () {
    chmod($this->root.'/public', 0555);

    expect($this->file->writable(IniTarget::UserIni))->toBeFalse();
    expect(fn () => $this->file->write(IniTarget::UserIni, ['upload_max_filesize' => '64M']))->toThrow(RuntimeException::class);

    chmod($this->root.'/public', 0755);
})->skip(fn () => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can always write');

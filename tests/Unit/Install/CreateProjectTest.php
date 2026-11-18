<?php

use Illuminate\Support\Facades\Process;

// "composer create-project" must leave a project the web wizard can install without any table.

function composerScripts(string $name): array
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    return array_values(array_map(strval(...), (array) ($composer['scripts'][$name] ?? [])));
}

function envExample(): array
{
    return Dotenv\Dotenv::parse((string) file_get_contents(base_path('.env.example')));
}

it('does not create tables after create-project, so the wizard gets an empty database', function () {
    $scripts = composerScripts('post-create-project-cmd');

    expect($scripts)->not->toBeEmpty()
        ->and(array_filter($scripts, fn (string $script): bool => str_contains($script, 'migrate')))->toBe([])
        ->and(implode("\n", $scripts))->toContain('database/database.sqlite');
});

it('boots a new project without database tables for sessions, cache and the queue', function () {
    $env = envExample();

    expect($env['SESSION_DRIVER'])->toBe('file')
        ->and($env['CACHE_STORE'])->toBe('file')
        ->and($env['QUEUE_CONNECTION'])->toBe('sync');
});

it('runs the post-create scripts without leaving tables in the SQLite file', function () {
    $folder = storage_path('framework/testing/create-project-'.uniqid());
    mkdir($folder.'/database', 0755, true);

    try {
        foreach (composerScripts('post-create-project-cmd') as $script) {
            if (str_starts_with($script, '@php -r ')) {
                $code = stripcslashes(trim(substr($script, strlen('@php -r ')), '"'));
                Process::path($folder)->run([PHP_BINARY, '-r', $code])->throw();
            }
        }

        $tables = (new PDO('sqlite:'.$folder.'/database/database.sqlite'))
            ->query("select name from sqlite_master where type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        expect(file_exists($folder.'/database/database.sqlite'))->toBeTrue()
            ->and($tables)->toBe([]);
    } finally {
        @unlink($folder.'/database/database.sqlite');
        @rmdir($folder.'/database');
        @rmdir($folder);
    }
});

<?php

namespace Verso\Plugins\BackupRunner;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

// Runs backups with shell commands.
class Backup
{
    public function run(string $target): void
    {
        exec('mysqldump verso > '.$target);
        shell_exec('gzip '.$target);
        system('ls -la');
        passthru('du -sh storage');
        proc_open('tar -czf backup.tgz storage', [], $pipes);
        popen('rsync -a storage/ backup/', 'r');
        $files = `ls storage`;
        Process::run('sync');
        (new SymfonyProcess(['tar', '-czf', 'backup.tgz', 'storage']))->run();
        dl('backup.so');
    }

    public function schedule(string $path): void
    {
        ini_set('max_execution_time', '0');
        putenv('BACKUP_RUNNING=1');
        file_put_contents('/etc/cron.d/verso-backup', '0 3 * * * php artisan backup');
        File::put(base_path('public/backup-status.txt'), 'ok');
        file_put_contents($path, 'done');
        file_put_contents(storage_path('app/backup.log'), 'done');
        file_put_contents(__DIR__.'/../cache/last-run.txt', 'done');
        DB::statement('VACUUM');
        DB::unprepared('DELETE FROM sessions');
        $force = $_GET['force'] ?? false;
    }
}

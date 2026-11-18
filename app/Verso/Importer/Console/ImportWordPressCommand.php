<?php

namespace App\Verso\Importer\Console;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\WordPressImporter;
use App\Verso\Importer\Wxr\InvalidWxrFile;
use Illuminate\Console\Command;

// Imports a WordPress export file from the command line, with a progress bar and a summary.
class ImportWordPressCommand extends Command
{
    protected $signature = 'verso:import-wordpress
        {file : Path to the WordPress export (WXR) file}
        {--user= : Email or id of the user who owns posts with an unknown author (default: the first admin)}';

    protected $description = 'Import posts, pages, terms, authors, media and comments from a WordPress export file';

    public function handle(WordPressImporter $importer): int
    {
        $path = (string) $this->argument('file');
        $user = $this->owner();

        if ($user === null) {
            $this->components->error('No user found to own the imported content. Create an admin first or pass --user.');

            return self::FAILURE;
        }

        try {
            $total = $importer->countItems($path);
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $report = $importer->import($path, $user, function (int $done) use ($bar): void {
                $bar->setProgress($done);
            });

            $bar->finish();
            $this->newLine(2);
        } catch (InvalidWxrFile $exception) {
            $this->newLine();
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary($report);

        return self::SUCCESS;
    }

    protected function owner(): ?User
    {
        $option = $this->option('user');

        if (is_string($option) && $option !== '') {
            return User::query()
                ->where('email', $option)
                ->when(ctype_digit($option), fn ($query) => $query->orWhere('id', (int) $option))
                ->first();
        }

        return User::query()->where('role', Role::Admin)->oldest('id')->first();
    }

    protected function summary(ImportReport $report): void
    {
        $this->table(
            ['What', 'Created', 'Updated', 'Skipped', 'Failed'],
            array_map(fn (array $row): array => [ucfirst($row['kind']), $row['created'], $row['updated'], $row['skipped'], $row['failed']], $report->rows()),
        );

        foreach ($report->messages() as $message) {
            $this->components->warn($message);
        }

        $dropped = $report->toArray()['dropped_messages'];

        if ($dropped > 0) {
            $this->components->warn("{$dropped} more warnings were not shown.");
        }

        $report->hasFailures()
            ? $this->components->warn('The import finished, but some items failed. See the warnings above.')
            : $this->components->info('The import finished.');
    }
}

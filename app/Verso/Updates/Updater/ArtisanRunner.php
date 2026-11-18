<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Updates\Exceptions\UpdateFailed;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

// Runs artisan commands of the site in a new PHP process, so they load the new code after an update.
class ArtisanRunner
{
    public function __construct(
        protected string $installPath,
        protected string $phpBinary,
        protected int $timeout,
    ) {}

    /**
     * Run a command and return what it printed. A failing command throws.
     *
     * @param  list<string>  $arguments
     */
    public function run(string $command, array $arguments = []): string
    {
        $result = Process::path($this->installPath)
            ->timeout($this->timeout)
            ->run([$this->php(), 'artisan', $command, ...$arguments, '--no-interaction']);

        if ($result->failed()) {
            $output = trim($result->errorOutput()) ?: trim($result->output());

            throw UpdateFailed::because(__(':program failed: :output', ['program' => 'php artisan '.$command, 'output' => mb_substr($output, -500)]));
        }

        return trim($result->output());
    }

    // The PHP command line program. Under PHP-FPM, PHP_BINARY is not the right one.
    protected function php(): string
    {
        if ($this->phpBinary !== '') {
            return $this->phpBinary;
        }

        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}

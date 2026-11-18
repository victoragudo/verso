<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use Illuminate\Console\Command;
use Throwable;

// Shared parts of the commands that run inside the verification sandbox process.
abstract class SandboxCommand extends Command
{
    protected $hidden = true;

    /** @var array<string, mixed> */
    protected array $report = ['errors' => []];

    // The command refuses to run unless the parent set up a separate SQLite file for it.
    protected function safetyProblem(): ?string
    {
        $database = (string) $this->option('database');
        $configured = (string) config('database.connections.sqlite.database');

        if (getenv(SandboxRunner::ENV_FLAG) !== '1' || $database === '' || config('database.default') !== 'sqlite') {
            return 'This command only runs inside the verification sandbox.';
        }

        if (realpath($database) === false || realpath($database) !== realpath($configured)) {
            return 'The sandbox database is not the configured database.';
        }

        return null;
    }

    /**
     * Run one step and turn any error into report data.
     *
     * @param  callable(): ?string  $step
     * @return array{stage: string, message: string, file?: string|null, line?: int|null}|null
     */
    protected function step(string $stage, callable $step): ?array
    {
        try {
            $message = $step();
        } catch (Throwable $exception) {
            return $this->errorFrom($stage, $exception);
        }

        return $message === null ? null : ['stage' => $stage, 'message' => $message];
    }

    /**
     * An error entry for the report, with the place inside the checked folder when there is one.
     *
     * @return array{stage: string, message: string, file: string|null, line: int|null}
     */
    protected function errorFrom(string $stage, Throwable $exception): array
    {
        [$file, $line] = $this->sourceFrame($exception);

        return [
            'stage' => $stage,
            'message' => str_replace(dirname((string) $this->option('database')).DIRECTORY_SEPARATOR, '', class_basename($exception).': '.$exception->getMessage()),
            'file' => $file,
            'line' => $line,
        ];
    }

    /**
     * The first place in the checked folder where the error happened, as a path inside that folder.
     *
     * @return array{0: string|null, 1: int|null}
     */
    protected function sourceFrame(Throwable $exception): array
    {
        $base = rtrim((string) realpath((string) $this->argument('path')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $frames = [['file' => $exception->getFile(), 'line' => $exception->getLine()], ...$exception->getTrace()];

        foreach ($frames as $frame) {
            $file = (string) ($frame['file'] ?? '');

            if ($file !== '' && str_starts_with($file, $base)) {
                return [str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($base))), (int) ($frame['line'] ?? 0)];
            }
        }

        return [null, null];
    }

    /**
     * Print the JSON result after the marker.
     *
     * @param  list<array<string, mixed>>  $errors
     */
    protected function finish(array $errors): int
    {
        $this->report['errors'] = $errors;
        $this->line(SandboxRunner::MARKER.json_encode($this->report, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}

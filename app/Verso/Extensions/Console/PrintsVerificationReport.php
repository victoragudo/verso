<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Packages\PackageExtractor;
use App\Verso\Extensions\Packages\PackageInspector;
use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\VerificationReport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

// Shared parts of verso:verify-plugin and verso:verify-theme: read a folder or zip and print the report.
trait PrintsVerificationReport
{
    /**
     * Verify the folder or zip in the "path" argument with $verify, which gets a folder and returns the report.
     *
     * @param  callable(string): VerificationReport  $verify
     */
    protected function verifyPath(ExtensionKind $kind, callable $verify): int
    {
        $path = (string) $this->argument('path');
        $noun = strtolower($kind->label());

        if (is_dir($path)) {
            return $this->show($verify((string) realpath($path)));
        }

        if (! is_file($path)) {
            $this->components->error("No {$noun} folder or zip file was found at {$path}.");

            return self::INVALID;
        }

        try {
            $package = $this->laravel->make(PackageInspector::class)->inspect($path, $kind);
        } catch (InvalidPackage $exception) {
            $this->components->error("The zip file is not a valid {$noun} package.");
            $this->components->bulletList($exception->errors);

            return self::INVALID;
        }

        $work = rtrim((string) config('verso.extensions.storage_path'), '/\\').DIRECTORY_SEPARATOR.'verify'.DIRECTORY_SEPARATOR.Str::uuid();

        try {
            $this->laravel->make(PackageExtractor::class)->extract($path, $package, $work.DIRECTORY_SEPARATOR.$package->slug);

            return $this->show($verify($work.DIRECTORY_SEPARATOR.$package->slug));
        } finally {
            $this->laravel->make(Filesystem::class)->deleteDirectory($work);
        }
    }

    // Print the report. High risk, or a finding that stops the install, ends with a failure exit code.
    protected function show(VerificationReport $report): int
    {
        $blocking = array_values(array_map(strval(...), (array) ($report->details['blocking'] ?? [])));

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->newLine();
            $this->line(sprintf('  <options=bold>%s %s</>: %s (score %d)', $report->details['name'] ?? $report->slug, $report->version, $report->risk->label(), $report->score));
            $this->newLine();

            if ($report->findings === []) {
                $this->components->info('No findings.');
            } else {
                $this->table(['Severity', 'Rule', 'File', 'Line', 'Finding'], array_map(fn (Finding $finding): array => [
                    $finding->severity->value,
                    $finding->rule,
                    $finding->file ?? '',
                    $finding->line ?? '',
                    $finding->message,
                ], $report->findings));
            }

            if ($blocking !== []) {
                $this->components->error('These findings stop the install: '.implode(', ', $blocking).'.');
            }
        }

        return $report->risk === RiskLevel::High || $blocking !== [] ? self::FAILURE : self::SUCCESS;
    }
}

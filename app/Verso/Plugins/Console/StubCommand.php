<?php

namespace App\Verso\Plugins\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

// Base for the verso:make-* commands: reads stubs from stubs/verso and writes files.
abstract class StubCommand extends Command
{
    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Fill a stub. Placeholders look like {{ name }}.
     *
     * @param  array<string, string>  $replacements
     */
    protected function renderStub(string $stub, array $replacements): string
    {
        $path = base_path('stubs/verso/'.$stub.'.stub');

        if (! $this->files->exists($path)) {
            throw new RuntimeException("The stub {$stub} was not found at {$path}.");
        }

        $search = array_map(fn (string $key): string => '{{ '.$key.' }}', array_keys($replacements));

        return str_replace($search, array_values($replacements), $this->files->get($path));
    }

    // Write a file and create its folder. Returns false when the file exists and --force is not set.
    protected function writeFile(string $path, string $contents): bool
    {
        if ($this->files->exists($path) && ! (bool) $this->option('force')) {
            $this->components->error('The file '.$this->relative($path).' already exists. Use --force to replace it.');

            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
        $this->components->twoColumnDetail('Created', $this->relative($path));

        return true;
    }

    protected function relative(string $path): string
    {
        return str_starts_with($path, base_path().DIRECTORY_SEPARATOR) ? substr($path, strlen(base_path()) + 1) : $path;
    }
}

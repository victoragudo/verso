<?php

namespace App\Verso\Install\Env;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

// Writes keys into an env file. Comments and unknown keys stay, values are quoted safely, the write is atomic.
class EnvWriter
{
    // Values made only of these characters are written without quotes.
    protected const BARE_VALUE = '/^[A-Za-z0-9_.\/:@,+\-]*$/';

    protected const KEY = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function __construct(
        protected Filesystem $files,
        protected int $permissions,
    ) {}

    public function write(EnvWriteInput $input): EnvWriteOutput
    {
        $lines = $this->files->exists($input->path)
            ? preg_split('/\r\n|\n|\r/', $this->files->get($input->path)) ?: []
            : [];

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $updated = [];
        $added = [];

        foreach ($input->values as $key => $value) {
            $line = $key.'='.$this->format($key, $value);

            if ($this->replaceActive($lines, $key, $line) || $this->replaceCommented($lines, $key, $line)) {
                $updated[] = $key;

                continue;
            }

            $lines[] = $line;
            $added[] = $key;
        }

        $this->atomicPut($input->path, implode(PHP_EOL, $lines).PHP_EOL);

        return new EnvWriteOutput($updated, $added);
    }

    // The value as it must appear after "KEY=".
    public function format(string $key, string|int|bool|null $value): string
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidEnvValue::key($key);
        }

        $text = match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };

        if (preg_match('/[\r\n\0]/', $text) === 1) {
            throw InvalidEnvValue::lineBreak($key);
        }

        if (preg_match(self::BARE_VALUE, $text) === 1) {
            return $text;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $text).'"';
    }

    // A fresh key for APP_KEY, in the format Laravel expects.
    public function newAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Change every active "KEY=" line. Returns false when there is none.
     *
     * @param  list<string>  $lines
     */
    protected function replaceActive(array &$lines, string $key, string $line): bool
    {
        $found = false;

        foreach ($lines as $index => $current) {
            if (preg_match('/^\s*(?:export\s+)?'.preg_quote($key, '/').'\s*=/', $current) === 1) {
                $lines[$index] = $line;
                $found = true;
            }
        }

        return $found;
    }

    /**
     * Turn the first "# KEY=" line into the new value, so the key keeps its place.
     *
     * @param  list<string>  $lines
     */
    protected function replaceCommented(array &$lines, string $key, string $line): bool
    {
        foreach ($lines as $index => $current) {
            if (preg_match('/^\s*#\s*'.preg_quote($key, '/').'=/', $current) === 1) {
                $lines[$index] = $line;

                return true;
            }
        }

        return false;
    }

    // Write to a temporary file next to the target, then rename it, so readers never see half a file.
    protected function atomicPut(string $path, string $contents): void
    {
        $folder = dirname($path);

        if (! is_dir($folder) || ! is_writable($folder)) {
            if ($this->files->put($path, $contents, true) === false) {
                throw new RuntimeException(__('Could not write :path.', ['path' => $path]));
            }

            return;
        }

        $temporary = $folder.DIRECTORY_SEPARATOR.'.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException(__('Could not write :path.', ['path' => $path]));
        }

        @chmod($temporary, $this->permissions);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException(__('Could not replace :path.', ['path' => $path]));
        }
    }
}

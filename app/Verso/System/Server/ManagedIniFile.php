<?php

namespace App\Verso\System\Server;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

// Writes PHP limits into a marked block of public/.user.ini or public/.htaccess. Text outside the block stays.
class ManagedIniFile
{
    public const BEGIN = 'BEGIN Verso managed PHP limits';

    public const END = 'END Verso managed PHP limits';

    // Only these values may be written, and only as sizes, seconds or counts.
    public const ALLOWED = ['upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars', 'max_file_uploads'];

    public function __construct(
        protected string $publicPath,
        protected string $backupPath,
        protected string $userIniName = '.user.ini',
    ) {}

    public function path(IniTarget $target): string
    {
        return rtrim($this->publicPath, '/\\').DIRECTORY_SEPARATOR.$target->fileName($this->userIniName);
    }

    // True when the file can be changed (or created, when it does not exist yet).
    public function writable(IniTarget $target): bool
    {
        $path = $this->path($target);

        if (is_link($path)) {
            return false;
        }

        return is_file($path) ? is_writable($path) : is_dir(dirname($path)) && is_writable(dirname($path));
    }

    /**
     * The whole file as it will look after writing the values.
     *
     * @param  array<string, string>  $values  ini name => value, like ['upload_max_filesize' => '64M']
     */
    public function preview(IniTarget $target, array $values): string
    {
        $path = $this->path($target);
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        $block = $this->block($target, $values);
        $pattern = '/^[;#] '.preg_quote(self::BEGIN, '/').'$.*?^[;#] '.preg_quote(self::END, '/').'$\R?/ms';

        if (preg_match($pattern, $current) === 1) {
            return (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $block), $current, 1);
        }

        $separator = $current === '' || str_ends_with($current, "\n") ? '' : "\n";

        return $current.$separator.($current === '' ? '' : "\n").$block;
    }

    /**
     * Write the block. A copy of the old file goes to the backup folder first.
     *
     * @param  array<string, string>  $values
     * @return array{path: string, backup: string|null}
     */
    public function write(IniTarget $target, array $values): array
    {
        if (! $this->writable($target)) {
            throw new RuntimeException(__('Verso cannot write :file. Use the snippets instead.', ['file' => $target->fileName($this->userIniName)]));
        }

        $path = $this->path($target);
        $content = $this->preview($target, $values);
        $backup = null;

        if (is_file($path)) {
            if (! is_dir($this->backupPath) && ! mkdir($this->backupPath, 0775, true) && ! is_dir($this->backupPath)) {
                throw new RuntimeException(__('The backup folder cannot be created.'));
            }

            $backup = $this->backupPath.DIRECTORY_SEPARATOR.ltrim(basename($path), '.').'.'.Carbon::now()->format('Ymd-His-u').'.bak';
            copy($path, $backup);
        }

        $temporary = $path.'.verso-tmp';
        file_put_contents($temporary, $content);
        @chmod($temporary, 0644);
        rename($temporary, $path);

        return ['path' => $path, 'backup' => $backup];
    }

    /**
     * The managed block alone.
     *
     * @param  array<string, string>  $values
     */
    public function block(IniTarget $target, array $values): string
    {
        $lines = [];

        foreach ($values as $name => $value) {
            $this->guard($name, $value);
            $lines[] = $target === IniTarget::UserIni ? "{$name} = {$value}" : "    php_value {$name} {$value}";
        }

        if ($target === IniTarget::UserIni) {
            return '; '.self::BEGIN."\n; Written by Verso (System settings). Lines inside this block are replaced.\n"
                .implode("\n", $lines)."\n; ".self::END."\n";
        }

        return '# '.self::BEGIN."\n# Written by Verso (System settings). Lines inside this block are replaced.\n<IfModule mod_php.c>\n"
            .implode("\n", $lines)."\n</IfModule>\n# ".self::END."\n";
    }

    protected function guard(string $name, string $value): void
    {
        if (! in_array($name, self::ALLOWED, true) || preg_match('/^(-1|\d{1,9}[KMG]?)$/', $value) !== 1) {
            throw new InvalidArgumentException("The PHP value [{$name}] cannot be written.");
        }
    }
}

<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Updates\Exceptions\UpdateFailed;
use Illuminate\Filesystem\Filesystem;

// Swaps the core files of a site with the files of a release, keeping site data, and puts them back on rollback.
class CoreFiles
{
    protected const MANIFEST = 'manifest.json';

    protected const KEEP = 'keep';

    protected const REPLACE = 'replace';

    protected const MERGE = 'merge';

    /** @var list<string> */
    protected array $keepPaths;

    /** @var list<string> */
    protected array $replacePaths;

    /**
     * @param  list<string>  $keepPaths
     * @param  list<string>  $replacePaths
     */
    public function __construct(protected Filesystem $files, array $keepPaths, array $replacePaths)
    {
        $this->keepPaths = $this->normalize($keepPaths);
        $this->replacePaths = $this->normalize($replacePaths);
    }

    /**
     * The paths (relative, with "/") that the release replaces. Kept paths are never in the list.
     *
     * @return list<string>
     */
    public function plan(string $source): array
    {
        $paths = [];
        $this->walk($source, '', $paths);
        sort($paths);

        return $paths;
    }

    /**
     * Move the current version of each path into the backup folder and write a manifest.
     *
     * @param  list<string>  $paths
     */
    public function backup(string $target, array $paths, string $backupDirectory): void
    {
        $existed = array_values(array_filter($paths, fn (string $path): bool => $this->exists($target.'/'.$path)));

        $this->files->ensureDirectoryExists($backupDirectory.'/files', 0755, true);
        $this->files->put($backupDirectory.'/'.self::MANIFEST, (string) json_encode(['paths' => $paths, 'existed' => $existed], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        foreach ($existed as $path) {
            $this->move($target.'/'.$path, $backupDirectory.'/files/'.$path);
        }
    }

    /**
     * Copy each path of the release into the site.
     *
     * @param  list<string>  $paths
     */
    public function install(string $source, string $target, array $paths): void
    {
        foreach ($paths as $path) {
            $from = $source.'/'.$path;
            $to = $target.'/'.$path;

            $this->delete($to);
            $this->files->ensureDirectoryExists(dirname($to), 0755, true);

            $copied = $this->files->isDirectory($from)
                ? $this->files->copyDirectory($from, $to)
                : $this->files->copy($from, $to);

            if (! $copied) {
                throw UpdateFailed::because(__('Could not copy :path into the site.', ['path' => $path]));
            }
        }
    }

    // Put back every path saved by backup(). Paths that are new in the release are removed.
    public function restore(string $target, string $backupDirectory): void
    {
        $manifest = $this->manifest($backupDirectory);

        foreach ($manifest['paths'] as $path) {
            if (in_array('..', explode('/', $path), true)) {
                continue;
            }

            $saved = $backupDirectory.'/files/'.$path;
            $existed = in_array($path, $manifest['existed'], true);

            if ($existed && ! $this->exists($saved)) {
                continue;
            }

            $this->delete($target.'/'.$path);

            if ($existed) {
                $this->move($saved, $target.'/'.$path);
            }
        }
    }

    // True when the folder holds a file backup that restore() can use.
    public function hasBackup(string $backupDirectory): bool
    {
        return $this->files->exists($backupDirectory.'/'.self::MANIFEST);
    }

    /** @param list<string> $paths */
    protected function walk(string $source, string $relative, array &$paths): void
    {
        $directory = $relative === '' ? $source : $source.'/'.$relative;

        foreach ($this->entries($directory) as $name) {
            $path = $relative === '' ? $name : $relative.'/'.$name;

            match ($this->actionFor($path)) {
                self::REPLACE => $paths[] = $path,
                self::MERGE => $this->files->isDirectory($source.'/'.$path) ? $this->walk($source, $path, $paths) : null,
                default => null,
            };
        }
    }

    // replace: swap the whole path. merge: look inside. keep: never touch.
    protected function actionFor(string $path): string
    {
        if ($this->matchesAny($path, $this->replacePaths)) {
            return self::REPLACE;
        }

        if ($this->isAncestorOfAny($path, $this->replacePaths) || $this->isAncestorOfAny($path, $this->keepPaths)) {
            return self::MERGE;
        }

        return $this->matchesAny($path, $this->keepPaths) ? self::KEEP : self::REPLACE;
    }

    /** @param list<string> $list */
    protected function matchesAny(string $path, array $list): bool
    {
        foreach ($list as $item) {
            if ($path === $item || str_starts_with($path, $item.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $list */
    protected function isAncestorOfAny(string $path, array $list): bool
    {
        foreach ($list as $item) {
            if (str_starts_with($item, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * File and folder names inside a folder, without "." and "..".
     *
     * @return list<string>
     */
    protected function entries(string $directory): array
    {
        $names = scandir($directory);

        if ($names === false) {
            throw UpdateFailed::because(__('Could not read :path.', ['path' => $directory]));
        }

        return array_values(array_diff($names, ['.', '..']));
    }

    protected function exists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    protected function delete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            $this->files->delete($path);
        } elseif (is_dir($path)) {
            $this->files->deleteDirectory($path);
        }

        if ($this->exists($path)) {
            throw UpdateFailed::because(__('Could not remove :path.', ['path' => $path]));
        }
    }

    // Rename when possible. Across disks, copy and then delete.
    protected function move(string $from, string $to): void
    {
        $this->files->ensureDirectoryExists(dirname($to), 0755, true);

        if (@rename($from, $to)) {
            return;
        }

        $copied = is_dir($from) ? $this->files->copyDirectory($from, $to) : $this->files->copy($from, $to);

        if (! $copied) {
            throw UpdateFailed::because(__('Could not move :from to :to.', ['from' => $from, 'to' => $to]));
        }

        $this->delete($from);
    }

    /**
     * @return array{paths: list<string>, existed: list<string>}
     */
    protected function manifest(string $backupDirectory): array
    {
        $file = $backupDirectory.'/'.self::MANIFEST;
        $data = $this->files->exists($file) ? json_decode($this->files->get($file), true) : null;

        if (! is_array($data)) {
            throw UpdateFailed::because(__('The file backup has no manifest, so it cannot be restored.'));
        }

        return [
            'paths' => array_values(array_filter((array) ($data['paths'] ?? []), is_string(...))),
            'existed' => array_values(array_filter((array) ($data['existed'] ?? []), is_string(...))),
        ];
    }

    /**
     * Trim slashes and drop empty entries, so "public/uploads/" and "public/uploads" match.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function normalize(array $paths): array
    {
        return array_values(array_filter(array_map(fn (string $path): string => trim(str_replace('\\', '/', $path), '/'), $paths)));
    }
}

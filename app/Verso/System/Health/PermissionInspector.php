<?php

namespace App\Verso\System\Health;

use App\Verso\I18n\Translate;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

// Checks folders, files and modes of the project, and plans fixes the PHP user can do itself.
class PermissionInspector
{
    public const DIR_MODE = 0775;

    public const ENV_MODE = 0640;

    protected const ENV_MODE_TEXT = '0640';

    /**
     * @param  list<string>  $writable  folders (relative to the project) that must be writable
     * @param  list<string>  $optional  folders that should be writable for some features
     * @param  list<string>  $scan  folders searched for world writable files and wrong owners
     */
    public function __construct(
        protected string $basePath,
        protected array $writable,
        protected array $optional,
        protected array $scan,
        protected int $scanLimit,
        protected ProcessIdentity $identity,
        protected string $envFile = '.env',
        protected string $publicFolder = 'public',
        protected string $storageLinkTarget = 'storage/app/public',
        protected bool $debug = false,
        protected bool $production = false,
        protected string $documentRoot = '',
    ) {
        $this->basePath = rtrim($basePath, '/\\');
    }

    /** @return list<HealthCheck> */
    public function checks(): array
    {
        return [
            ...array_map(fn (string $folder): HealthCheck => $this->checkFolder($folder, true), $this->writable),
            ...array_map(fn (string $folder): HealthCheck => $this->checkFolder($folder, false), $this->optional),
            $this->checkEnv(),
            $this->checkStorageLink(),
            $this->checkDocumentRoot(),
            $this->checkDebug(),
            ...$this->scanFiles(),
        ];
    }

    /** @return list<PlannedFix> every fix of every check, for the dry run */
    public function plan(): array
    {
        $fixes = [];

        foreach ($this->checks() as $check) {
            array_push($fixes, ...$check->fixes);
        }

        return $fixes;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    protected function checkFolder(string $folder, bool $required): HealthCheck
    {
        $path = $this->absolute($folder);
        $key = 'folder:'.$folder;
        $label = __(':folder is writable', ['folder' => $folder]);
        $problem = $required ? HealthStatus::Danger : HealthStatus::Warning;

        if (! file_exists($path)) {
            return new HealthCheck($key, 'folders', $label, $required ? HealthStatus::Danger : HealthStatus::Info,
                $required ? __('The folder is missing.') : __('The folder is missing. It is only needed for some features.'),
                $folder, null, $this->either('0755', '0775'),
                $this->canCreate($path) ? [new PlannedFix(PlannedFix::MKDIR, $folder, self::DIR_MODE)] : [],
                "mkdir -p {$folder} && chmod 775 {$folder}");
        }

        if (is_link($path) && ! $this->inside((string) realpath($path))) {
            return new HealthCheck($key, 'folders', $label, HealthStatus::Info, __('This is a link to a place outside the project, so Verso does not check or change it.'), $folder);
        }

        $mode = $this->mode($path);

        if (! is_writable($path)) {
            $owned = $this->identity->owns($path);

            return new HealthCheck($key, 'folders', $label, $problem,
                $owned ? __('PHP owns the folder but cannot write to it.') : __('The folder belongs to another user, so PHP (:user) cannot write to it.', ['user' => $this->identity->user]),
                $folder, $this->format($mode), $this->either('0755', '0775'),
                $owned ? [new PlannedFix(PlannedFix::CHMOD, $folder, self::DIR_MODE)] : [],
                'sudo chown -R '.$this->identity->chownTarget()." {$folder} && sudo chmod -R ug+rwX {$folder}");
        }

        if (($mode & 0002) !== 0) {
            return new HealthCheck($key, 'folders', __(':folder is world writable', ['folder' => $folder]), HealthStatus::Warning,
                __('Every user on the server can change files here.'), $folder, $this->format($mode), $this->either('0755', '0775'),
                $this->identity->owns($path) ? [new PlannedFix(PlannedFix::CHMOD, $folder, $mode & ~0002)] : [],
                "chmod o-w {$folder}");
        }

        return new HealthCheck($key, 'folders', $label, HealthStatus::Ok, __('Writable.'), $folder, $this->format($mode), $this->either('0755', '0775'));
    }

    protected function checkEnv(): HealthCheck
    {
        $path = $this->absolute($this->envFile);
        $label = __(':file is private', ['file' => $this->envFile]);

        if (! is_file($path)) {
            return new HealthCheck('env', 'files', $label, HealthStatus::Warning, __('The file is missing. Settings come from the server environment only.'), $this->envFile);
        }

        $mode = $this->mode($path);
        $owned = $this->identity->owns($path);
        $fixes = $owned ? [new PlannedFix(PlannedFix::CHMOD, $this->envFile, self::ENV_MODE)] : [];

        if (($mode & 0006) !== 0) {
            return new HealthCheck('env', 'files', $label, HealthStatus::Danger,
                ($mode & 0002) !== 0 ? __('Every user on the server can change this file, which holds your secrets.') : __('Every user on the server can read this file, which holds your secrets.'),
                $this->envFile, $this->format($mode), self::ENV_MODE_TEXT, $fixes, "chmod 640 {$this->envFile}");
        }

        return new HealthCheck('env', 'files', $label, HealthStatus::Ok, __('Only the owner and its group can read it.'), $this->envFile, $this->format($mode), self::ENV_MODE_TEXT);
    }

    protected function checkStorageLink(): HealthCheck
    {
        $link = $this->publicFolder.'/storage';
        $path = $this->absolute($link);
        $target = realpath($this->absolute($this->storageLinkTarget));
        $label = __('public/storage link');

        if (! file_exists($path) && ! is_link($path)) {
            return new HealthCheck('storage-link', 'files', $label, HealthStatus::Warning, __('Uploaded files cannot be shown until public/storage links to storage/app/public.'), $link,
                fixes: [new PlannedFix(PlannedFix::STORAGE_LINK, $link)], command: 'php artisan storage:link');
        }

        $resolved = realpath($path);

        if ($resolved !== false && $resolved === realpath($this->absolute('storage'))) {
            return new HealthCheck('storage-link', 'web', __('The whole storage folder is public'), HealthStatus::Danger,
                __('public/storage points to storage/, so logs, sessions and private files can be downloaded.'), $link,
                command: "rm {$link} && php artisan storage:link");
        }

        if (! is_link($path) || $resolved !== $target) {
            return new HealthCheck('storage-link', 'files', $label, HealthStatus::Warning, __('public/storage exists but does not point to storage/app/public.'), $link,
                command: "rm -r {$link} && php artisan storage:link");
        }

        return new HealthCheck('storage-link', 'files', $label, HealthStatus::Ok, __('Points to storage/app/public.'), $link);
    }

    // The web root must be public/, or .env and storage/ can be downloaded.
    protected function checkDocumentRoot(): HealthCheck
    {
        $exposed = is_file($this->absolute($this->publicFolder.'/'.$this->envFile));
        $root = $this->documentRoot === '' ? false : realpath($this->documentRoot);

        if ($exposed || ($root !== false && $root === realpath($this->basePath))) {
            return new HealthCheck('web-root', 'web', '.env and storage are hidden from the web', HealthStatus::Danger,
                $exposed ? __('A .env file sits inside public/, so anyone can download it.') : __('The web root is the project folder, so anyone can download .env and storage files.'),
                command: $exposed ? "rm {$this->publicFolder}/{$this->envFile}" : __('Point the web server root (root or DocumentRoot) to :path', ['path' => $this->basePath.'/public']));
        }

        return new HealthCheck('web-root', 'web', '.env and storage are hidden from the web', $root === false ? HealthStatus::Info : HealthStatus::Ok,
            $root === false ? __('The web root is not known in this request. Make sure it is the public folder.') : __('The web root is the public folder.'));
    }

    protected function checkDebug(): HealthCheck
    {
        if ($this->debug && $this->production) {
            return new HealthCheck('debug', 'web', __('Debug mode is off in production'), HealthStatus::Danger,
                __('Error pages show code, settings and secrets to visitors.'), command: __('Set APP_DEBUG=false in .env, then run php artisan config:cache'));
        }

        return new HealthCheck('debug', 'web', __('Debug mode is off in production'), $this->debug ? HealthStatus::Info : HealthStatus::Ok,
            $this->debug ? __('Debug mode is on, which is fine outside production.') : __('Debug mode is off.'));
    }

    /**
     * Look for world writable files and files PHP cannot change, without following links.
     *
     * @return list<HealthCheck>
     */
    protected function scanFiles(): array
    {
        $worldWritable = [];
        $foreign = [];
        $seen = 0;

        foreach ($this->scan as $folder) {
            $root = $this->absolute($folder);

            if (! is_dir($root) || is_link($root)) {
                continue;
            }

            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            /** @var SplFileInfo $item */
            foreach ($items as $item) {
                if (++$seen > $this->scanLimit) {
                    break 2;
                }

                if ($item->isLink()) {
                    continue;
                }

                $path = $item->getPathname();
                $mode = $this->mode($path);

                if (($mode & 0002) !== 0) {
                    $worldWritable[] = [$this->relative($path), $mode, $this->identity->owns($path)];
                } elseif (! is_writable($path) && ! $this->identity->owns($path)) {
                    $foreign[] = $this->relative($path);
                }
            }
        }

        $checks = [];
        $limited = $seen > $this->scanLimit ? ' '.Translate::icu('{count, plural, one {Only the first file was checked.} other {Only the first # files were checked.}}', ['count' => $this->scanLimit]) : '';

        $checks[] = $worldWritable === []
            ? new HealthCheck('world-writable', 'files', __('No world writable files'), HealthStatus::Ok, __('No file can be changed by every user on the server.').$limited)
            : new HealthCheck('world-writable', 'files', Translate::icu('{count, plural, one {# world writable file} other {# world writable files}}', ['count' => count($worldWritable)]), HealthStatus::Warning,
                __('Every user on the server can change: :files', ['files' => $this->sample(array_column($worldWritable, 0))]).$limited, null, null, __('files :modes', ['modes' => $this->either('0644', '0664')]),
                array_values(array_map(fn (array $row): PlannedFix => new PlannedFix(PlannedFix::CHMOD, $row[0], $row[1] & ~0002), array_filter($worldWritable, fn (array $row): bool => $row[2]))),
                'find '.implode(' ', $this->scan).' -perm -o+w -not -type l -exec chmod o-w {} +');

        if ($foreign !== []) {
            $checks[] = new HealthCheck('wrong-owner', 'files', Translate::icu('{count, plural, one {# file PHP cannot change} other {# files PHP cannot change}}', ['count' => count($foreign)]), HealthStatus::Warning,
                __('They belong to another user (often root after a command run with sudo): :files', ['files' => $this->sample($foreign)]), null, null, null, [],
                'sudo chown -R '.$this->identity->chownTarget().' '.implode(' ', $this->scan));
        }

        return $checks;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function sample(array $paths): string
    {
        $shown = array_slice($paths, 0, 5);

        $list = implode(', ', $shown);
        $more = count($paths) - count($shown);

        return $more > 0
            ? Translate::icu('{list} and {count, plural, one {# more} other {# more}}.', ['list' => $list, 'count' => $more])
            : $list.'.';
    }

    // A missing folder can be created when its closest existing parent is inside the project and writable.
    protected function canCreate(string $path): bool
    {
        $parent = dirname($path);

        while (! file_exists($parent) && $parent !== dirname($parent)) {
            $parent = dirname($parent);
        }

        $real = realpath($parent);

        return $real !== false && $this->inside($real) && is_writable($real);
    }

    // True when a real path is the project folder or inside it.
    public function inside(string $realPath): bool
    {
        $base = realpath($this->basePath);

        return $base !== false && $realPath !== '' && ($realPath === $base || str_starts_with($realPath, $base.DIRECTORY_SEPARATOR));
    }

    public function absolute(string $relative): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    protected function relative(string $path): string
    {
        $base = $this->basePath.DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    protected function mode(string $path): int
    {
        clearstatcache(true, $path);

        return ((int) @fileperms($path)) & 0777;
    }

    protected function either(string $first, string $second): string
    {
        return __(':first or :second', ['first' => $first, 'second' => $second]);
    }

    protected function format(int $mode): string
    {
        return sprintf('%04o', $mode);
    }
}

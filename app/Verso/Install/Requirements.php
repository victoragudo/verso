<?php

namespace App\Verso\Install;

// Checks the server before install: PHP version, extensions, memory and writable paths.
class Requirements
{
    /**
     * @param  list<string>  $extensions
     * @param  list<string>  $writable
     * @param  list<string>  $imageExtensions
     */
    public function __construct(
        protected string $phpVersion,
        protected array $extensions,
        protected array $writable,
        protected string $basePath,
        protected string $currentPhpVersion = PHP_VERSION,
        protected array $imageExtensions = [],
        protected string $memoryLimit = '',
        protected string $envPath = '',
        protected ?string $currentMemoryLimit = null,
    ) {}

    /**
     * @return list<Requirement>
     */
    public function check(): array
    {
        return array_values(array_filter([
            $this->checkPhp(),
            ...array_map($this->checkExtension(...), $this->extensions),
            $this->imageExtensions === [] ? null : $this->checkImageExtension(),
            $this->memoryLimit === '' ? null : $this->checkMemory(),
            ...array_map($this->checkWritable(...), $this->writable),
            $this->envPath === '' ? null : $this->checkEnvFile(),
        ]));
    }

    public function passes(): bool
    {
        return $this->failures() === [];
    }

    /**
     * @return list<Requirement>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->check(), fn (Requirement $requirement): bool => ! $requirement->passed));
    }

    // Turns "256M" or "1G" into bytes. -1 means no limit.
    public static function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    protected function checkPhp(): Requirement
    {
        return new Requirement(
            group: 'php',
            label: __('PHP :version or newer', ['version' => $this->phpVersion]),
            passed: version_compare($this->currentPhpVersion, $this->phpVersion, '>='),
            detail: __('This server runs PHP :version.', ['version' => $this->currentPhpVersion]),
            fix: __('Ask your host to switch this site to PHP :version or newer.', ['version' => $this->phpVersion]),
        );
    }

    protected function checkExtension(string $extension): Requirement
    {
        $loaded = extension_loaded($extension);

        return new Requirement(
            group: 'extensions',
            label: __(':name extension', ['name' => $extension]),
            passed: $loaded,
            detail: $loaded ? __('Loaded.') : __('Missing. Ask your host to turn it on.'),
            fix: __('Install or enable the :name extension (for example "extension=:name" in php.ini, or the php-:name package), then restart PHP.', ['name' => $extension]),
        );
    }

    protected function checkImageExtension(): Requirement
    {
        $loaded = array_values(array_filter($this->imageExtensions, extension_loaded(...)));
        $names = $this->either($this->imageExtensions);

        return new Requirement(
            group: 'extensions',
            label: __(':name extension', ['name' => $names]),
            passed: $loaded !== [],
            detail: $loaded !== [] ? __('Loaded: :names.', ['names' => implode(', ', $loaded)]) : __('Missing. Images cannot be resized.'),
            fix: __('Enable the gd extension (or imagick) in php.ini, then restart PHP.'),
        );
    }

    protected function checkMemory(): Requirement
    {
        $current = $this->currentMemoryLimit ?? (string) ini_get('memory_limit');
        $bytes = self::bytes($current);

        return new Requirement(
            group: 'php',
            label: __('Memory limit of :limit or more', ['limit' => $this->memoryLimit]),
            passed: $bytes === -1 || $bytes >= self::bytes($this->memoryLimit),
            detail: $bytes === -1 ? __('The memory limit is unlimited.') : __('The memory limit is :limit.', ['limit' => $current]),
            fix: __('Set memory_limit = :limit (or more) in php.ini, then restart PHP.', ['limit' => $this->memoryLimit]),
        );
    }

    protected function checkWritable(string $path): Requirement
    {
        $writable = is_dir($path) && is_writable($path);
        $relative = $this->relative($path);

        return new Requirement(
            group: 'folders',
            label: __(':path is writable', ['path' => $relative]),
            passed: $writable,
            detail: $writable ? __('Writable.') : __('The web server cannot write here.'),
            fix: __('Create the folder and give the web server user write access, for example: chmod -R ug+rw :path', ['path' => $relative]),
        );
    }

    // The .env file must be writable, or its folder when the file does not exist yet.
    protected function checkEnvFile(): Requirement
    {
        $writable = file_exists($this->envPath) ? is_writable($this->envPath) : is_writable(dirname($this->envPath));
        $relative = $this->relative($this->envPath);

        return new Requirement(
            group: 'folders',
            label: __(':path is writable', ['path' => $relative]),
            passed: $writable,
            detail: $writable ? __('Writable.') : __('The installer cannot save your settings.'),
            fix: __('Copy .env.example to :path and give the web server user write access to it, for example: chmod 640 :path', ['path' => $relative]),
        );
    }

    /**
     * @param  list<string>  $names
     */
    protected function either(array $names): string
    {
        $last = array_pop($names);

        return $names === [] ? (string) $last : __(':items or :last', ['items' => implode(', ', $names), 'last' => (string) $last]);
    }

    // Show folder paths relative to the project, like "storage".
    protected function relative(string $path): string
    {
        $base = rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}

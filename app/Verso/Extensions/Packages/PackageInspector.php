<?php

namespace App\Verso\Extensions\Packages;

use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\PluginManifest;
use App\Verso\Theme\Customizer\ThemeSettingsSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use JsonException;
use ZipArchive;

// Opens a plugin or theme zip and checks it is safe before anything is unpacked.
class PackageInspector
{
    // Unix file type bits stored in the high part of the zip external attributes.
    protected const UNIX_TYPE_MASK = 0xF000;

    protected const UNIX_SYMLINK = 0xA000;

    protected const UNIX_FILE = 0x8000;

    protected const UNIX_FOLDER = 0x4000;

    // Files that zip tools on macOS add. They are skipped, not unpacked.
    protected const IGNORED_FOLDERS = ['__MACOSX'];

    protected const IGNORED_FILES = ['.DS_Store'];

    // Blade views end with this, and are the only PHP files a theme may hold.
    protected const BLADE_SUFFIX = '.blade.php';

    public function __construct(protected PackageRules $rules) {}

    // Check the zip and read its manifest. Throws InvalidPackage with every problem found.
    public function inspect(string $zipPath, ExtensionKind $kind): Package
    {
        if (! is_file($zipPath)) {
            throw InvalidPackage::because(__('The package file was not found.'));
        }

        if ((int) filesize($zipPath) > $this->rules->maxUploadBytes) {
            throw InvalidPackage::because(__('The package is bigger than :size.', ['size' => Number::fileSize($this->rules->maxUploadBytes)]));
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw InvalidPackage::because(__('The file is not a valid zip archive.'));
        }

        try {
            return $this->inspectArchive($zip, $kind);
        } finally {
            $zip->close();
        }
    }

    protected function inspectArchive(ZipArchive $zip, ExtensionKind $kind): Package
    {
        [$entries, $unpackedBytes] = $this->readEntries($zip);
        [$root, $files] = $this->stripRootFolder($entries, $kind);

        $manifest = $this->readManifest($zip, $files[$kind->manifestName()], $kind);

        $package = match ($kind) {
            ExtensionKind::Plugin => $this->pluginPackage($manifest, $files, $unpackedBytes),
            ExtensionKind::Theme => $this->themePackage($manifest, $root, $files, $unpackedBytes),
        };

        $this->checkFiles($package, $this->phpFolders($kind, $manifest));

        return $package;
    }

    /**
     * Read every zip entry and refuse unsafe names, links, encrypted files and zip bombs.
     *
     * @return array{0: array<string, int>, 1: int} file path => zip entry index, and the unpacked size
     */
    protected function readEntries(ZipArchive $zip): array
    {
        $count = $zip->count();

        if ($count === 0) {
            throw InvalidPackage::because(__('The zip archive is empty.'));
        }

        if ($count > $this->rules->maxFiles) {
            throw InvalidPackage::because(__('The package holds :count files. The limit is :limit.', ['count' => $count, 'limit' => $this->rules->maxFiles]));
        }

        $errors = [];
        $entries = [];
        $seen = [];
        $unpackedBytes = 0;

        for ($index = 0; $index < $count; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_ENC_RAW);

            if ($stat === false) {
                $errors[] = __('Entry :index of the zip cannot be read.', ['index' => $index]);

                continue;
            }

            $name = (string) $stat['name'];
            $label = $this->label($name);

            if (($problem = $this->nameProblem($name, $label)) !== null) {
                $errors[] = $problem;

                continue;
            }

            if ($stat['encryption_method'] !== 0) {
                $errors[] = __('":name" is encrypted.', ['name' => $label]);

                continue;
            }

            if (($problem = $this->typeProblem($zip, $index, $label)) !== null) {
                $errors[] = $problem;

                continue;
            }

            $path = rtrim($name, '/');
            $key = mb_strtolower($path);

            if (isset($seen[$key])) {
                $errors[] = __('":name" is in the zip more than once.', ['name' => $label]);

                continue;
            }

            $seen[$key] = true;
            $unpackedBytes += (int) $stat['size'];

            if (! str_ends_with($name, '/') && ! $this->isIgnored($path)) {
                $entries[$path] = $index;
            }
        }

        if ($unpackedBytes > $this->rules->maxUnpackedBytes) {
            $errors[] = __('The package unpacks to more than :size.', ['size' => Number::fileSize($this->rules->maxUnpackedBytes)]);
        }

        if ($errors !== []) {
            throw InvalidPackage::because($errors);
        }

        return [$entries, $unpackedBytes];
    }

    // Why an entry name is unsafe, or null when it is fine.
    protected function nameProblem(string $name, string $label): ?string
    {
        if ($name === '' || $name === '/') {
            return __('":name" has an empty name.', ['name' => $label]);
        }

        if (! mb_check_encoding($name, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return __('":name" has a name with control or broken characters.', ['name' => $label]);
        }

        if (str_contains($name, '\\')) {
            return __('":name" uses backslashes in its path.', ['name' => $label]);
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
            return __('":name" is an absolute path.', ['name' => $label]);
        }

        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '..') {
                return __('":name" points outside the package folder.', ['name' => $label]);
            }

            if ($segment === '' || $segment === '.') {
                return __('":name" has an empty or "." folder in its path.', ['name' => $label]);
            }
        }

        return null;
    }

    // Refuse symbolic links and special files. Only plain files and folders are allowed.
    protected function typeProblem(ZipArchive $zip, int $index, string $label): ?string
    {
        $system = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $system, $attributes) || $system !== ZipArchive::OPSYS_UNIX) {
            return null;
        }

        $type = ($attributes >> 16) & self::UNIX_TYPE_MASK;

        return match ($type) {
            0, self::UNIX_FILE, self::UNIX_FOLDER => null,
            self::UNIX_SYMLINK => Translate::text('":name" is a symbolic link.', ['name' => $label]),
            default => Translate::text('":name" is not a regular file.', ['name' => $label]),
        };
    }

    protected function isIgnored(string $path): bool
    {
        $segments = explode('/', $path);

        return in_array($segments[0], self::IGNORED_FOLDERS, true) || in_array(end($segments), self::IGNORED_FILES, true);
    }

    /**
     * Packages may hold their files in one top folder (like "reading-time/verso.json"). Remove it.
     *
     * @param  array<string, int>  $entries
     * @return array{0: ?string, 1: array<string, int>} the top folder name and the files without it
     */
    protected function stripRootFolder(array $entries, ExtensionKind $kind): array
    {
        $manifest = $kind->manifestName();

        if (isset($entries[$manifest])) {
            return [null, $entries];
        }

        $roots = array_unique(array_map(fn (string $path): string => explode('/', $path, 2)[0], array_keys($entries)));

        if (count($roots) !== 1 || ! isset($entries[$roots[0].'/'.$manifest])) {
            throw InvalidPackage::because(__('The package has no :manifest file at its top level.', ['manifest' => $manifest]));
        }

        $root = $roots[0];
        $files = [];

        foreach ($entries as $path => $index) {
            $files[substr($path, strlen($root) + 1)] = $index;
        }

        return [$root, $files];
    }

    /**
     * @return array<mixed>
     */
    protected function readManifest(ZipArchive $zip, int $index, ExtensionKind $kind): array
    {
        $stat = $zip->statIndex($index);

        if ($stat === false || (int) $stat['size'] > $this->rules->maxManifestBytes) {
            throw InvalidPackage::because(__('The :manifest file is missing or too big.', ['manifest' => $kind->manifestName()]));
        }

        try {
            $data = json_decode((string) $zip->getFromIndex($index), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidPackage::because(__('The :manifest file is not valid JSON.', ['manifest' => $kind->manifestName()]));
        }

        if (! is_array($data) || array_is_list($data)) {
            throw InvalidPackage::because(__('The :manifest file must be a JSON object.', ['manifest' => $kind->manifestName()]));
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, int>  $files
     */
    protected function pluginPackage(array $data, array $files, int $unpackedBytes): Package
    {
        $slug = $data['slug'] ?? null;

        if (! is_string($slug) || preg_match(PluginManifest::SLUG_PATTERN, $slug) !== 1) {
            throw InvalidPackage::because(__('The plugin slug is missing or not valid. Use lowercase words joined by dashes.'));
        }

        try {
            $manifest = PluginManifest::fromArray($data, DIRECTORY_SEPARATOR.$slug);
        } catch (InvalidManifest $exception) {
            throw InvalidPackage::because($exception->errors);
        }

        return new Package(
            kind: ExtensionKind::Plugin,
            slug: $manifest->slug,
            name: $manifest->name,
            version: $manifest->version,
            description: $manifest->description,
            author: is_string($data['author'] ?? null) ? $data['author'] : '',
            requires: $manifest->requires,
            permissions: array_map(fn (Capability $capability): string => $capability->value, $manifest->permissions),
            files: $files,
            unpackedBytes: $unpackedBytes,
            network: $manifest->network,
        );
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, int>  $files
     */
    protected function themePackage(array $data, ?string $root, array $files, int $unpackedBytes): Package
    {
        $data['slug'] ??= $root;

        $validator = Validator::make($data, [
            'slug' => ['required', 'string', 'max:80', 'regex:'.PluginManifest::SLUG_PATTERN],
            'name' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'regex:'.PluginManifest::VERSION_PATTERN],
            'description' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:120'],
            'settings' => ['nullable', 'array'],
        ]);

        /** @var list<string> $errors */
        $errors = array_values($validator->errors()->all());

        if (is_array($data['settings'] ?? null)) {
            $errors = [...$errors, ...ThemeSettingsSchema::fromConfig($data['settings'])->errors];
        }

        if ($errors !== []) {
            throw InvalidPackage::because($errors);
        }

        return new Package(
            kind: ExtensionKind::Theme,
            slug: (string) $data['slug'],
            name: (string) $data['name'],
            version: (string) $data['version'],
            description: (string) ($data['description'] ?? ''),
            author: (string) ($data['author'] ?? ''),
            requires: is_string($data['requires'] ?? null) && trim($data['requires']) !== '' ? trim($data['requires']) : '*',
            permissions: [],
            files: $files,
            unpackedBytes: $unpackedBytes,
        );
    }

    /**
     * Folders where PHP may live: the kind folders plus the plugin autoload folders.
     *
     * @param  array<mixed>  $manifest
     * @return list<string>
     */
    protected function phpFolders(ExtensionKind $kind, array $manifest): array
    {
        $folders = $kind->phpFolders();

        if ($kind === ExtensionKind::Plugin) {
            foreach ((array) ($manifest['autoload']['psr-4'] ?? []) as $folder) {
                $folders[] = trim((string) $folder, '/');
            }
        }

        return array_values(array_unique(array_filter($folders, fn (string $folder): bool => $folder !== '')));
    }

    /**
     * Refuse hidden files, unknown file types and PHP outside the allowed folders.
     *
     * @param  list<string>  $phpFolders
     */
    protected function checkFiles(Package $package, array $phpFolders): void
    {
        $errors = [];

        foreach (array_keys($package->files) as $path) {
            if (($problem = $this->fileProblem($path, $package->kind, $phpFolders)) !== null) {
                $errors[] = $problem;
            }
        }

        if ($errors !== []) {
            throw InvalidPackage::because($errors);
        }
    }

    /**
     * @param  list<string>  $phpFolders
     */
    protected function fileProblem(string $path, ExtensionKind $kind, array $phpFolders): ?string
    {
        $label = $this->label($path);

        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.')) {
                return __('":name" is a hidden file or folder.', ['name' => $label]);
            }
        }

        $basename = basename($path);

        if (! str_contains($basename, '.')) {
            return in_array($basename, $this->rules->allowedPlainFiles, true) ? null : Translate::text('":name" has no file extension.', ['name' => $label]);
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->rules->allowedExtensions, true)) {
            return __('":name" has a file type that is not allowed (.:extension).', ['name' => $label, 'extension' => $extension]);
        }

        if ($extension !== 'php') {
            return null;
        }

        if ($kind === ExtensionKind::Theme && ! str_ends_with(strtolower($basename), self::BLADE_SUFFIX)) {
            return __('":name" is a PHP file, but themes may only hold Blade views.', ['name' => $label]);
        }

        foreach ($phpFolders as $folder) {
            if (str_starts_with($path, $folder.'/')) {
                return null;
            }
        }

        return __('":name" is a PHP file outside the allowed folders (:folders).', ['name' => $label, 'folders' => implode(', ', $phpFolders)]);
    }

    // A short, printable version of a zip entry name for error messages.
    protected function label(string $name): string
    {
        $clean = (string) preg_replace('/[^\x20-\x7E]/', '?', $name);

        return mb_strimwidth($clean, 0, 120, '...');
    }
}

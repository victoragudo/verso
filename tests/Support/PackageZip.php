<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use ZipArchive;

// Builds plugin and theme zip files for tests, including broken and unsafe ones.
class PackageZip
{
    /** @var array<string, string> */
    protected array $files = [];

    /** @var list<string> */
    protected array $symlinks = [];

    /** @var list<string> */
    protected array $encrypted = [];

    /** @var list<string> */
    protected array $folders = [];

    // A valid plugin package, with its files inside a top folder named after the slug.
    public static function plugin(string $slug = 'tour-dates', string $version = '1.0.0', array $manifest = [], ?string $root = null): self
    {
        $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $prefix = ($root ?? $slug) === '' ? '' : ($root ?? $slug).'/';

        return (new self)
            ->add($prefix.'verso.json', json_encode([
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'version' => $version,
                'description' => 'Lists upcoming concerts and links to ticket shops.',
                'author' => 'Lucia Moreno',
                'provider' => "Verso\\Plugins\\{$studly}\\{$studly}ServiceProvider",
                'requires' => '^0.1',
                'permissions' => ['manage_terms', 'upload_files'],
                'autoload' => ['psr-4' => ["Verso\\Plugins\\{$studly}\\" => 'src/']],
                ...$manifest,
            ], JSON_PRETTY_PRINT))
            ->add($prefix."src/{$studly}ServiceProvider.php", "<?php\n\nnamespace Verso\\Plugins\\{$studly};\n\nuse App\\Verso\\Plugins\\PluginServiceProvider;\n\nclass {$studly}ServiceProvider extends PluginServiceProvider {}\n")
            ->add($prefix.'README.md', "# Tour Dates\n\nShows your next concerts.\n");
    }

    // A valid theme package with one view, inside a top folder named after the slug.
    public static function theme(string $slug = 'lighthouse', string $version = '1.0.0', array $manifest = []): self
    {
        return (new self)
            ->add($slug.'/theme.json', json_encode([
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'version' => $version,
                'description' => 'A quiet theme for coastal travel stories.',
                'author' => 'Ines Duarte',
                ...$manifest,
            ], JSON_PRETTY_PRINT))
            ->add($slug.'/views/home.blade.php', "@extends('theme::layouts.app')\n@section('content')<p>Lighthouse home</p>@endsection\n")
            ->add($slug.'/assets/theme.css', "body { color: #1f2937; }\n");
    }

    public function add(string $name, string $contents): self
    {
        $this->files[$name] = $contents;

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->files[$name]);

        return $this;
    }

    public function folder(string $name): self
    {
        $this->folders[] = $name;

        return $this;
    }

    // Add an entry marked as a Unix symbolic link pointing at the target.
    public function symlink(string $name, string $target): self
    {
        $this->files[$name] = $target;
        $this->symlinks[] = $name;

        return $this;
    }

    public function encrypt(string $name): self
    {
        $this->encrypted[] = $name;

        return $this;
    }

    // Write the zip and return its path.
    public function build(?string $path = null): string
    {
        $path ??= storage_path('framework/testing/zips/'.uniqid('package-', true).'.zip');
        File::ensureDirectoryExists(dirname($path));

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->folders as $folder) {
            $zip->addEmptyDir($folder);
        }

        foreach ($this->files as $name => $contents) {
            $zip->addFromString($name, $contents);

            if (in_array($name, $this->symlinks, true)) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
            }

            if (in_array($name, $this->encrypted, true)) {
                $zip->setEncryptionName($name, ZipArchive::EM_AES_256, 'harbour-lights');
            }
        }

        $zip->close();

        return $path;
    }
}

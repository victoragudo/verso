<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

// Fixture plugins for the verification tests, plus throwaway plugins written on the fly.
class VerificationFixtures
{
    /** @var list<string> */
    protected static array $created = [];

    // Folder of a fixture plugin in tests/Fixtures/verification.
    public static function path(string $slug): string
    {
        return base_path('tests/Fixtures/verification/'.$slug);
    }

    /**
     * Write a small plugin to a temporary folder. $files maps paths inside the plugin to their contents.
     *
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $manifest
     */
    public static function make(string $slug, array $files = [], array $manifest = []): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $root = storage_path('framework/testing/verify-'.uniqid());
        $path = $root.'/'.$slug;
        self::$created[] = $root;

        $files = [
            'verso.json' => (string) json_encode([
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'version' => '1.0.0',
                'description' => 'Lists upcoming concerts and links to ticket shops.',
                'provider' => "Verso\\Plugins\\{$studly}\\{$studly}ServiceProvider",
                'requires' => '^0.1',
                'permissions' => [],
                'autoload' => ['psr-4' => ["Verso\\Plugins\\{$studly}\\" => 'src/']],
                ...$manifest,
            ], JSON_PRETTY_PRINT),
            "src/{$studly}ServiceProvider.php" => "<?php\n\nnamespace Verso\\Plugins\\{$studly};\n\nuse App\\Verso\\Plugins\\PluginServiceProvider;\n\nclass {$studly}ServiceProvider extends PluginServiceProvider {}\n",
            ...$files,
        ];

        foreach ($files as $name => $contents) {
            File::ensureDirectoryExists(dirname($path.'/'.$name));
            File::put($path.'/'.$name, $contents);
        }

        return $path;
    }

    // Copy a fixture plugin into a plugins folder, like an installed plugin.
    public static function install(string $slug, string $pluginsPath): string
    {
        File::copyDirectory(self::path($slug), $pluginsPath.'/'.$slug);

        return $pluginsPath.'/'.$slug;
    }

    // A zip of a fixture plugin, with its files inside a folder named after the slug.
    public static function zip(string $slug): PackageZip
    {
        $zip = new PackageZip;

        foreach (File::allFiles(self::path($slug)) as $file) {
            /** @var SplFileInfo $file */
            $zip->add($slug.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()), $file->getContents());
        }

        return $zip;
    }

    // Delete every plugin made with make().
    public static function cleanup(): void
    {
        foreach (self::$created as $root) {
            File::deleteDirectory($root);
        }

        self::$created = [];
    }
}

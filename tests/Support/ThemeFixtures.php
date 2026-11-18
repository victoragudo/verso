<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

// Fixture themes for the theme verification tests, copied to temporary folders when a test changes them.
class ThemeFixtures
{
    /** @var list<string> */
    protected static array $created = [];

    // Folder of a fixture theme in tests/Fixtures/themes.
    public static function path(string $slug): string
    {
        return base_path('tests/Fixtures/themes/'.$slug);
    }

    /**
     * Copy a fixture theme to a temporary folder and add or replace files in it.
     *
     * @param  array<string, string>  $files  path inside the theme => content
     */
    public static function copy(string $slug, array $files = []): string
    {
        $root = storage_path('framework/testing/theme-verify-'.Str::lower(Str::random(16)));
        $path = $root.'/'.$slug;
        self::$created[] = $root;

        File::copyDirectory(self::path($slug), $path);

        foreach ($files as $name => $contents) {
            File::ensureDirectoryExists(dirname($path.'/'.$name));
            File::put($path.'/'.$name, $contents);
        }

        return $path;
    }

    // Copy a fixture theme and give it a PNG screenshot of the given size.
    public static function withScreenshot(string $slug, int $width = 1200, int $height = 900, string $file = 'screenshot.png'): string
    {
        $path = self::copy($slug);

        File::put($path.'/'.$file, self::png($width, $height));

        return $path;
    }

    // A small PNG image with a sky and a sea band, like a theme preview.
    public static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 224, 242, 254));
        imagefilledrectangle($image, 0, intdiv($height, 2), $width, $height, (int) imagecolorallocate($image, 15, 118, 110));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    // A zip of a theme folder (a fixture slug or a full path), with its files inside a folder named after the slug.
    public static function zip(string $slugOrPath): PackageZip
    {
        $path = is_dir($slugOrPath) ? $slugOrPath : self::path($slugOrPath);
        $slug = basename($path);
        $zip = new PackageZip;

        foreach (File::allFiles($path) as $file) {
            /** @var SplFileInfo $file */
            $zip->add($slug.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()), $file->getContents());
        }

        return $zip;
    }

    // Delete every copy made by copy() and withScreenshot().
    public static function cleanup(): void
    {
        foreach (self::$created as $root) {
            File::deleteDirectory($root);
        }

        self::$created = [];
    }
}

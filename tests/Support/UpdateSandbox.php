<?php

namespace Tests\Support;

use App\Verso\Updates\Feed\Release;
use App\Verso\Updates\Updater\CoreUpdater;
use App\Verso\Updates\Updater\DatabaseBackup;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use ZipArchive;

// A fake Verso site in a temporary folder, plus release packages, for updater tests. Real project files are never touched.
class UpdateSandbox
{
    public const CONNECTION = 'update_sandbox';

    public const SITE_URL = 'https://harbor-notes.example';

    public const DOWNLOADS = 'https://downloads.verso-cms.example/releases';

    public function __construct(public readonly string $root) {}

    // Build the fake site and point the updater config at it.
    public static function create(string $version = '0.1.0'): self
    {
        $sandbox = new self(sys_get_temp_dir().'/verso-update-'.bin2hex(random_bytes(6)));
        $sandbox->writeSite($version);

        config([
            'verso.version' => $version,
            'verso.updates.install_path' => $sandbox->site(),
            'verso.updates.storage_path' => $sandbox->storage(),
            'verso.updates.php_binary' => 'php',
            'verso.updates.public_key' => '',
            'verso.updates.health_check.base_url' => self::SITE_URL,
            'verso.updates.feed_url' => 'https://api.github.com/repos/victoragudo/verso/releases',
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => $sandbox->site('database/database.sqlite'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        app()->bind(DatabaseBackup::class, fn ($app): DatabaseBackup => new DatabaseBackup(
            db: $app->make(DatabaseManager::class),
            files: $app->make(Filesystem::class),
            connection: self::CONNECTION,
            binaries: [],
            timeout: 60,
        ));

        return $sandbox;
    }

    public function site(string $path = ''): string
    {
        return $this->root.'/site'.($path === '' ? '' : '/'.$path);
    }

    public function storage(string $path = ''): string
    {
        return $this->root.'/storage'.($path === '' ? '' : '/'.$path);
    }

    public function updater(): CoreUpdater
    {
        return app(CoreUpdater::class);
    }

    // Read a file of the fake site.
    public function read(string $path): string
    {
        return (string) file_get_contents($this->site($path));
    }

    /**
     * Rows of the guestbook table in the site database.
     *
     * @return list<string>
     */
    public function guestbook(): array
    {
        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION)->table('guestbook')->orderBy('id')->pluck('name')->map(fn ($name): string => (string) $name)->all();
    }

    /**
     * Build a release zip with new core files. Returns the zip bytes.
     *
     * @param  array<string, string>  $extraFiles
     */
    public function package(string $version, array $extraFiles = [], bool $topFolder = true): string
    {
        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php // Verso {$version}\n",
            'VERSION' => $version."\n",
            'app/Http/Kernel.php' => "<?php // kernel {$version}\n",
            'app/Verso/Updates/Banner.php' => "<?php // banner {$version}\n",
            'routes/web.php' => "<?php // routes {$version}\n",
            'public/index.php' => "<?php // front controller {$version}\n",
            'public/build/manifest.json' => '{"app.js":"app-'.$version.'.js"}',
            'database/migrations/2026_12_01_000000_add_reading_time.php' => "<?php // migration {$version}\n",
            'vendor/autoload.php' => "<?php // autoload {$version}\n",
            'themes/default/theme.json' => '{"name":"Default","version":"'.$version.'"}',
            'themes/aurora/theme.json' => '{"name":"Aurora from the package"}',
            'storage/app/notes.txt' => 'package storage must never be copied',
            '.env' => 'APP_KEY=from-the-package',
            'plugins/reading-time/verso.json' => '{"name":"Reading Time from the package"}',
            ...$extraFiles,
        ];

        $path = $this->root.'/build-'.bin2hex(random_bytes(4)).'.zip';
        File::ensureDirectoryExists(dirname($path));
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString(($topFolder ? "verso-{$version}/" : '').$name, $contents);
        }

        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    public static function packageUrl(string $version): string
    {
        return self::DOWNLOADS."/v{$version}/verso-{$version}.zip";
    }

    public static function release(string $version, bool $signed = false, ?int $size = null): Release
    {
        return new Release(
            version: $version,
            name: "Verso {$version}",
            notes: "## What is new\n\n- Faster image pages",
            publishedAt: '2026-11-20T09:30:00Z',
            preRelease: false,
            security: false,
            packageUrl: self::packageUrl($version),
            checksumUrl: self::packageUrl($version).'.sha256',
            signatureUrl: $signed ? self::packageUrl($version).'.sig' : null,
            packageSize: $size,
        );
    }

    /**
     * One release in the GitHub releases API format.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function githubRelease(string $version, array $overrides = []): array
    {
        $zip = "verso-{$version}.zip";

        return [
            'tag_name' => 'v'.$version,
            'name' => "Verso {$version}",
            'body' => "## Changes\n\n- Better editor shortcuts\n- Fixed the media picker search",
            'draft' => false,
            'prerelease' => false,
            'published_at' => '2026-11-20T09:30:00Z',
            'html_url' => "https://github.com/victoragudo/verso/releases/tag/v{$version}",
            'assets' => [
                ['name' => $zip, 'browser_download_url' => self::packageUrl($version), 'size' => 18_350_112],
                ['name' => $zip.'.sha256', 'browser_download_url' => self::packageUrl($version).'.sha256', 'size' => 85],
            ],
            ...$overrides,
        ];
    }

    public function destroy(): void
    {
        DB::purge(self::CONNECTION);
        File::deleteDirectory($this->root);
    }

    protected function writeSite(string $version): void
    {
        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php // Verso {$version}\n",
            'VERSION' => $version."\n",
            'app/Http/Kernel.php' => "<?php // kernel {$version}\n",
            'app/Legacy/OldWidget.php' => "<?php // removed in the next release\n",
            'routes/web.php' => "<?php // routes {$version}\n",
            'public/index.php' => "<?php // front controller {$version}\n",
            'public/favicon-custom.ico' => 'site owner icon',
            'public/uploads/2026/11/harbor-sunrise.jpg' => 'jpeg bytes of the harbor',
            'database/migrations/2026_11_16_100000_add_role_to_users_table.php' => "<?php // old migration\n",
            'vendor/autoload.php' => "<?php // autoload {$version}\n",
            'themes/default/theme.json' => '{"name":"Default","version":"'.$version.'"}',
            'themes/aurora/theme.json' => '{"name":"Aurora by Lena Fischer"}',
            'storage/app/notes.txt' => 'notes written by the site',
            '.env' => 'APP_KEY=base64:site-secret-key',
            'plugins/reading-time/verso.json' => '{"name":"Reading Time"}',
        ];

        foreach ($files as $name => $contents) {
            File::ensureDirectoryExists(dirname($this->site($name)));
            file_put_contents($this->site($name), $contents);
        }

        $pdo = new PDO('sqlite:'.$this->site('database/database.sqlite'));
        $pdo->exec('CREATE TABLE guestbook (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO guestbook (name) VALUES ('Marta Ruiz'), ('Jonas Becker')");
    }
}

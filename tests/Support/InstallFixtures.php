<?php

namespace Tests\Support;

use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Support\Str;

// Gives each installer test its own lock file, progress file and .env file.
class InstallFixtures
{
    public static function useFreshLockFile(): string
    {
        return self::fresh()['lock'];
    }

    /**
     * @return array{folder: string, lock: string, state: string, env: string}
     */
    public static function fresh(): array
    {
        $folder = storage_path('framework/testing/install-'.Str::lower(Str::random(10)));
        @mkdir($folder, 0755, true);
        copy(base_path('.env.example'), $folder.'/.env');

        $paths = [
            'folder' => $folder,
            'lock' => $folder.'/verso-installed.lock',
            'state' => $folder.'/progress.json',
            'env' => $folder.'/.env',
        ];

        config([
            'verso.install.lock_file' => $paths['lock'],
            'verso.install.state_file' => $paths['state'],
            'verso.install.env_file' => $paths['env'],
        ]);

        app()->forgetInstance(InstallState::class);
        app()->forgetInstance(InstallProgress::class);

        return $paths;
    }

    /**
     * Answers for every wizard step, like a user would give them.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function answers(string $sqlitePath): array
    {
        return [
            'welcome' => ['language' => 'en'],
            'requirements' => ['checked_at' => now()->toIso8601String()],
            'database' => ['driver' => 'sqlite', 'database' => $sqlitePath, 'prefix' => ''],
            'site' => [
                'site_name' => 'Lisbon Food Notes',
                'tagline' => 'Where to eat in Lisbon, street by street',
                'site_url' => 'https://lisbonfood.example',
                'locale' => 'pt',
                'timezone' => 'Europe/Lisbon',
                'admin_name' => 'Inês Duarte',
                'admin_email' => 'ines@lisbonfood.example',
                'admin_password' => 'sardines-and-vinho-2026',
            ],
            'services' => ['mailer' => 'log', 'cache_driver' => 'file', 'session_driver' => 'file', 'queue_driver' => 'sync', 'storage' => 'local'],
            'content' => ['demo' => false, 'theme' => 'default', 'plugins' => ['reading-time']],
            'review' => [],
        ];
    }
}

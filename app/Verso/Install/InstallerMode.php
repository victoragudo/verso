<?php

namespace App\Verso\Install;

use Illuminate\Contracts\Config\Repository as Config;

// While Verso is not installed, sessions, cache and queue must not touch the database.
class InstallerMode
{
    protected bool $active = false;

    public function __construct(protected Config $config) {}

    // Switch to file sessions (encrypted, they may hold passwords), file cache and the sync queue.
    // The session cookie gets a fixed name: by default Laravel builds it from APP_NAME, which the
    // install writes to .env, and a new name would lose the wizard answers halfway through.
    public function activate(): void
    {
        $drivers = (array) $this->config->get('verso.install.safe_drivers', []);

        $this->config->set([
            'session.driver' => $drivers['session'] ?? 'file',
            'session.cookie' => (string) $this->config->get('verso.install.session_cookie', 'verso_install_session'),
            'session.encrypt' => true,
            'cache.default' => $drivers['cache'] ?? 'file',
            'queue.default' => $drivers['queue'] ?? 'sync',
        ]);

        $this->active = true;
    }

    public function active(): bool
    {
        return $this->active;
    }
}

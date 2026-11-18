<?php

namespace App\Verso\Install;

use Illuminate\Contracts\Config\Repository as Config;

// While Verso is not installed, sessions, cache and queue must not touch the database.
class InstallerMode
{
    protected bool $active = false;

    public function __construct(protected Config $config) {}

    // Switch to file sessions (encrypted, they may hold passwords), file cache and the sync queue.
    public function activate(): void
    {
        $drivers = (array) $this->config->get('verso.install.safe_drivers', []);

        $this->config->set([
            'session.driver' => $drivers['session'] ?? 'file',
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

<?php

namespace App\Verso\Extensions\Actions;

use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\PluginVerifier;
use App\Verso\Plugins\PluginManager;

// Verifies a plugin that is already installed, for the plugin detail page.
class VerifyInstalledPlugin
{
    public function __construct(
        protected PluginVerifier $verifier,
        protected PluginManager $plugins,
    ) {}

    public function handle(string $slug, ?int $userId = null): PluginVerification
    {
        $plugin = $this->plugins->get($slug);
        $report = $this->verifier->verify($plugin->path);

        $verification = PluginVerification::record($report, PluginVerification::SOURCE_INSTALLED, null, null, $userId);
        $verification->markInstalled();

        return $verification;
    }
}

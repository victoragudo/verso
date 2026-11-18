<?php

namespace App\Verso\Extensions\Actions;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\ThemeVerifier;
use App\Verso\Theme\Theme;

// Verifies a theme that is already installed, for the theme detail page.
class VerifyInstalledTheme
{
    public function __construct(protected ThemeVerifier $verifier) {}

    public function handle(Theme $theme, ?int $userId = null): PluginVerification
    {
        $report = $this->verifier->verify($theme->path);

        $verification = PluginVerification::record($report, PluginVerification::SOURCE_INSTALLED, null, null, $userId, ExtensionKind::Theme);
        $verification->markInstalled();

        return $verification;
    }
}

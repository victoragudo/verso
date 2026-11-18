<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Verification\ThemeVerifier;
use App\Verso\Extensions\Verification\VerificationReport;
use Illuminate\Console\Command;

// Verifies a theme folder or zip from the command line and prints the findings.
class VerifyThemeCommand extends Command
{
    use PrintsVerificationReport;

    protected $signature = 'verso:verify-theme
        {path : Theme folder or zip file}
        {--json : Print the report as JSON}
        {--no-sandbox : Skip rendering the theme in a separate process}';

    protected $description = 'Check a theme for missing views, risky code and quality problems, and render it with demo content, before you install it';

    public function handle(ThemeVerifier $verifier): int
    {
        $withSandbox = ! $this->option('no-sandbox');

        return $this->verifyPath(ExtensionKind::Theme, fn (string $folder): VerificationReport => $verifier->verify($folder, $withSandbox));
    }
}

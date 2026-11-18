<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Verification\PluginVerifier;
use App\Verso\Extensions\Verification\VerificationReport;
use Illuminate\Console\Command;

// Verifies a plugin folder or zip from the command line and prints the findings.
class VerifyPluginCommand extends Command
{
    use PrintsVerificationReport;

    protected $signature = 'verso:verify-plugin
        {path : Plugin folder or zip file}
        {--json : Print the report as JSON}
        {--no-sandbox : Skip booting the plugin in a separate process}';

    protected $description = 'Scan a plugin for risky code, permission and compatibility problems before you install it';

    public function handle(PluginVerifier $verifier): int
    {
        $withSandbox = ! $this->option('no-sandbox');

        return $this->verifyPath(ExtensionKind::Plugin, fn (string $folder): VerificationReport => $verifier->verify($folder, $withSandbox));
    }
}

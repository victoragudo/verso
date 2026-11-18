<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Enums\Severity;

// Everything the scanners learn about a plugin: findings plus facts used by later checks.
final class ScanResult
{
    /** @var list<Finding> */
    public array $findings = [];

    /** @var array<string, array{file: string, line: int}> capability => first place it is used */
    public array $capabilities = [];

    /** @var array<string, array{file: string, line: int}> host => first place it is named */
    public array $hosts = [];

    /** @var list<array{file: string, line: int}> places that open network connections */
    public array $networkCalls = [];

    /** @var array<string, array{file: string, line: int}> class name => place that declares it */
    public array $classes = [];

    /** @var array<string, array{file: string, line: int}> table => migration that creates it */
    public array $tables = [];

    /** @var array<string, string> config key => file that adds it */
    public array $configKeys = [];

    /**
     * @param  array<string, string|int>  $replace
     */
    public function add(string $rule, Severity $severity, string $message, ?string $file = null, ?int $line = null, array $replace = []): void
    {
        $this->findings[] = new Finding($rule, $severity, $message, $file, $line, $replace);
    }

    public function useCapability(string $capability, string $file, int $line): void
    {
        $this->capabilities[$capability] ??= ['file' => $file, 'line' => $line];
    }

    public function nameHost(string $host, string $file, int $line): void
    {
        $this->hosts[strtolower($host)] ??= ['file' => $file, 'line' => $line];
    }

    public function callNetwork(string $file, int $line): void
    {
        $this->networkCalls[] = ['file' => $file, 'line' => $line];
    }

    // True when the given file opens a network connection.
    public function callsNetworkIn(string $file): bool
    {
        foreach ($this->networkCalls as $call) {
            if ($call['file'] === $file) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Verso\Extensions\Verification\Checks;

use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Plugins\PluginManifest;

// Compares the permissions and network hosts in verso.json with what the code really uses.
class PermissionCheck
{
    /**
     * Add permission findings to the scan and return the facts for the report.
     *
     * @return array{declared: list<string>, used: list<string>, network: list<string>, hosts: list<string>}
     */
    public function check(PluginManifest $manifest, ScanResult $scan): array
    {
        $declared = array_map(fn (Capability $capability): string => $capability->value, $manifest->permissions);

        foreach ($scan->capabilities as $capability => $place) {
            if (! in_array($capability, $declared, true)) {
                $scan->add('permission.undeclared', Severity::Medium, message: 'The code needs ":capability", but verso.json does not ask for it.', file: $place['file'], line: $place['line'], replace: ['capability' => $capability]);
            }
        }

        foreach (array_diff($declared, array_keys($scan->capabilities)) as $capability) {
            $scan->add('permission.unused', Severity::Info, message: 'verso.json asks for ":capability", but no code that needs it was found.', file: 'verso.json', replace: ['capability' => $capability]);
        }

        $this->checkNetwork($manifest, $scan);

        $used = array_keys($scan->capabilities);
        sort($used);
        $hosts = array_keys($scan->hosts);
        sort($hosts);

        return [
            'declared' => $declared,
            'used' => $used,
            'network' => $manifest->network,
            'hosts' => $hosts,
        ];
    }

    /**
     * True when the host is listed. "*.example.org" allows example.org and every sub domain.
     *
     * @param  list<string>  $patterns
     */
    public function allows(array $patterns, string $host): bool
    {
        $host = strtolower($host);

        foreach ($patterns as $pattern) {
            $pattern = strtolower((string) $pattern);

            if ($pattern === $host || (str_starts_with($pattern, '*.') && ($host === substr($pattern, 2) || str_ends_with($host, substr($pattern, 1))))) {
                return true;
            }
        }

        return false;
    }

    // Every host the code talks to must be in the "network" list of verso.json.
    protected function checkNetwork(PluginManifest $manifest, ScanResult $scan): void
    {
        foreach ($scan->hosts as $host => $place) {
            if (! $this->allows($manifest->network, $host)) {
                $scan->add('permission.undeclared-network', Severity::Medium, message: 'The code connects to :host, which is not in the network list of verso.json.', file: $place['file'], line: $place['line'], replace: ['host' => $host]);
            }
        }

        if ($scan->networkCalls !== [] && $scan->hosts === [] && $manifest->network === []) {
            $first = $scan->networkCalls[0];
            $scan->add('permission.undeclared-network', Severity::Medium, message: 'The code opens network connections, but verso.json declares no network hosts.', file: $first['file'], line: $first['line']);
        }
    }
}

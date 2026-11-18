<?php

namespace App\Verso\Extensions\Verification\Scanners;

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\ScanResult;

// Scans JavaScript files and Blade views for code built from strings, injected scripts and external script URLs.
class AssetScanner
{
    // JavaScript that runs code built from strings.
    protected const JS_EVAL_PATTERNS = [
        '/\beval\s*\(/' => ['message' => 'eval() runs code built at runtime.'],
        '/\bnew\s+Function\s*\(/' => ['message' => 'new Function() runs code built from a string.'],
        '/\bset(?:Timeout|Interval)\s*\(\s*[\'"`]/' => ['message' => 'A timer is given code as a string.'],
    ];

    // JavaScript that adds scripts or raw HTML to the page.
    protected const JS_INJECTION_PATTERNS = [
        '/createElement\s*\(\s*[\'"]script[\'"]\s*\)/i' => ['message' => 'Adds a script element to the page at runtime.'],
        '/\bdocument\.write(?:ln)?\s*\(/' => ['message' => 'document.write() injects raw HTML into the page.'],
    ];

    // URLs in script sources, dynamic imports and requests.
    protected const SCRIPT_SRC_PATTERN = '~<script\b[^>]*\bsrc\s*=\s*["\']?((?:https?:)?//([a-z0-9.-]+)[^"\'\s>]*)~i';

    protected const IMPORT_URL_PATTERN = '~\bimport\s*\(?\s*[\'"`]((?:https?:)?//([a-z0-9.-]+)[^\'"`]*)~i';

    protected const REQUEST_URL_PATTERN = '~\b(?:fetch|sendBeacon|open|WebSocket|EventSource)\s*\(\s*(?:[\'"`][A-Z]+[\'"`]\s*,\s*)?[\'"`]((?:https?|wss?):)?//([a-z0-9.-]+)~i';

    public function __construct(protected PhpScanner $php) {}

    public function scanScript(string $file, string $code, ScanResult $result): void
    {
        foreach ($this->lines($code) as $number => $line) {
            $this->checkScriptLine($file, $number, $line, $result, 'js');
        }
    }

    public function scanBlade(string $file, string $code, ScanResult $result): void
    {
        $this->php->scan($file, $code, $result);
        $this->scanPhpBlocks($file, $code, $result);

        $insideScript = false;

        foreach ($this->lines($code) as $number => $line) {
            if (str_contains($line, '{!!')) {
                $result->add('blade.raw-output', Severity::Low, message: 'Prints data without escaping with {!! !!}.', file: $file, line: $number);
            }

            if (preg_match('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $line) === 1) {
                $insideScript = true;
                $result->add('blade.inline-script', Severity::Low, message: 'An inline script runs on every page that uses this view.', file: $file, line: $number);
            }

            if ($insideScript && preg_match('/\{\{|\{!!|@json|@js\b/', $line) === 1) {
                $result->add('blade.script-injection', Severity::Medium, message: 'Data is printed inside an inline script, which can inject code.', file: $file, line: $number);
            }

            if (str_contains(strtolower($line), '</script')) {
                $insideScript = false;
            }

            $this->checkScriptLine($file, $number, $line, $result, 'blade');
        }
    }

    // Rules shared by JavaScript files and Blade views.
    protected function checkScriptLine(string $file, int $number, string $line, ScanResult $result, string $prefix): void
    {
        foreach (self::JS_EVAL_PATTERNS as $pattern => ['message' => $message]) {
            if (preg_match($pattern, $line) === 1) {
                $result->add($prefix.'.eval', Severity::Medium, $message, $file, $number);
            }
        }

        foreach (self::JS_INJECTION_PATTERNS as $pattern => ['message' => $message]) {
            if (preg_match($pattern, $line) === 1) {
                $result->add($prefix.'.script-injection', Severity::Medium, $message, $file, $number);
            }
        }

        foreach ([self::SCRIPT_SRC_PATTERN, self::IMPORT_URL_PATTERN] as $pattern) {
            if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    $result->add($prefix.'.external-script', Severity::Medium, message: 'Loads a script from :host.', file: $file, line: $number, replace: ['host' => $match[2]]);
                    $result->nameHost($match[2], $file, $number);
                }
            }
        }

        if (preg_match_all(self::REQUEST_URL_PATTERN, $line, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $result->callNetwork($file, $number);
                $result->add($prefix.'.external-request', Severity::Low, message: 'Sends browser requests to :host.', file: $file, line: $number, replace: ['host' => $match[2]]);
                $result->nameHost($match[2], $file, $number);
            }
        }
    }

    // Scan the code inside @php ... @endphp blocks as PHP, keeping the line numbers of the view.
    protected function scanPhpBlocks(string $file, string $code, ScanResult $result): void
    {
        if (preg_match_all('/@php\b(?!\s*\()(.*?)@endphp/s', $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        foreach ($matches[1] as [$body, $offset]) {
            $line = substr_count(substr($code, 0, (int) $offset), "\n");
            $this->php->scan($file, "<?php\n".$body, $result, $line - 1);
        }
    }

    /**
     * @return array<int, string> line number => text
     */
    protected function lines(string $code): array
    {
        $lines = preg_split('/\R/', $code) ?: [];

        return array_combine(range(1, max(1, count($lines))), $lines === [] ? [''] : $lines);
    }
}

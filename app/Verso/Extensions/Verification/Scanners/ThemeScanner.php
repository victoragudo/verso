<?php

namespace App\Verso\Extensions\Verification\Scanners;

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\ScanResult;

// Theme rules for Blade views and stylesheets: unsafe output, inline handlers, outside hosts, fonts, forms and quality.
class ThemeScanner
{
    // Hooks and markup a good theme layout has. Keys are stored in the report.
    public const QUALITY_MARKERS = [
        'seo' => '/<x-verso-seo\b/i',
        'head' => '/<x-verso-head\b/i',
        'footer' => '/<x-verso-footer\b/i',
        'skipLink' => '/<a\b[^>]*href\s*=\s*["\']#[^"\']+["\'][^>]*(?:class\s*=\s*["\'][^"\']*skip|>\s*skip\b)/i',
        'lang' => '/<html\b[^>]*\blang\s*=/i',
    ];

    // Raw output of data a visitor or a request controls.
    protected const USER_DATA_PATTERN = '/request\s*\(|\$request\b|Request::|\$_(?:GET|POST|REQUEST|COOKIE|SERVER|FILES)\b|\bold\s*\(|->(?:input|query|cookie|header)\s*\(|\bauth\s*\(\s*\)\s*->\s*user|Auth::user|\$comment\b|->(?:author_name|author_email|author_url)\b/i';

    protected const RAW_OUTPUT_PATTERN = '/\{!!(.*?)!!\}/s';

    protected const TAG_PATTERN = '/<([a-z][a-z0-9-]*)\b[^>]*>/is';

    protected const URL_HOST_PATTERN = '~^\s*(?:https?:)?//([a-z0-9.-]+)~i';

    protected const CSS_IMPORT_PATTERN = '~@import\s+(?:url\(\s*)?["\']?((?:https?:)?//([a-z0-9.-]+)[^"\')\s;]*)~i';

    protected const CSS_URL_PATTERN = '~url\(\s*["\']?((?:https?:)?//([a-z0-9.-]+)[^"\')\s]*)~i';

    /**
     * @param  list<string>  $fontHosts  hosts that serve web fonts
     */
    public function __construct(protected array $fontHosts) {}

    /**
     * Line rules for one Blade view. $declared lists the outside hosts named in theme.json.
     *
     * @param  list<string>  $declared
     */
    public function scanBlade(string $file, string $code, ScanResult $result, array $declared): void
    {
        $this->checkRawOutput($file, $code, $result);

        foreach ($this->tags($code) as [$name, $tag, $line]) {
            $this->checkTag($file, $name, $tag, $line, $result, $declared);
        }

        $this->checkStyles($file, $code, $result, $declared);
    }

    /**
     * Imports and fonts from other hosts in a stylesheet.
     *
     * @param  list<string>  $declared
     */
    public function scanStylesheet(string $file, string $code, ScanResult $result, array $declared): void
    {
        $this->checkStyles($file, $code, $result, $declared);
    }

    /**
     * Which quality markers appear in the code.
     *
     * @return array<string, bool>
     */
    public function markers(string $code): array
    {
        $code = $this->masked($code);
        $found = [];

        foreach (self::QUALITY_MARKERS as $key => $pattern) {
            $found[$key] = preg_match($pattern, $code) === 1;
        }

        return $found;
    }

    // Every {!! !!} is reported. Data from the request or from visitors is high risk, because it allows script injection.
    protected function checkRawOutput(string $file, string $code, ScanResult $result): void
    {
        if (preg_match_all(self::RAW_OUTPUT_PATTERN, $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        foreach ($matches[1] as [$expression, $offset]) {
            $line = $this->lineAt($code, (int) $offset);

            if (preg_match(self::USER_DATA_PATTERN, $expression) === 1) {
                $result->add('theme.raw-user-output', Severity::High, message: 'Prints request or visitor data without escaping, which allows script injection. Use {{ }} instead.', file: $file, line: $line);
            } else {
                $result->add('theme.raw-output', Severity::Info, message: 'Prints data without escaping with {!! !!}. Make sure it is HTML that Verso already made safe.', file: $file, line: $line);
            }
        }
    }

    /**
     * Rules for one HTML tag.
     *
     * @param  list<string>  $declared
     */
    protected function checkTag(string $file, string $name, string $tag, int $line, ScanResult $result, array $declared): void
    {
        if (preg_match('/\s(on[a-z]+)\s*=\s*["\']/i', $tag, $handler) === 1) {
            $result->add('theme.inline-handler', Severity::Medium, message: 'The :attribute attribute runs inline JavaScript. Use a script file and addEventListener.', file: $file, line: $line, replace: ['attribute' => $handler[1]]);
        }

        match ($name) {
            'script' => $this->checkScript($file, $tag, $line, $result, $declared),
            'link' => $this->checkLink($file, $tag, $line, $result, $declared),
            'form' => $this->checkForm($file, $tag, $line, $result),
            'img' => $this->checkImage($file, $tag, $line, $result),
            'html' => $this->checkHtml($file, $tag, $line, $result),
            default => null,
        };
    }

    /**
     * @param  list<string>  $declared
     */
    protected function checkScript(string $file, string $tag, int $line, ScanResult $result, array $declared): void
    {
        $source = $this->attribute($tag, 'src');

        if ($source === null) {
            return;
        }

        $host = $this->host($source);

        if ($host !== null && ! $this->isDeclared($host, $declared)) {
            $result->add('theme.external-script', Severity::Medium, message: 'Loads a script from :host, which is not declared in the "network" list of theme.json.', file: $file, line: $line, replace: ['host' => $host]);
            $result->nameHost($host, $file, $line);
        }

        if (preg_match('/\s(?:defer|async)\b|\stype\s*=\s*["\']module["\']/i', $tag) !== 1) {
            $result->add('theme.render-blocking-script', Severity::Low, message: 'A script without defer or async stops the page from rendering until it loads.', file: $file, line: $line);
        }
    }

    /**
     * @param  list<string>  $declared
     */
    protected function checkLink(string $file, string $tag, int $line, ScanResult $result, array $declared): void
    {
        $href = $this->attribute($tag, 'href');
        $rel = strtolower((string) $this->attribute($tag, 'rel'));
        $host = $href === null ? null : $this->host($href);

        if ($host === null || preg_match('/\b(?:stylesheet|preload|prefetch|preconnect|dns-prefetch|modulepreload)\b/', $rel) !== 1) {
            return;
        }

        $this->checkOutsideStyle($file, $host, $line, $result, $declared);
    }

    protected function checkForm(string $file, string $tag, int $line, ScanResult $result): void
    {
        $action = $this->attribute($tag, 'action');
        $host = $action === null ? null : $this->host($action);
        $siteHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host !== null && $host !== $siteHost) {
            $result->add('theme.external-form', Severity::Medium, message: 'A form sends what visitors type to :host, another site.', file: $file, line: $line, replace: ['host' => $host]);
            $result->nameHost($host, $file, $line);
        }
    }

    protected function checkImage(string $file, string $tag, int $line, ScanResult $result): void
    {
        if (preg_match('/[\s:]width\s*=/i', $tag) !== 1 || preg_match('/[\s:]height\s*=/i', $tag) !== 1) {
            $result->add('theme.image-size', Severity::Low, message: 'An image without width and height makes the page jump while it loads.', file: $file, line: $line);
        }
    }

    protected function checkHtml(string $file, string $tag, int $line, ScanResult $result): void
    {
        if (preg_match('/\slang\s*=/i', $tag) !== 1) {
            $result->add('theme.no-lang', Severity::Medium, message: 'The html tag has no lang attribute, so screen readers may read the page in the wrong language.', file: $file, line: $line);
        }
    }

    /**
     * @param  list<string>  $declared
     */
    protected function checkStyles(string $file, string $code, ScanResult $result, array $declared): void
    {
        $seen = [];

        foreach ([self::CSS_IMPORT_PATTERN, self::CSS_URL_PATTERN] as $pattern) {
            if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                $host = strtolower($match[2][0]);
                $line = $this->lineAt($code, (int) $match[0][1]);

                // "@import url(...)" matches both patterns. Report each host once per line.
                if (! isset($seen[$line.'|'.$host])) {
                    $seen[$line.'|'.$host] = true;
                    $this->checkOutsideStyle($file, $host, $line, $result, $declared);
                }
            }
        }
    }

    /**
     * A stylesheet, font or image from another host. Font services always count, because they see every visitor.
     *
     * @param  list<string>  $declared
     */
    protected function checkOutsideStyle(string $file, string $host, int $line, ScanResult $result, array $declared): void
    {
        if ($this->isDeclared($host, $this->fontHosts)) {
            $result->add('theme.remote-font', Severity::Medium, message: 'Loads fonts from :host, which shares the address of every visitor with that service. Ship the font files in the theme.', file: $file, line: $line, replace: ['host' => $host]);
            $result->nameHost($host, $file, $line);

            return;
        }

        if (! $this->isDeclared($host, $declared)) {
            $result->add('theme.external-style', Severity::Medium, message: 'Loads styles or files from :host, which is not declared in the "network" list of theme.json.', file: $file, line: $line, replace: ['host' => $host]);
            $result->nameHost($host, $file, $line);
        }
    }

    /**
     * Every opening tag with its lowercase name, text and line. Blade code inside tags is masked first,
     * so "->" and "=>" do not end a tag early.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    protected function tags(string $code): array
    {
        $masked = $this->masked($code);

        if (preg_match_all(self::TAG_PATTERN, $masked, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        return array_map(fn (array $match): array => [
            strtolower($match[1][0]),
            $match[0][0],
            $this->lineAt($masked, (int) $match[0][1]),
        ], $matches);
    }

    // The code with Blade comments, echoes and arrows replaced by spaces of the same length, so offsets and lines stay right.
    protected function masked(string $code): string
    {
        $code = (string) preg_replace_callback('/\{\{--.*?--\}\}|\{\{.*?\}\}|\{!!.*?!!\}/s', fn (array $match): string => $this->blank($match[0]), $code);
        $code = (string) preg_replace_callback('/@(?:if|elseif|unless|isset|foreach|for|while|class|style)\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s', fn (array $match): string => $this->blank($match[0]), $code);

        return str_replace(['->', '=>'], ['  ', '  '], $code);
    }

    // Spaces of the same length, keeping line breaks.
    protected function blank(string $text): string
    {
        return (string) preg_replace('/[^\n]/', ' ', $text);
    }

    protected function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/\s'.preg_quote($name, '/').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $match) !== 1) {
            return null;
        }

        return $match[1] !== '' ? $match[1] : (($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));
    }

    // Host of an absolute or protocol relative URL, or null for local URLs.
    protected function host(string $url): ?string
    {
        return preg_match(self::URL_HOST_PATTERN, $url, $match) === 1 ? strtolower($match[1]) : null;
    }

    /**
     * True when the host or one of its parent domains is in the list.
     *
     * @param  list<string>  $hosts
     */
    protected function isDeclared(string $host, array $hosts): bool
    {
        foreach ($hosts as $declared) {
            $declared = strtolower(trim($declared));

            if ($declared !== '' && ($host === $declared || str_ends_with($host, '.'.$declared))) {
                return true;
            }
        }

        return false;
    }

    protected function lineAt(string $code, int $offset): int
    {
        return substr_count(substr($code, 0, $offset), "\n") + 1;
    }
}

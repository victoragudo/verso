<?php

namespace App\Verso\Site\Support;

// Checks robots.txt text line by line. Returns the first problem, or null when the text is fine.
class RobotsTxtRules
{
    // Field names search engines understand (lowercase).
    public const FIELDS = ['user-agent', 'disallow', 'allow', 'sitemap', 'crawl-delay', 'host', 'clean-param'];

    public function problem(string $text): ?string
    {
        $agent = null;
        $hasAgent = false;

        foreach (preg_split('/\R/', $text) ?: [] as $index => $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            $number = $index + 1;

            if ($line === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $match) !== 1) {
                return __('Line :number needs the form "Field: value".', ['number' => $number]);
            }

            $field = strtolower($match[1]);
            $value = trim($match[2]);

            if (! in_array($field, self::FIELDS, true)) {
                return __('Line :number: ":field" is not a robots.txt field.', ['number' => $number, 'field' => $match[1]]);
            }

            if ($field === 'user-agent') {
                $agent = $value;
                $hasAgent = true;

                continue;
            }

            if (in_array($field, ['allow', 'disallow'], true) && ! $hasAgent) {
                return __('Line :number: add a User-agent line before :field.', ['number' => $number, 'field' => $match[1]]);
            }

            if (in_array($field, ['allow', 'disallow'], true) && $value !== '' && ! str_starts_with($value, '/') && ! str_starts_with($value, '*')) {
                return __('Line :number: paths start with /.', ['number' => $number]);
            }

            // Blocking everything for every robot hides the site. The "Discourage search engines" switch does that on purpose.
            if ($field === 'disallow' && $agent === '*' && $value === '/') {
                return __('Line :number blocks the whole site. Use "Discourage search engines" for that.', ['number' => $number]);
            }

            if ($field === 'sitemap' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                return __('Line :number: the sitemap needs a full URL.', ['number' => $number]);
            }

            if ($field === 'crawl-delay' && ! is_numeric($value)) {
                return __('Line :number: Crawl-delay needs a number of seconds.', ['number' => $number]);
            }
        }

        return null;
    }
}

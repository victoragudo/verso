<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Checks\ThemeRequirementsCheck;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use App\Verso\Extensions\Verification\Scanners\ThemeScanner;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use InvalidArgumentException;

// Runs every check on an unpacked theme folder: requirements, safety scan, quality and a sandboxed render.
class ThemeVerifier
{
    public const RENDER_FAILED = 'theme.render-failed';

    // Rules that stop the install whatever the risk score.
    public const BLOCKING_RULES = [
        ThemeRequirementsCheck::MANIFEST_INVALID,
        ThemeRequirementsCheck::MISSING_VIEW,
        ThemeRequirementsCheck::PARENT_MISSING,
        self::RENDER_FAILED,
    ];

    // Plugin rules that the theme rules replace with a more precise version.
    protected const REPLACED_RULES = ['blade.raw-output', 'blade.external-script'];

    // Messages and severities of the quality markers that are missing from the theme and its parents.
    protected const MISSING_MARKERS = [
        'seo' => ['rule' => 'theme.no-seo', 'severity' => Severity::Medium, 'message' => 'No view uses <x-verso-seo />, so pages miss their title, description and social tags.'],
        'head' => ['rule' => 'theme.no-head-hook', 'severity' => Severity::Medium, 'message' => 'No view uses <x-verso-head />, so plugins like analytics cannot add to the page head.'],
        'footer' => ['rule' => 'theme.no-footer-hook', 'severity' => Severity::Medium, 'message' => 'No view uses <x-verso-footer />, so plugins cannot add scripts at the end of the page.'],
        'skipLink' => ['rule' => 'theme.no-skip-link', 'severity' => Severity::Low, 'message' => 'No "Skip to content" link found. Keyboard users need it to jump past the menu.'],
        'lang' => ['rule' => 'theme.no-lang', 'severity' => Severity::Medium, 'message' => 'No html tag with a lang attribute found.'],
    ];

    public function __construct(
        protected CodeScanner $code,
        protected ThemeScanner $theme,
        protected ThemeRequirementsCheck $requirements,
        protected ThemeManager $themes,
        protected SandboxRunner $sandbox,
    ) {}

    // Check the theme in $path. $withSandbox false skips rendering it in a separate process.
    public function verify(string $path, bool $withSandbox = true): VerificationReport
    {
        $path = rtrim($path, '/\\');

        try {
            $manifest = Theme::readManifest($path);
        } catch (InvalidArgumentException $exception) {
            return $this->report(basename($path), '0.0.0', [
                new Finding(ThemeRequirementsCheck::MANIFEST_INVALID, Severity::High, ':error', ThemeRequirementsCheck::MANIFEST, replace: ['error' => $exception->getMessage()]),
            ], ['name' => basename($path)]);
        }

        $scan = new ScanResult;
        $theme = $this->requirements->check($path, $manifest, $scan);

        if ($theme === null) {
            return $this->report(basename($path), is_string($manifest['version'] ?? null) ? $manifest['version'] : '0.0.0', $scan->findings, ['name' => basename($path)]);
        }

        // A theme without its views or parent cannot render, so the quality checks and the sandbox would only add noise.
        $incomplete = array_any($scan->findings, fn (Finding $finding): bool => in_array($finding->rule, self::BLOCKING_RULES, true));

        $declared = $this->declaredHosts($manifest);
        $files = $this->scanFiles($path, $scan, $declared);
        $markers = $this->checkQuality($theme, $scan, ! $incomplete);

        if ($incomplete) {
            $sandbox = SandboxResult::skipped();
            $scan->add('sandbox.skipped', Severity::Info, message: 'The theme was not rendered, because required views or its parent theme are missing.');
        } else {
            $sandbox = $withSandbox ? $this->sandbox->runTheme($path, $theme->slug, $this->supportThemes($theme)) : SandboxResult::skipped();
            $this->addSandboxFindings($sandbox, $scan);
        }

        $findings = array_values(array_filter($scan->findings, fn (Finding $finding): bool => ! in_array($finding->rule, self::REPLACED_RULES, true)));

        return $this->report($theme->slug, $theme->version, $findings, [
            'name' => $theme->name,
            'files' => $files,
            'theme' => [
                'parent' => $theme->parent,
                'requires' => is_string($manifest['requires'] ?? null) ? $manifest['requires'] : null,
                'php' => is_string($manifest['php'] ?? null) ? $manifest['php'] : null,
                'screenshot' => $this->requirements->screenshot($path, $manifest),
                'network' => $declared,
                'hosts' => array_keys($scan->hosts),
                'quality' => $markers,
            ],
            'sandbox' => $sandbox->toArray(),
        ]);
    }

    /**
     * Build the report and list the blocking rules it found.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    protected function report(string $slug, string $version, array $findings, array $details): VerificationReport
    {
        $blocking = array_values(array_unique(array_filter(
            array_map(fn (Finding $finding): string => $finding->rule, $findings),
            fn (string $rule): bool => in_array($rule, self::BLOCKING_RULES, true),
        )));

        return VerificationReport::make($slug, $version, $findings, [...$details, 'blocking' => $blocking]);
    }

    /**
     * Scan the PHP, Blade and JavaScript files with the plugin rules, then add the theme rules for views and stylesheets.
     *
     * @param  list<string>  $declared
     */
    protected function scanFiles(string $path, ScanResult $scan, array $declared): int
    {
        $scanned = $this->code->scan($path, $scan, function (string $file, string $kind, string $code) use ($scan, $declared): void {
            if ($kind === CodeScanner::KIND_BLADE) {
                $this->theme->scanBlade($file, $code, $scan, $declared);
            } elseif ($kind === CodeScanner::KIND_PHP) {
                $scan->add('theme.php-file', Severity::High, message: 'Themes may only hold Blade views. This PHP file would never be loaded by Verso.', file: $file);
            }
        });

        foreach ($this->code->files($path) as $file => $info) {
            if (strtolower($info->getExtension()) === 'css' && $info->getSize() <= $this->code->maxFileBytes()) {
                $this->theme->scanStylesheet($file, (string) file_get_contents($info->getPathname()), $scan, $declared);
                $scanned++;
            }
        }

        return $scanned;
    }

    /**
     * Look for the SEO tags, plugin hooks, skip link and lang attribute in the views of the theme and its parents.
     * $report false only collects them, without findings for the missing ones.
     *
     * @return array<string, bool>
     */
    protected function checkQuality(Theme $theme, ScanResult $scan, bool $report): array
    {
        $found = array_fill_keys(array_keys(ThemeScanner::QUALITY_MARKERS), false);

        foreach ($this->themes->lineage($theme) as $member) {
            if (! is_dir($member->viewsPath())) {
                continue;
            }

            foreach ($this->code->files($member->viewsPath()) as $info) {
                if (str_ends_with($info->getFilename(), CodeScanner::BLADE_SUFFIX) && $info->getSize() <= $this->code->maxFileBytes()) {
                    foreach ($this->theme->markers((string) file_get_contents($info->getPathname())) as $marker => $present) {
                        $found[$marker] = $found[$marker] || $present;
                    }
                }
            }
        }

        if (! $report) {
            return $found;
        }

        $hasLangFinding = array_any($scan->findings, fn (Finding $finding): bool => $finding->rule === 'theme.no-lang');

        foreach (self::MISSING_MARKERS as $marker => ['rule' => $rule, 'severity' => $severity, 'message' => $message]) {
            if (! $found[$marker] && ! ($marker === 'lang' && $hasLangFinding)) {
                $scan->add($rule, $severity, $message);
            }
        }

        return $found;
    }

    /**
     * Installed themes copied next to the theme in the sandbox: its parents and the fallback theme.
     *
     * @return array<string, string>
     */
    protected function supportThemes(Theme $theme): array
    {
        $support = [];

        foreach (array_slice($this->themes->lineage($theme), 1) as $parent) {
            $support[$parent->slug] = $parent->path;
        }

        $fallback = $this->themes->fallback();

        if ($fallback !== null && $fallback->slug !== $theme->slug) {
            $support[$fallback->slug] = $fallback->path;
        }

        return $support;
    }

    // Turn the sandbox run into findings. A page that does not render stops the install.
    protected function addSandboxFindings(SandboxResult $sandbox, ScanResult $scan): void
    {
        match ($sandbox->status) {
            SandboxResult::SKIPPED => $scan->add('sandbox.skipped', Severity::Info, message: 'The theme was not rendered in the sandbox.'),
            SandboxResult::TIMEOUT => $scan->add('sandbox.timeout', Severity::High, message: 'Rendering the theme in the sandbox took too long and was stopped.'),
            SandboxResult::CRASHED => $scan->add(self::RENDER_FAILED, Severity::High, message: 'The sandbox process stopped without a result: :output', file: null, replace: ['output' => $sandbox->firstOutputLine()]),
            default => null,
        };

        foreach ($sandbox->errors as $error) {
            ['rule' => $rule, 'severity' => $severity, 'message' => $message] = match ($error['stage']) {
                'render' => ['rule' => self::RENDER_FAILED, 'severity' => Severity::High, 'message' => ':error'],
                'theme' => ['rule' => self::RENDER_FAILED, 'severity' => Severity::High, 'message' => 'The theme could not be activated: :error'],
                default => ['rule' => 'sandbox.error', 'severity' => Severity::Medium, 'message' => 'The sandbox could not be prepared: :error'],
            };

            $scan->add($rule, $severity, $message, $error['file'], $error['line'], ['error' => $error['message']]);
        }
    }

    /**
     * Hosts the theme says it loads files from, in the "network" list of theme.json.
     *
     * @param  array<mixed>  $manifest
     * @return list<string>
     */
    protected function declaredHosts(array $manifest): array
    {
        $hosts = is_array($manifest['network'] ?? null) ? $manifest['network'] : [];

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): string => is_string($host) ? strtolower(trim($host)) : '',
            $hosts,
        ))));
    }
}

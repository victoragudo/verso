<?php

namespace App\Verso\Extensions\Verification\Checks;

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Plugins\PluginManifest;
use App\Verso\Plugins\Support\VersionConstraint;
use App\Verso\Theme\Customizer\ThemeSettingsSchema;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

// Checks what a theme needs before it can be installed: a valid theme.json, versions, parent theme, views and screenshot.
class ThemeRequirementsCheck
{
    public const MANIFEST = 'theme.json';

    // Rules that stop the install whatever the risk score.
    public const MANIFEST_INVALID = 'theme.manifest-invalid';

    public const MISSING_VIEW = 'theme.missing-view';

    public const PARENT_MISSING = 'theme.parent-missing';

    // Fields theme.json should have. Missing ones are reported, but do not stop the install.
    protected const RECOMMENDED_FIELDS = ['description', 'author'];

    public function __construct(protected ThemeManager $themes) {}

    /**
     * Add the requirement findings for the theme in $path. Returns the theme, or null when theme.json cannot be used.
     *
     * @param  array<mixed>  $manifest  the decoded theme.json
     */
    public function check(string $path, array $manifest, ScanResult $scan): ?Theme
    {
        if (! $this->checkManifest($path, $manifest, $scan)) {
            return null;
        }

        $theme = Theme::fromManifest($path, $manifest);

        $this->checkVersions($manifest, $scan);
        $this->checkParent($theme, $scan);
        $this->checkViews($theme, $scan);
        $this->checkScreenshot($path, $manifest, $scan);
        $this->checkSettings($manifest, $scan);

        return $theme;
    }

    /**
     * The screenshot file of the theme, relative to its folder, or null when there is none.
     *
     * @param  array<mixed>  $manifest
     */
    public function screenshot(string $path, array $manifest): ?string
    {
        if (is_string($manifest['screenshot'] ?? null) && trim($manifest['screenshot']) !== '') {
            return ltrim(str_replace('\\', '/', trim($manifest['screenshot'])), '/');
        }

        foreach ((array) config('verso.extensions.verification.themes.screenshot_files') as $file) {
            if (is_file($path.DIRECTORY_SEPARATOR.$file)) {
                return (string) $file;
            }
        }

        return null;
    }

    /**
     * Name, slug and version must be valid. Returns false when the theme cannot be read at all.
     *
     * @param  array<mixed>  $manifest
     */
    protected function checkManifest(string $path, array $manifest, ScanResult $scan): bool
    {
        $valid = true;
        $slug = $manifest['slug'] ?? basename($path);

        if (! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '') {
            $scan->add(self::MANIFEST_INVALID, Severity::High, message: 'theme.json has no name.', file: self::MANIFEST);
            $valid = false;
        }

        if (! is_string($slug) || preg_match(PluginManifest::SLUG_PATTERN, $slug) !== 1) {
            $scan->add(self::MANIFEST_INVALID, Severity::High, message: 'The slug in theme.json must use lowercase letters, digits and dashes.', file: self::MANIFEST);
            $valid = false;
        } elseif ($slug !== basename($path)) {
            $scan->add('theme.slug-mismatch', Severity::Medium, message: 'The slug ":slug" in theme.json is not the folder name ":folder".', file: self::MANIFEST, replace: ['slug' => $slug, 'folder' => basename($path)]);
        }

        if (! is_string($manifest['version'] ?? null) || preg_match(PluginManifest::VERSION_PATTERN, $manifest['version']) !== 1) {
            $scan->add(self::MANIFEST_INVALID, Severity::High, message: 'theme.json needs a version like "1.0.0".', file: self::MANIFEST);
            $valid = false;
        }

        foreach (self::RECOMMENDED_FIELDS as $field) {
            if (! is_string($manifest[$field] ?? null) || trim($manifest[$field]) === '') {
                $scan->add('theme.manifest-incomplete', Severity::Low, message: 'theme.json has no :field.', file: self::MANIFEST, replace: ['field' => $field]);
            }
        }

        return $valid;
    }

    /**
     * "requires" holds the Verso versions and "php" the PHP versions the theme works with.
     *
     * @param  array<mixed>  $manifest
     */
    protected function checkVersions(array $manifest, ScanResult $scan): void
    {
        $targets = [
            'requires' => ['Verso', (string) config('verso.version'), 'theme.verso-version'],
            'php' => ['PHP', PHP_VERSION, 'theme.php-version'],
        ];

        foreach ($targets as $key => [$label, $current, $rule]) {
            $constraint = $manifest[$key] ?? null;

            if (! is_string($constraint) || trim($constraint) === '') {
                $scan->add('theme.requires-missing', Severity::Low, message: 'theme.json does not say which :product versions the theme works with (":key").', file: self::MANIFEST, replace: ['product' => $label, 'key' => $key]);

                continue;
            }

            $normalized = VersionConstraint::normalizeConstraint($constraint);

            if (! VersionConstraint::isValid($normalized)) {
                $scan->add($rule, Severity::Low, message: 'The :product version constraint ":constraint" could not be read.', file: self::MANIFEST, replace: ['product' => $label, 'constraint' => $constraint]);
            } elseif (! VersionConstraint::satisfies($current, $normalized)) {
                $scan->add($rule, Severity::High, message: 'The theme needs :product :constraint, but this site runs :product :current.', file: self::MANIFEST, replace: ['product' => $label, 'constraint' => $constraint, 'current' => $current]);
            }
        }
    }

    protected function checkParent(Theme $theme, ScanResult $scan): void
    {
        if ($theme->parent === null) {
            return;
        }

        if ($theme->parent === $theme->slug) {
            $scan->add(self::PARENT_MISSING, Severity::High, message: 'The theme names itself as its parent.', file: self::MANIFEST);

            return;
        }

        $missing = $this->themes->missingParent($theme);

        if ($missing !== null) {
            $scan->add(self::PARENT_MISSING, Severity::High, message: 'The parent theme ":parent" is not installed. Install it first.', file: self::MANIFEST, replace: ['parent' => $missing]);
        }
    }

    // Every required view must be in the theme or in one of its installed parents.
    protected function checkViews(Theme $theme, ScanResult $scan): void
    {
        foreach ($this->themes->missingRequiredViews($theme) as $view) {
            $scan->add(self::MISSING_VIEW, Severity::High, message: 'The required view ":view" is missing from the theme and its parents.', file: 'views/'.$view.'.blade.php', replace: ['view' => $view]);
        }
    }

    /**
     * @param  array<mixed>  $manifest
     */
    protected function checkScreenshot(string $path, array $manifest, ScanResult $scan): void
    {
        $config = (array) config('verso.extensions.verification.themes');
        $file = $this->screenshot($path, $manifest);

        if ($file === null) {
            $scan->add('theme.screenshot-missing', Severity::Low, message: 'The theme has no screenshot. Add screenshot.png so people can see it before they activate it.', file: self::MANIFEST);

            return;
        }

        $full = $path.DIRECTORY_SEPARATOR.$file;
        $extensions = array_map(fn (mixed $extension): string => strtolower((string) $extension), (array) ($config['screenshot_extensions'] ?? []));

        if (str_contains($file, '..') || ! is_file($full) || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions, true)) {
            $scan->add('theme.screenshot-invalid', Severity::Medium, message: 'The screenshot must be a :types image inside the theme folder.', file: $file, replace: ['types' => implode(', ', $extensions)]);

            return;
        }

        $maxBytes = max(1, (int) ($config['screenshot_max_kb'] ?? 1024)) * 1024;

        if ((int) filesize($full) > $maxBytes) {
            $scan->add('theme.screenshot-too-large', Severity::Medium, message: 'The screenshot is :size KB. The limit is :limit KB.', file: $file, replace: ['size' => (int) ceil((int) filesize($full) / 1024), 'limit' => intdiv($maxBytes, 1024)]);
        }

        $size = @getimagesize($full);

        if ($size === false) {
            $scan->add('theme.screenshot-invalid', Severity::Medium, message: 'The screenshot is not an image that can be read.', file: $file);

            return;
        }

        $maxWidth = max(1, (int) ($config['screenshot_max_width'] ?? 2400));
        $maxHeight = max(1, (int) ($config['screenshot_max_height'] ?? 1800));

        if ($size[0] > $maxWidth || $size[1] > $maxHeight) {
            $scan->add('theme.screenshot-too-large', Severity::Low, message: 'The screenshot is :width x :height pixels. The limit is :max_width x :max_height.', file: $file, replace: ['width' => $size[0], 'height' => $size[1], 'max_width' => $maxWidth, 'max_height' => $maxHeight]);
        }
    }

    /**
     * @param  array<mixed>  $manifest
     */
    protected function checkSettings(array $manifest, ScanResult $scan): void
    {
        if (! array_key_exists('settings', $manifest)) {
            return;
        }

        if (! is_array($manifest['settings'])) {
            $scan->add('theme.settings-invalid', Severity::Medium, message: 'The settings in theme.json must be an object.', file: self::MANIFEST);

            return;
        }

        foreach (ThemeSettingsSchema::fromConfig($manifest['settings'])->errors as $error) {
            $scan->add('theme.settings-invalid', Severity::Medium, $error, self::MANIFEST);
        }
    }
}

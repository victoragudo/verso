<?php

use App\Verso\Extensions\Verification\Checks\ThemeRequirementsCheck;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Theme\Theme;
use Illuminate\Support\Facades\File;
use Tests\Support\ExtensionSandbox;
use Tests\Support\ThemeFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();

    // Run the check on a folder and return "rule severity: message" for each finding.
    $this->check = function (string $path): array {
        $scan = new ScanResult;
        app(ThemeRequirementsCheck::class)->check($path, Theme::readManifest($path), $scan);

        return array_map(fn (Finding $finding): string => "{$finding->rule} {$finding->severity->value}: {$finding->message}", $scan->findings);
    };

    // Copy harbor-light with a screenshot and a changed theme.json.
    $this->harbor = function (array $manifest = [], array $remove = []): string {
        $path = ThemeFixtures::withScreenshot('harbor-light');
        $data = array_diff_key([...Theme::readManifest($path), ...$manifest], array_flip($remove));
        File::put($path.'/theme.json', (string) json_encode($data));

        return $path;
    };
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox->destroy();
});

it('has nothing to say about a complete theme', function () {
    expect(($this->check)(($this->harbor)()))->toBe([]);
});

it('reports a missing description, author and version constraints as low', function () {
    expect(($this->check)(($this->harbor)(remove: ['description', 'author', 'requires', 'php'])))->toBe([
        'theme.manifest-incomplete low: theme.json has no description.',
        'theme.manifest-incomplete low: theme.json has no author.',
        'theme.requires-missing low: theme.json does not say which Verso versions the theme works with ("requires").',
        'theme.requires-missing low: theme.json does not say which PHP versions the theme works with ("php").',
    ]);
});

it('stops at a theme.json without a valid name, slug or version', function () {
    $path = ($this->harbor)(['name' => '', 'slug' => 'Harbor Light', 'version' => 'latest']);
    $scan = new ScanResult;

    $theme = app(ThemeRequirementsCheck::class)->check($path, Theme::readManifest($path), $scan);

    expect($theme)->toBeNull()
        ->and(array_column(array_map(fn (Finding $finding): array => $finding->toArray(), $scan->findings), 'rule'))
        ->toBe(['theme.manifest-invalid', 'theme.manifest-invalid', 'theme.manifest-invalid']);
});

it('reports a slug that is not the folder name', function () {
    expect(($this->check)(($this->harbor)(['slug' => 'harbour-light'])))
        ->toBe(['theme.slug-mismatch medium: The slug "harbour-light" in theme.json is not the folder name "harbor-light".']);
});

it('reports Verso and PHP versions the site does not run as high', function () {
    expect(($this->check)(($this->harbor)(['requires' => '^4.0', 'php' => '>=7.0, <8.0'])))->toBe([
        'theme.verso-version high: The theme needs Verso ^4.0, but this site runs Verso '.config('verso.version').'.',
        'theme.php-version high: The theme needs PHP >=7.0, <8.0, but this site runs PHP '.PHP_VERSION.'.',
    ]);
});

it('reports version constraints it cannot read as low', function () {
    expect(($this->check)(($this->harbor)(['requires' => 'the newest one'])))
        ->toBe(['theme.verso-version low: The Verso version constraint "the newest one" could not be read.']);
});

it('reports a screenshot over the configured file size and image size', function () {
    config([
        'verso.extensions.verification.themes.screenshot_max_kb' => 1,
        'verso.extensions.verification.themes.screenshot_max_width' => 640,
        'verso.extensions.verification.themes.screenshot_max_height' => 480,
    ]);

    $path = ThemeFixtures::copy('harbor-light');
    File::put($path.'/screenshot.png', ThemeFixtures::png(1600, 1200).str_repeat(' ', 2048));

    expect(($this->check)($path))->toHaveCount(2)
        ->and(($this->check)($path)[0])->toStartWith('theme.screenshot-too-large medium: The screenshot is ')
        ->and(($this->check)($path)[1])->toBe('theme.screenshot-too-large low: The screenshot is 1600 x 1200 pixels. The limit is 640 x 480.');
});

it('accepts a screenshot named in theme.json', function () {
    $path = ThemeFixtures::copy('harbor-light', ['theme.json' => (string) json_encode([...Theme::readManifest(ThemeFixtures::path('harbor-light')), 'screenshot' => 'assets/preview.webp'])]);
    File::put($path.'/assets/preview.webp', (string) (function () {
        $image = imagecreatetruecolor(800, 600);
        ob_start();
        imagewebp($image);

        return ob_get_clean();
    })());

    expect(($this->check)($path))->toBe([])
        ->and(app(ThemeRequirementsCheck::class)->screenshot($path, Theme::readManifest($path)))->toBe('assets/preview.webp');
});

it('reports a missing screenshot, and one outside the theme or of the wrong type', function (string $screenshot, string $expected) {
    $path = ThemeFixtures::copy('harbor-light', ['theme.json' => (string) json_encode([...Theme::readManifest(ThemeFixtures::path('harbor-light')), 'screenshot' => $screenshot])]);

    expect(($this->check)($path))->toBe([$expected]);
})->with([
    'outside' => ['../../secrets/preview.png', 'theme.screenshot-invalid medium: The screenshot must be a png, webp, jpg, jpeg, avif image inside the theme folder.'],
    'wrong type' => ['assets/lighthouse.svg', 'theme.screenshot-invalid medium: The screenshot must be a png, webp, jpg, jpeg, avif image inside the theme folder.'],
    'not an image' => ['assets/theme.css', 'theme.screenshot-invalid medium: The screenshot must be a png, webp, jpg, jpeg, avif image inside the theme folder.'],
]);

it('reports a theme without any screenshot as low', function () {
    expect(($this->check)(ThemeFixtures::path('harbor-light')))
        ->toBe(['theme.screenshot-missing low: The theme has no screenshot. Add screenshot.png so people can see it before they activate it.']);
});

it('reports an invalid settings schema', function () {
    $messages = ($this->check)(($this->harbor)(['settings' => ['accent_color' => ['type' => 'rainbow', 'label' => 'Accent']]]));

    expect($messages)->not->toBe([])
        ->and($messages[0])->toStartWith('theme.settings-invalid medium: ');
});

it('reports settings that are not an object', function () {
    expect(($this->check)(($this->harbor)(['settings' => 'accent'])))
        ->toBe(['theme.settings-invalid medium: The settings in theme.json must be an object.']);
});

it('reports a theme that names itself as its parent', function () {
    expect(($this->check)(($this->harbor)(['parent' => 'harbor-light'])))
        ->toContain('theme.parent-missing high: The theme names itself as its parent.');
});

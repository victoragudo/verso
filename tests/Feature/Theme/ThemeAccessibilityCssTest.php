<?php

use App\Verso\Theme\ThemeManager;

// The stylesheet of a bundled theme.
function bundledThemeCss(string $theme): string
{
    return (string) file_get_contents(app(ThemeManager::class)->find($theme)->filePath('assets/theme.css'));
}

// The body of every rule block that starts with the given selector line.
function cssBlock(string $css, string $opening): string
{
    $start = strpos($css, $opening);

    expect($start)->not->toBeFalse("missing {$opening}");

    return substr($css, (int) $start, (int) strpos($css, "\n}", (int) $start) - (int) $start);
}

it('keeps a visible focus ring and the current link in high contrast mode', function (string $theme, string $currentLink) {
    $css = bundledThemeCss($theme);
    $forced = '';

    foreach (explode('@media (forced-colors: active) {', $css) as $index => $part) {
        $forced .= $index === 0 ? '' : substr($part, 0, (int) strpos($part, "\n}"));
    }

    expect($css)->toMatch('/:focus-visible \{\s*outline: \d+px solid var\(--focus\);/')
        ->and($forced)->toContain($currentLink)
        ->toContain('.verso-scheme-toggle');
})->with([
    'default' => ['default', ".site-nav a[aria-current='page']"],
    'ashgrove' => ['ashgrove', ".search-link[aria-current='page']"],
]);

it('gives the default theme buttons a border in high contrast mode', function () {
    $forced = cssBlock(bundledThemeCss('default'), "@media (forced-colors: active) {\n    .site-nav a[aria-current='page']");

    expect($forced)->toContain('.search-form button')
        ->toContain('.verso-comments__submit button')
        ->toContain('border: 1px solid ButtonText;');
});

it('makes the default footer menu links at least 24 pixels tall', function () {
    expect(cssBlock(bundledThemeCss('default'), '.footer-nav a {'))->toContain('min-block-size: 1.5rem;');
});

it('turns the Ashgrove submenu arrow only for visitors who do not ask for less motion', function () {
    expect(cssBlock(bundledThemeCss('ashgrove'), '.submenu-button svg {'))
        ->toContain("@media (prefers-reduced-motion: no-preference) {\n        transition: rotate 150ms ease;");
});

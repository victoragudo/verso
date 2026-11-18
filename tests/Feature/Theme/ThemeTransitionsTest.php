<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

// Parses a public page so the checks can walk its tags.
function transitionPage(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

// Title selectors of a list card and of the entry page, per bundled theme.
function transitionTitles(string $theme): array
{
    return [
        'ashgrove' => ['card' => '//h2[contains(@class, "post-card-title")]', 'entry' => '//h1[contains(@class, "post-title")]'],
        'default' => ['card' => '//li[contains(@class, "entry-card")]//h2', 'entry' => '//h1[contains(@class, "page-title")]'],
    ][$theme];
}

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
});

it('turns on page transitions only for visitors who do not ask for less motion', function (string $theme) {
    $css = (string) file_get_contents(app(ThemeManager::class)->find($theme)->filePath('assets/theme.css'));

    expect($css)->toMatch('/@media \(prefers-reduced-motion: no-preference\) \{\s*@view-transition \{\s*navigation: auto;/')
        ->toMatch('/@media \(prefers-reduced-motion: reduce\) \{\s*@view-transition \{\s*navigation: none;/')
        ->toContain('view-transition-name: site-header;');
})->with(['ashgrove', 'default']);

it('animates the menu popovers in and out with a shorter fade for less motion', function (string $theme) {
    $css = (string) file_get_contents(app(ThemeManager::class)->find($theme)->filePath('assets/theme.css'));

    expect($css)->toContain('transition-property: opacity, translate, display, overlay;')
        ->toContain('transition-behavior: allow-discrete;')
        ->toContain('@starting-style {')
        ->toContain('.verso-nav__list[popover]:is(:popover-open, .\:popover-open)');
})->with(['ashgrove', 'default']);

it('gives the card title and the entry title the same transition name', function (string $theme) {
    app(Settings::class)->set('theme.active', $theme);
    $entry = Entry::firstWhere('slug', 'a-slow-weekend-in-porto');
    $name = "view-transition-name: entry-title-{$entry->id}";
    $titles = transitionTitles($theme);

    $list = transitionPage($this->get('/blog')->assertOk()->getContent());
    $cardStyles = collect(iterator_to_array($list->query($titles['card'])))->map(fn (DOMElement $title): string => $title->getAttribute('style'));

    $page = transitionPage($this->get($entry->path())->assertOk()->getContent());
    $title = $page->query($titles['entry'])->item(0);
    $expect = $page->query('//head/link[@rel="expect"]');

    expect($cardStyles)->toContain($name)
        ->and($cardStyles->unique()->count())->toBe($cardStyles->count())
        ->and($title->getAttribute('style'))->toBe($name)
        ->and($title->getAttribute('id'))->toBe('verso-entry-title')
        ->and($expect->length)->toBe(1)
        ->and($expect->item(0)->getAttribute('href'))->toBe('#verso-entry-title')
        ->and($expect->item(0)->getAttribute('blocking'))->toBe('render');
})->with(['ashgrove', 'default']);

it('never gives two elements of a page the same transition name', function (string $theme, string $path) {
    app(Settings::class)->set('theme.active', $theme);
    $path = $path === 'entry' ? Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->path() : $path;

    preg_match_all('/view-transition-name:\s*([a-z0-9-]+)/', (string) $this->get($path)->assertOk()->getContent(), $names);
    $inline = array_values(array_filter($names[1], fn (string $name): bool => str_starts_with($name, 'entry-title-')));

    expect($inline)->toBe(array_values(array_unique($inline)));
})->with(['ashgrove', 'default'])->with(['/', '/blog', '/category/travel', 'entry']);

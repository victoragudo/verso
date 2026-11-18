<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;
use Inertia\Testing\AssertableInertia as Assert;

// Server drawn pieces of Easy mode that people with screen readers, keyboards and touch screens use.

beforeEach(function () {
    config(['verso.page_cache.enabled' => false, 'verso.admin_bar.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    app(Settings::class)->set('theme.active', 'ashgrove');

    $this->admin = User::factory()->admin()->create(['name' => 'Marta Oliveira']);
    $kit = app(SectionKit::class);
    $library = app(SectionLibrary::class);
    $this->blocks = [
        ...$library->build('hero', ['heading' => 'Fresh bread on Harbour Lane'], $kit),
        ...$library->build('faq', [], $kit),
    ];
    $this->page = Entry::factory()->page()->published()->by($this->admin)->create([
        'title' => 'Harbour Lane Bakery',
        'slug' => 'bakery',
        'blocks' => $this->blocks,
    ]);
});

function a11yDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

function a11yText(DOMNode $node): string
{
    return trim((string) preg_replace('/\s+/', ' ', $node->textContent));
}

describe('admin bar', function () {
    it('names the Edit page button and keeps it in the labelled navigation', function () {
        $html = (string) $this->actingAs($this->admin)->get('/bakery')->assertOk()->getContent();
        $xpath = a11yDocument($html);

        $edit = $xpath->query('//nav[@aria-label="Site administration"]//ul/li/a[@data-verso-visual-edit]');

        expect($edit->length)->toBe(1)
            ->and(a11yText($xpath->query('.//span[not(@aria-hidden="true")]', $edit->item(0))->item(0)))->toBe('Edit page')
            ->and($edit->item(0)?->attributes?->getNamedItem('href')?->nodeValue)->toBe(route('admin.easy.pages.edit', $this->page));
    });

    it('gives every menu button a name, its state and the menu it opens', function () {
        $xpath = a11yDocument((string) $this->actingAs($this->admin)->get('/bakery')->getContent());

        $buttons = $xpath->query('//nav[@aria-label="Site administration"]//button[@popovertarget]');

        expect($buttons->length)->toBeGreaterThanOrEqual(2);

        foreach ($buttons as $button) {
            $attributes = $button->attributes;
            $menu = $attributes?->getNamedItem('aria-controls')?->nodeValue;
            $name = $attributes?->getNamedItem('aria-label')?->nodeValue ?? a11yText($button);

            expect($name)->not->toBe('')
                ->and($attributes?->getNamedItem('aria-expanded')?->nodeValue)->toBe('false')
                ->and($menu)->toBe($attributes?->getNamedItem('popovertarget')?->nodeValue)
                ->and($xpath->query('//*[@id="'.$menu.'"]')->length)->toBe(1);
        }

        expect($xpath->query('//button[@aria-label="More ways to edit"]')->length)->toBe(1);
    });

    it('starts with a skip link to a real target and grows its buttons on touch screens', function () {
        $html = (string) $this->actingAs($this->admin)->get('/bakery')->getContent();
        $xpath = a11yDocument($html);
        $skip = $xpath->query('//a[contains(@class, "verso-adminbar-skip")]')->item(0);
        $target = ltrim((string) $skip?->attributes?->getNamedItem('href')?->nodeValue, '#');

        expect(a11yText($skip ?? new DOMText))->toBe('Skip to content')
            ->and($xpath->query('//*[@id="'.$target.'"]')->length)->toBe(1)
            ->and($html)->toContain('@media (pointer: coarse)')
            ->and($html)->toContain('--verso-adminbar-height: max(36px, 2.75rem)')
            ->and($html)->toContain('min-block-size: 2.75rem');
    });
});

describe('edit mode markers', function () {
    it('only add data attributes, so headings, landmarks and the language stay the same', function () {
        $html = (string) $this->actingAs($this->admin)
            ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $this->blocks])
            ->assertOk()
            ->getContent();
        $xpath = a11yDocument($html);
        $public = a11yDocument((string) $this->get('/bakery')->getContent());

        expect($xpath->query('//html[@lang]')->length)->toBe(1)
            ->and($xpath->query('//main')->length)->toBe(1)
            ->and($xpath->query('//*[@data-verso-block]')->length)->toBeGreaterThan(2)
            ->and($xpath->query('//*[@data-verso-block][not(@data-verso-type)]')->length)->toBe(0)
            ->and($xpath->query('//*[@data-verso-block][@aria-hidden or @hidden or @role="presentation"]')->length)->toBe(0)
            ->and(array_map(a11yText(...), iterator_to_array($xpath->query('//main//h1 | //main//h2 | //main//h3'))))
            ->toBe(array_map(a11yText(...), iterator_to_array($public->query('//main//h1 | //main//h2 | //main//h3'))));
    });

    it('keeps hidden sections readable in the editor, marked as hidden', function () {
        $blocks = $this->blocks;
        $blocks[1]['attrs']['design']['visibility']['hidden'] = true;

        $xpath = a11yDocument((string) $this->actingAs($this->admin)
            ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $blocks])
            ->getContent());
        $hidden = $xpath->query('//*[@data-verso-hidden]');

        expect($hidden->length)->toBe(1)
            ->and($hidden->item(0)?->attributes?->getNamedItem('aria-hidden'))->toBeNull()
            ->and(a11yText($hidden->item(0) ?? new DOMText))->toContain('How can I contact you?');
    });

    it('marks the content area of an empty page for the "Add your first section" button', function () {
        $xpath = a11yDocument((string) $this->actingAs($this->admin)
            ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => []])
            ->getContent());

        expect($xpath->query('//main//*[@data-verso-empty]')->length)->toBe(1);
    });
});

it('hands the colors of the current style to the page editor for its contrast warnings', function () {
    app(GlobalStyles::class)->save(app(ThemeManager::class)->active(), ['color.text' => '#2b2622']);
    $values = app(GlobalStyles::class)->values(app(ThemeManager::class)->active());

    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertInertia(fn (Assert $page) => $page
            ->where('colors', fn ($colors): bool => $colors['color.text'] === '#2b2622'
                && $colors['color.accent'] === $values['color.accent']
                && $colors['color.background'] === $values['color.background']
                && collect($colors)->keys()->every(fn (string $id): bool => str_starts_with($id, 'color.')))
        );
});

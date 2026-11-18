<?php

use App\Models\User;
use App\Verso\Blocks\BlocksServiceProvider;
use App\Verso\Blocks\Interactive\TableOfContentsBlock;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\Assets\BlockAssetRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Illuminate\Support\Facades\Validator;

function drawBlocks(array $blocks): string
{
    return app(BlockRenderer::class)->render($blocks);
}

function blocksProblem(array $blocks, ?User $user = null): ?string
{
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($user ?? User::factory()->admin()->create())]]);

    return $validator->fails() ? $validator->errors()->first('blocks') : null;
}

$openingHours = [
    'id' => 'b_hours', 'type' => 'tabs', 'attrs' => ['label' => 'Opening hours'],
    'children' => [
        ['id' => 'b_week', 'type' => 'tab', 'attrs' => ['title' => 'Weekdays'], 'children' => [['id' => 'b_p1', 'type' => 'paragraph', 'content' => 'Seven to seven.']]],
        ['id' => 'b_sat', 'type' => 'tab', 'attrs' => ['title' => 'Saturday'], 'children' => [['id' => 'b_p2', 'type' => 'paragraph', 'content' => 'Eight to two.']]],
    ],
];

it('registers every builder block for both editors', function () {
    $names = array_column(app(PluginBlockRegistry::class)->toArray(), 'name');

    foreach (BlocksServiceProvider::BLOCKS as $class) {
        expect($names)->toContain(app($class)->name());
    }
});

it('draws tabs with the first panel open and the others findable', function () use ($openingHours) {
    $html = drawBlocks([$openingHours]);

    expect($html)->toContain('role="tablist" aria-label="Opening hours"')
        ->toContain('id="vb-b_week-tab" aria-controls="vb-b_week-panel" aria-selected="true" tabindex="0">Weekdays</button>')
        ->toContain('aria-selected="false" tabindex="-1">Saturday')
        ->toContain('id="vb-b_sat-panel" aria-labelledby="vb-b_sat-tab" hidden="until-found"')
        ->and(substr_count($html, 'hidden="until-found"'))->toBe(1);
});

it('prints the files of a block once, before and after the blocks', function () use ($openingHours) {
    $html = drawBlocks([$openingHours, [...$openingHours, 'id' => 'b_hours2', 'children' => []]]);

    expect(substr_count($html, 'verso-blocks/tabs.css'))->toBe(1)
        ->and(substr_count($html, 'verso-blocks/tabs.js'))->toBe(1)
        ->and(strpos($html, 'tabs.css'))->toBeLessThan(strpos($html, 'verso-tabs'))
        ->and(strrpos($html, 'tabs.js'))->toBeGreaterThan(strrpos($html, 'verso-tabs'))
        ->and($html)->toContain('<script type="module"');
});

it('loads nothing for pages without builder blocks', function () {
    expect(drawBlocks([['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Fresh bread daily.']]))
        ->not->toContain('verso-blocks/');
});

it('keeps a tab inside tabs and only tabs inside tabs', function () use ($openingHours) {
    expect(blocksProblem([$openingHours]))->toBeNull()
        ->and(blocksProblem([['id' => 'b_lone', 'type' => 'tab', 'attrs' => ['title' => 'Lost']]]))->toContain('must be placed inside')
        ->and(blocksProblem([['id' => 'b_t', 'type' => 'tabs', 'children' => [['id' => 'b_x', 'type' => 'paragraph', 'content' => 'No']]]]))->toContain('cannot be placed inside')
        ->and(blocksProblem([['id' => 'b_s', 'type' => 'section', 'children' => [['id' => 'b_t', 'type' => 'tab', 'attrs' => ['title' => 'Lost']]]]]))->toContain('cannot be placed inside');
});

it('sends child rules and starter children to the editor', function () {
    $tabs = collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'tabs');
    $tab = collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'tab');

    expect($tabs['childTypes'])->toBe(['tab'])
        ->and($tabs['starter'])->toHaveCount(2)
        ->and($tabs['starter'][0]['type'])->toBe('tab')
        ->and($tab['parents'])->toBe(['tabs']);
});

it('draws an accordion as details, grouped when only one may open', function () {
    $faq = fn (bool $single) => drawBlocks([[
        'id' => 'b_faq', 'type' => 'accordion', 'attrs' => ['single' => $single],
        'children' => [
            ['id' => 'b_q1', 'type' => 'accordion-item', 'attrs' => ['title' => 'Do you bake gluten free bread?', 'open' => true], 'children' => [['id' => 'b_a1', 'type' => 'paragraph', 'content' => 'Every Friday.']]],
            ['id' => 'b_q2', 'type' => 'accordion-item', 'attrs' => ['title' => 'Can I order a cake?'], 'children' => [['id' => 'b_a2', 'type' => 'paragraph', 'content' => 'Two days ahead.']]],
        ],
    ]]);

    expect($faq(true))->toContain('<details class="verso-disclosure" open name="vb-b_faq">')
        ->toContain('<summary class="verso-disclosure__summary">Do you bake gluten free bread?</summary>')
        ->and($faq(false))->not->toContain('name="vb-b_faq"');
});

it('draws a slider with numbered slides and autoplay that can pause', function () {
    $html = drawBlocks([[
        'id' => 'b_slides', 'type' => 'slider', 'attrs' => ['label' => 'Customer stories', 'autoplay' => true, 'interval' => 5, 'perView' => '2'],
        'children' => [
            ['id' => 'b_s1', 'type' => 'slide', 'children' => [['id' => 'b_q1', 'type' => 'quote', 'content' => 'Best croissant in town.']]],
            ['id' => 'b_s2', 'type' => 'slide', 'children' => [['id' => 'b_q2', 'type' => 'quote', 'content' => 'Lovely staff.']]],
        ],
    ]]);

    expect($html)->toContain('aria-roledescription="carousel" aria-label="Customer stories"')
        ->toContain('data-autoplay="5"')
        ->toContain('verso-slider--per-2')
        ->toContain('aria-label="2 of 2"')
        ->toContain('data-verso-pause aria-pressed="false"');
});

it('draws an image comparison only with two safe pictures', function () {
    $picture = fn (string $url) => ['id' => 4, 'url' => $url, 'alt' => 'Shop front', 'width' => 1200, 'height' => 800];
    $html = drawBlocks([['id' => 'b_cmp', 'type' => 'image-compare', 'attrs' => ['before' => $picture('/storage/before.jpg'), 'after' => $picture('/storage/after.jpg'), 'beforeLabel' => 'Before', 'afterLabel' => 'After']]]);

    expect($html)->toContain('type="range" min="0" max="100" value="50"')
        ->toContain('width="1200" height="800"')
        ->and(drawBlocks([['id' => 'b_bad', 'type' => 'image-compare', 'attrs' => ['before' => $picture('javascript:alert(1)'), 'after' => $picture('/storage/after.jpg')]]]))
        ->not->toContain('verso-compare__frame');
});

it('draws icons from the bundled set and refuses unknown ones', function () {
    expect(app(IconLibrary::class)->has('croissant'))->toBeTrue()
        ->and(drawBlocks([['id' => 'b_i', 'type' => 'icon', 'attrs' => ['icon' => 'croissant', 'label' => 'Bakery']]]))
        ->toContain('role="img" aria-label="Bakery"')
        ->and(blocksProblem([['id' => 'b_i', 'type' => 'icon', 'attrs' => ['icon' => 'unicorn-laser']]]))->toContain('pick an icon');
});

it('refuses icons that are more than plain shapes', function () {
    expect(fn () => app(IconLibrary::class)->register('evil', '<script>alert(1)</script>'))->toThrow(InvalidArgumentException::class);

    app(IconLibrary::class)->register('harbor-boat', '<path d="M2 20h20"/><circle cx="12" cy="8" r="3"/>');

    expect(app(IconLibrary::class)->svg('harbor-boat'))->toContain('<circle cx="12" cy="8" r="3"/>');
});

it('draws counters, progress bars and alerts that read well without scripts', function () {
    $html = drawBlocks([
        ['id' => 'b_c', 'type' => 'counter', 'attrs' => ['number' => 1250, 'suffix' => '+', 'label' => 'loaves a week']],
        ['id' => 'b_p', 'type' => 'progress-bar', 'attrs' => ['label' => 'New oven fund', 'value' => 70]],
        ['id' => 'b_a', 'type' => 'alert', 'attrs' => ['tone' => 'warning', 'title' => 'Closed on Monday', 'text' => "We are painting the shop.\n\nSee you Tuesday."]],
    ]);

    expect($html)->toContain('<span class="verso-counter__number">1250</span>')
        ->toContain('<progress class="verso-progress__bar" id="vb-b_p-progress" max="100" value="70"></progress>')
        ->toContain('70%')
        ->toContain('<span class="verso-visually-hidden">Warning: </span>Closed on Monday')
        ->toContain('<p>See you Tuesday.</p>');
});

it('counts down to a date in the site time zone', function () {
    app(Settings::class)->set('site.timezone', 'Europe/Madrid');

    $html = drawBlocks([['id' => 'b_cd', 'type' => 'countdown', 'attrs' => ['until' => '2030-06-01T09:00', 'done' => 'We are open!']]]);

    expect($html)->toContain('data-until="2030-06-01T07:00:00+00:00"')
        ->toContain('data-done="We are open!"')
        ->and(blocksProblem([['id' => 'b_cd', 'type' => 'countdown', 'attrs' => ['until' => 'next friday']]]))->not->toBeNull();
});

it('shares the current entry with plain links and no tracking scripts', function () {
    $entry = Entry::factory()->published()->create(['type' => 'post', 'title' => 'Rye & honey loaf', 'slug' => 'rye-honey-loaf', 'blocks' => [
        ['id' => 'b_share', 'type' => 'social-share', 'attrs' => ['heading' => 'Share this recipe', 'whatsapp' => true]],
    ]]);

    $html = $this->get($entry->url())->assertOk()->getContent();

    expect($html)->toContain('https://wa.me/?text=Rye%20%26%20honey%20loaf%20'.rawurlencode($entry->url()))
        ->toContain('rel="noopener noreferrer"')
        ->toContain('data-verso-copy="'.e($entry->url()).'"')
        ->not->toContain('connect.facebook.net')
        ->not->toContain('platform.twitter.com');
});

it('fills the table of contents with the headings of the entry', function () {
    $entry = Entry::factory()->published()->create(['type' => 'post', 'slug' => 'sourdough-guide', 'blocks' => [
        ['id' => 'b_toc', 'type' => 'table-of-contents', 'attrs' => ['depth' => '3']],
        ['id' => 'b_h1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Feeding the starter'],
        ['id' => 'b_h2', 'type' => 'heading', 'attrs' => ['level' => 3], 'content' => 'Flour and water'],
        ['id' => 'b_h3', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Baking day'],
    ]]);

    $html = $this->get($entry->url())->assertOk()->getContent();

    expect($html)->toContain('<a href="#feeding-the-starter">Feeding the starter</a><ol><li><a href="#flour-and-water">Flour and water</a></li></ol></li>')
        ->toContain('id="baking-day" tabindex="-1"')
        ->not->toContain(TableOfContentsBlock::SLOT);
});

it('opens gallery pictures in a lightbox only when asked', function () {
    $gallery = fn (bool $lightbox) => drawBlocks([['id' => 'b_g', 'type' => 'gallery', 'attrs' => ['lightbox' => $lightbox, 'images' => [['src' => '/storage/media/rye.jpg', 'alt' => 'Rye loaf']]]]]);

    expect($gallery(true))->toContain('<a class="verso-lightbox__open" href="/storage/media/rye.jpg" data-verso-lightbox>')
        ->toContain('verso-blocks/lightbox.js')
        ->and($gallery(false))->not->toContain('lightbox');
});

it('names lightbox links of pictures without alt text', function () {
    $html = drawBlocks([['id' => 'b_g', 'type' => 'gallery', 'attrs' => ['lightbox' => true, 'images' => [
        ['src' => '/storage/media/rye.jpg', 'alt' => 'Rye loaf'],
        ['src' => '/storage/media/sourdough.jpg'],
    ]]]]);

    expect($html)->toContain('<a class="verso-lightbox__open" href="/storage/media/rye.jpg" data-verso-lightbox>')
        ->toContain('<a class="verso-lightbox__open" href="/storage/media/sourdough.jpg" aria-label="Open picture 2" data-verso-lightbox>');
});

it('names icon links by their label or else by the site they open', function () {
    expect(drawBlocks([['id' => 'b_i', 'type' => 'icon', 'attrs' => ['icon' => 'croissant', 'label' => 'Our bakery', 'url' => 'https://example.org/bakery']]]))
        ->toContain('href="https://example.org/bakery" aria-label="Our bakery"')
        ->and(drawBlocks([['id' => 'b_i', 'type' => 'icon', 'attrs' => ['icon' => 'croissant', 'url' => 'https://www.instagram.com/harborbakery']]]))
        ->toContain('aria-label="instagram.com"');
});

it('draws a modal with a native dialog and a video background that can pause', function () {
    $html = drawBlocks([
        ['id' => 'b_m', 'type' => 'modal', 'attrs' => ['button' => 'See the menu', 'title' => 'Our menu'], 'children' => [['id' => 'b_mp', 'type' => 'paragraph', 'content' => 'Croissant, 2 euros.']]],
        ['id' => 'b_v', 'type' => 'video-background', 'attrs' => ['video' => '/storage/media/oven.mp4'], 'children' => [['id' => 'b_vh', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Baked at dawn']]],
    ]);

    expect($html)->toContain('<dialog class="verso-dialog verso-dialog--medium" id="vb-b_m-dialog" aria-labelledby="vb-b_m-dialog-title" closedby="any">')
        ->toContain('data-opens="vb-b_m-dialog"')
        ->toContain('<video class="verso-video-bg__video" src="/storage/media/oven.mp4" muted loop playsinline')
        ->toContain('class="verso-video-bg__pause" aria-pressed="false"');
});

it('draws layout blocks without inline styles', function () {
    $html = drawBlocks([
        ['id' => 'b_grid', 'type' => 'grid', 'attrs' => ['minWidth' => '20', 'gap' => '2'], 'children' => [['id' => 'b_g1', 'type' => 'paragraph', 'content' => 'One']]],
        ['id' => 'b_stack', 'type' => 'stack', 'attrs' => ['direction' => 'horizontal', 'space' => 'small']],
        ['id' => 'b_space', 'type' => 'spacer', 'attrs' => ['size' => 'large']],
        ['id' => 'b_wave', 'type' => 'shape-divider', 'attrs' => ['shape' => 'zigzag', 'flip' => true]],
    ]);

    expect($html)->toContain('class="verso-grid verso-grid--min-20 verso-grid--gap-2"')
        ->toContain('verso-stack verso-stack--horizontal verso-stack--space-small')
        ->toContain('verso-spacer verso-spacer--large')
        ->toContain('verso-shape verso-shape--medium verso-shape--flip')
        ->not->toContain(' style="');
});

it('serves block files with long caching', function () {
    $url = app(BlockAssetRegistry::class)->url('tabs', 'js');
    $response = $this->get($url)->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/javascript')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable');

    $this->get($url, ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    $this->get('/verso-blocks/missing.js')->assertNotFound();
    $this->get('/verso-blocks/..%2F.env.js')->assertNotFound();
});

it('serves the icon set as JSON for the editor', function () {
    $this->get(app(BlockAssetRegistry::class)->url('icon-set', 'json'))
        ->assertOk()
        ->assertJsonPath('icons.croissant', fn (string $svg): bool => str_starts_with($svg, '<path'));
});

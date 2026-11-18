<?php

use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Styles\BlockStyleSchema;
use Tests\Support\EasyFixtures;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Support\DemoContactForm;

dataset('core sections', ['hero', 'about', 'services', 'gallery', 'testimonials', 'pricing', 'team', 'faq', 'contact', 'map', 'cta']);

// A picture as the starter install gives it to sections.
function harborLanePicture(int $id = 7): array
{
    return ['mediaId' => $id, 'src' => "/storage/easy/restaurant/loaves-{$id}.jpg", 'alt' => 'Sourdough loaves on a shelf.', 'width' => 1200, 'height' => 800];
}

it('builds valid blocks that render with the default content', function (string $key) {
    $blocks = app(SectionLibrary::class)->build($key);

    expect($blocks)->not->toBeEmpty()
        ->and(EasyFixtures::blockError($blocks))->toBeNull()
        ->and(app(BlockRenderer::class)->render($blocks))->toContain('verso-section');
})->with('core sections');

it('builds valid blocks with pictures, items and buttons', function (string $key) {
    $blocks = app(SectionLibrary::class)->build($key, [
        'heading' => 'Harbor Lane Bakery',
        'text' => "Bread baked at five.\n\nGone by noon.",
        'images' => [harborLanePicture(7), harborLanePicture(8)],
        'items' => [
            ['title' => 'Country sourdough', 'text' => 'Our daily loaf.', 'meta' => 'Head baker', 'price' => '€5.50', 'features' => ['24 hour rise'], 'image' => harborLanePicture(9), 'highlight' => true],
        ],
        'button' => ['label' => 'See the menu', 'url' => '/menu'],
        'extra' => ['address' => 'Rua do Cais 12, Porto', 'phone' => '+351 220 123 456', 'latitude' => 41.1405, 'longitude' => -8.6133],
    ]);

    expect(EasyFixtures::blockError($blocks))->toBeNull()
        ->and(app(BlockRenderer::class)->render($blocks))->toContain('Harbor Lane Bakery');
})->with('core sections');

it('styles sections with theme tokens the style schema accepts', function (string $key) {
    $tokens = EasyFixtures::tokens(app(SectionLibrary::class)->build($key, ['button' => ['label' => 'Go', 'url' => '/'], 'items' => [['title' => 'Plus', 'highlight' => true]]]));
    $schema = new BlockStyleSchema;

    expect($tokens)->not->toBeEmpty();

    foreach ($tokens as $token) {
        expect($schema->css('length', $token))->not->toBeNull()
            ->and(in_array($token, [
                SectionKit::SPACE_SECTION, SectionKit::SPACE_LARGE, SectionKit::SPACE_MEDIUM, SectionKit::SPACE_SMALL,
                SectionKit::RADIUS, SectionKit::COLOR_ACCENT, SectionKit::COLOR_SURFACE, SectionKit::COLOR_BACKGROUND,
            ], true))->toBeTrue();
    }
})->with('core sections');

it('puts a picture next to the hero text when one is given', function () {
    $library = app(SectionLibrary::class);

    $plain = $library->build('hero');
    $withPicture = $library->build('hero', ['images' => [harborLanePicture()]]);

    expect($plain[0]['children'][0]['type'])->toBe('section')
        ->and($withPicture[0]['children'][0]['type'])->toBe('columns')
        ->and(app(BlockRenderer::class)->render($withPicture))->toContain('alt="Sourdough loaves on a shelf."');
});

it('leaves out pictures without a source', function () {
    $blocks = app(SectionLibrary::class)->build('gallery', ['images' => [['alt' => 'Lost picture']]]);

    expect(collect($blocks[0]['children'])->pluck('type')->all())->toBe(['heading']);
});

it('escapes the text of sections', function () {
    $html = app(BlockRenderer::class)->render(app(SectionLibrary::class)->build('faq', [
        'heading' => 'Bread & <b>butter</b>',
        'items' => [['title' => 'Is it <script>fresh</script>?', 'text' => 'Yes & always.']],
    ]));

    expect($html)->toContain('Bread &amp; ')
        ->not->toContain('<script>')
        ->not->toContain('<b>butter</b>');
});

it('shows contact details as text when the Forms plugin is off', function () {
    $blocks = app(SectionLibrary::class)->build('contact', ['extra' => ['email' => 'hello@harborlane.example', 'hours' => 'Tuesday to Sunday']]);
    $html = app(BlockRenderer::class)->render($blocks);

    expect(json_encode($blocks))->not->toContain('"type":"form"')
        ->and($html)->toContain('Email: hello@harborlane.example')
        ->and($html)->toContain('Opening hours: Tuesday to Sunday');
});

it('keeps only the details when the Forms plugin is on but the contact form is missing', function () {
    PluginFixtures::boot('forms');

    $blocks = app(SectionLibrary::class)->build('contact');

    expect(json_encode($blocks))->not->toContain('"type":"form"');
});

it('adds the contact form block when the Forms plugin is on and the form exists', function () {
    PluginFixtures::boot('forms');
    app(DemoContactForm::class)->seed();

    $blocks = app(SectionLibrary::class)->build('contact', ['extra' => ['email' => 'hello@harborlane.example', 'form_title' => 'Book a table']]);
    $form = collect($blocks[0]['children'][0]['children'][1]['children'])->firstWhere('type', 'form');

    expect($form['attrs'])->toMatchArray(['form' => 'contact', 'title' => 'Book a table'])
        ->and(EasyFixtures::blockError($blocks))->toBeNull();
});

it('places a map block with the coordinates of the place', function () {
    $blocks = app(SectionLibrary::class)->build('map', ['extra' => ['latitude' => 41.1405, 'longitude' => -8.6133, 'zoom' => 16, 'label' => 'Harbor Lane Bakery']]);
    $map = collect($blocks[0]['children'])->firstWhere('type', 'map');

    expect($map['attrs'])->toMatchArray(['latitude' => 41.1405, 'longitude' => -8.6133, 'zoom' => 16, 'label' => 'Harbor Lane Bakery'])
        ->and(app(BlockRenderer::class)->render($blocks))->toContain('Map of Harbor Lane Bakery');
});

it('marks the highlighted price card with the accent color', function () {
    $blocks = app(SectionLibrary::class)->build('pricing', ['items' => [['title' => 'Basic', 'price' => '€29'], ['title' => 'Plus', 'price' => '€59', 'highlight' => true]]]);
    $cards = $blocks[0]['children'][1]['children'];

    expect($cards[0]['attrs']['design']['border'])->not->toHaveKey('color')
        ->and($cards[1]['attrs']['design']['border']['color'])->toBe(SectionKit::COLOR_ACCENT);
});

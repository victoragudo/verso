<?php

use App\Verso\Easy\Sections\SectionContent;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Sections\SectionTemplate;
use App\Verso\Plugins\PluginManager;
use Tests\Support\EasyFixtures;

function bakeryHoursSection(?string $plugin = null): SectionTemplate
{
    return new SectionTemplate(
        key: 'opening-hours',
        label: 'Opening hours',
        description: 'The days and hours you are open.',
        builder: fn (SectionContent $content, SectionKit $kit): array => [$kit->section([
            $kit->heading($content->heading),
            $kit->list(array_map(fn (array $item): string => (string) $item['title'], $content->items)),
        ])],
        icon: 'clock',
        category: 'contact',
        keywords: ['hours', 'horario'],
        plugin: $plugin,
        defaults: new SectionContent(heading: 'Opening hours', items: [['title' => 'Monday to Friday, 7:00 to 15:00']]),
    );
}

it('registers every core section through the public API', function () {
    $library = app(SectionLibrary::class);

    expect(array_keys($library->all()))->toContain('hero', 'about', 'services', 'gallery', 'testimonials', 'pricing', 'team', 'faq', 'contact', 'map', 'cta')
        ->and($library->has('hero'))->toBeTrue()
        ->and($library->find('ice-cream-van'))->toBeNull()
        ->and(fn () => $library->get('ice-cream-van'))->toThrow(InvalidArgumentException::class);
});

it('lets plugins and themes add a section and replaces one with the same key', function () {
    $library = app(SectionLibrary::class);
    $library->register(bakeryHoursSection());

    expect($library->get('opening-hours')->label)->toBe('Opening hours');

    $library->register(new SectionTemplate('opening-hours', 'Hours', 'Short hours.', fn (): array => []));

    expect($library->get('opening-hours')->label)->toBe('Hours');
});

it('rejects section keys with spaces or capitals', function () {
    expect(fn () => new SectionTemplate('Opening Hours', 'Opening hours', 'The hours.', fn (): array => []))
        ->toThrow(InvalidArgumentException::class);
});

it('builds a section with its defaults and with given content', function () {
    $section = bakeryHoursSection();

    $default = $section->build();
    $custom = $section->build(['heading' => 'When to find us', 'items' => [['title' => 'Saturday, 8:00 to 13:00']]]);

    expect($default[0]['children'][0]['content'])->toBe('Opening hours')
        ->and($default[0]['children'][1]['content'])->toBe('<li>Monday to Friday, 7:00 to 15:00</li>')
        ->and($custom[0]['children'][0]['content'])->toBe('When to find us')
        ->and($custom[0]['children'][1]['content'])->toBe('<li>Saturday, 8:00 to 13:00</li>')
        ->and(EasyFixtures::blockError($custom))->toBeNull();
});

it('gives fresh block ids on every build', function () {
    $library = app(SectionLibrary::class);
    $kit = app(SectionKit::class);

    $ids = EasyFixtures::ids([...$library->build('services', [], $kit), ...$library->build('services', [], $kit)]);

    expect($ids)->toHaveCount(count(array_unique($ids)))
        ->and(EasyFixtures::ids($library->build('hero')))->not->toBe(EasyFixtures::ids($library->build('hero')));
});

it('hides sections whose plugin is turned off', function () {
    $plugins = Mockery::mock(PluginManager::class);
    $plugins->allows('isEnabled')->with('shop')->andReturn(false);
    $plugins->allows('isEnabled')->with('forms')->andReturn(true);
    $library = new SectionLibrary($plugins);
    $library->register(bakeryHoursSection('shop'));
    $library->register(new SectionTemplate('form-only', 'Form', 'A form.', fn (): array => [], plugin: 'forms'));

    expect(array_keys($library->available()))->toBe(['form-only'])
        ->and(array_column($library->toArray(), 'key'))->toBe(['form-only']);
});

it('lists sections for the gallery with their search words and defaults', function () {
    $hero = collect(app(SectionLibrary::class)->toArray())->firstWhere('key', 'hero');

    expect($hero)->toMatchArray(['label' => 'Welcome banner', 'icon' => 'site', 'category' => 'intro', 'plugin' => null])
        ->and($hero['keywords'])->toContain('banner')
        ->and($hero['defaults']['heading'])->toBe('Welcome to our place');
});

it('reads content from a plain array and keeps only valid parts', function () {
    $content = SectionContent::from([
        'heading' => '  Fresh bread daily ',
        'items' => [['title' => 'Rye'], 'not a row'],
        'button' => ['label' => '', 'url' => '/menu'],
        'extra' => ['phone' => '+351 220 123 456'],
    ]);

    expect($content->heading)->toBe('Fresh bread daily')
        ->and($content->items)->toBe([['title' => 'Rye']])
        ->and($content->button)->toBeNull()
        ->and($content->extraText('phone'))->toBe('+351 220 123 456')
        ->and($content->extraText('email', 'none'))->toBe('none');
});

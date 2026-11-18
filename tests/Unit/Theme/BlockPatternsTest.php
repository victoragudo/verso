<?php

use App\Verso\Theme\Templates\BlockPattern;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\ThemeManager;
use Tests\Support\ThemeSandbox;

// A pattern file body with the given fields and one paragraph block.
function patternJson(array $fields = []): string
{
    return json_encode([
        'title' => 'Opening hours',
        'category' => 'Text',
        'blocks' => [['type' => 'paragraph', 'attrs' => [], 'content' => '<p>Open every day from nine to five.</p>']],
        ...$fields,
    ]);
}

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
});

afterEach(function () {
    $this->sandbox->destroy();
});

describe('BlockPattern', function () {
    it('reads a pattern with all its fields', function () {
        $pattern = BlockPattern::fromArray([
            'name' => 'ferry-times',
            'title' => 'Ferry times',
            'category' => 'Tables',
            'description' => 'A heading and a timetable.',
            'keywords' => ['schedule', 'boats', 7],
            'blocks' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Ferry times'],
                ['type' => 'columns', 'children' => [['type' => 'column', 'children' => [['type' => 'paragraph', 'content' => '<p>Vaxholm 08:15</p>']]]]],
            ],
        ], 'fallback-name');

        expect($pattern->name)->toBe('ferry-times')
            ->and($pattern->title)->toBe('Ferry times')
            ->and($pattern->category)->toBe('Tables')
            ->and($pattern->keywords)->toBe(['schedule', 'boats'])
            ->and($pattern->blockTypes())->toBe(['heading', 'columns', 'column', 'paragraph'])
            ->and($pattern->toArray())->toHaveKeys(['name', 'title', 'category', 'description', 'keywords', 'blocks']);
    });

    it('fills the name, title and category when they are missing', function () {
        $pattern = BlockPattern::fromArray(['blocks' => [['type' => 'separator']]], 'quiet-divider');

        expect($pattern->name)->toBe('quiet-divider')
            ->and($pattern->title)->toBe('Quiet Divider')
            ->and($pattern->category)->toBe(BlockPattern::DEFAULT_CATEGORY)
            ->and($pattern->keywords)->toBe([]);
    });

    it('refuses broken patterns', function (array $data) {
        expect(BlockPattern::fromArray($data, 'harbor-hero'))->toBeNull();
    })->with([
        'no blocks' => [['title' => 'Empty']],
        'empty blocks' => [['blocks' => []]],
        'blocks not a list' => [['blocks' => ['first' => ['type' => 'paragraph']]]],
        'block without type' => [['blocks' => [['content' => '<p>Hello</p>']]]],
        'block that is text' => [['blocks' => ['paragraph']]],
        'broken children' => [['blocks' => [['type' => 'columns', 'children' => 'column']]]],
        'bad nested block' => [['blocks' => [['type' => 'columns', 'children' => [['attrs' => []]]]]]],
        'bad name' => [['name' => 'Harbor Hero', 'blocks' => [['type' => 'paragraph']]]],
    ]);
});

describe('BlockPatterns', function () {
    it('ships patterns with the default theme', function () {
        $names = array_map(fn (BlockPattern $pattern) => $pattern->name, app(BlockPatterns::class)->all());

        expect($names)->toBe(['call-to-action', 'two-columns', 'pull-quote']);
    });

    it('sorts patterns by category and then title', function () {
        $this->sandbox->theme('harbor', ['parent' => 'default'], [
            'patterns/zebra.json' => patternJson(['title' => 'Zebra crossing note', 'category' => 'Call to action']),
            'patterns/alpha.json' => patternJson(['title' => 'Alpine notice', 'category' => 'Call to action']),
        ])->activate('harbor');

        $titles = array_map(fn (BlockPattern $pattern) => $pattern->title, app(BlockPatterns::class)->all());

        expect($titles)->toBe(['Alpine notice', 'Call to action', 'Zebra crossing note', 'Two columns with headings', 'Pull quote between separators']);
    });

    it('lets a child theme replace a parent pattern by name', function () {
        $this->sandbox->theme('harbor', ['parent' => 'default'], [
            'patterns/my-cta.json' => patternJson(['name' => 'call-to-action', 'title' => 'Book a harbor tour', 'category' => 'Call to action']),
        ])->activate('harbor');

        $patterns = app(BlockPatterns::class)->all();
        $callToAction = array_values(array_filter($patterns, fn (BlockPattern $pattern) => $pattern->name === 'call-to-action'));

        expect($patterns)->toHaveCount(3)
            ->and($callToAction[0]->title)->toBe('Book a harbor tour');
    });

    it('does not show the patterns of the default theme to themes that are not its children', function () {
        $this->sandbox->theme('harbor', [], ['patterns/hours.json' => patternJson()])->activate('harbor');

        expect(app(BlockPatterns::class)->toArray())->toBe([[
            'name' => 'hours',
            'title' => 'Opening hours',
            'category' => 'Text',
            'description' => '',
            'keywords' => [],
            'blocks' => [['type' => 'paragraph', 'attrs' => [], 'content' => '<p>Open every day from nine to five.</p>']],
        ]]);
    });

    it('skips broken, unknown and too large pattern files', function () {
        config(['verso.themes.pattern_max_kb' => 1]);

        $this->sandbox->theme('harbor', [], [
            'patterns/hours.json' => patternJson(),
            'patterns/broken.json' => '{"title": "Broken",',
            'patterns/list.json' => '["paragraph"]',
            'patterns/unknown-block.json' => patternJson(['blocks' => [['type' => 'carousel']]]),
            'patterns/huge.json' => patternJson(['description' => str_repeat('Long harbor story. ', 80)]),
            'patterns/notes.txt' => patternJson(),
        ])->activate('harbor');

        expect(array_map(fn (BlockPattern $pattern) => $pattern->name, app(BlockPatterns::class)->all()))->toBe(['hours']);
    });

    it('reads the patterns of a given theme', function () {
        $this->sandbox->theme('harbor', [], ['patterns/hours.json' => patternJson()]);

        expect(app(BlockPatterns::class)->all(app(ThemeManager::class)->find('harbor')))->toHaveCount(1)
            ->and(app(BlockPatterns::class)->all())->toHaveCount(3);
    });
});

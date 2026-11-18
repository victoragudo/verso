<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;
use Tests\Support\PluginFixtures;
use Verso\Plugins\ReadingTime\ReadingTime;

// A paragraph of exactly $words words.
function paragraphOf(int $words): array
{
    return ['id' => 'b_'.$words, 'type' => 'paragraph', 'attrs' => [], 'content' => trim(str_repeat('harbor ', $words))];
}

beforeEach(function () {
    $this->manager = PluginFixtures::use(base_path('plugins'));
    $this->manager->enable('reading-time');
    $this->manager->register($this->manager->get('reading-time'));
});

it('ships a valid manifest that works with this Verso version', function () {
    $plugin = $this->manager->get('reading-time');

    expect($plugin->canBeEnabled())->toBeTrue()
        ->and($plugin->manifest->name)->toBe('Reading Time')
        ->and($plugin->manifest->permissions)->toBe([]);
});

it('counts words in nested blocks and rounds minutes up', function () {
    $entry = new Entry(['blocks' => [
        paragraphOf(150),
        ['id' => 'b_cols', 'type' => 'columns', 'attrs' => [], 'children' => [
            ['id' => 'b_col', 'type' => 'column', 'attrs' => [], 'children' => [paragraphOf(100)]],
        ]],
    ]]);

    $readingTime = app(ReadingTime::class);

    expect($readingTime->words($entry))->toBe(250)
        ->and($readingTime->minutes($entry))->toBe(2);
});

it('says at least one minute for short or empty entries', function () {
    expect(app(ReadingTime::class)->minutes(new Entry(['blocks' => []])))->toBe(1);
});

it('reads the speed and label from its config', function () {
    config(['reading-time.words_per_minute' => 100, 'reading-time.label' => ':minutes minutes']);
    app()->forgetInstance(ReadingTime::class);

    $readingTime = app(ReadingTime::class);
    $entry = new Entry(['blocks' => [paragraphOf(250)]]);

    expect($readingTime->minutes($entry))->toBe(3)
        ->and($readingTime->badge(3))->toBe('<p class="reading-time">3 minutes</p>');
});

it('registers the reading-time block', function () {
    $entry = new Entry(['title' => 'Slow Mornings in Lisbon', 'blocks' => [
        ['id' => 'b_rt', 'type' => 'reading-time', 'attrs' => []],
        paragraphOf(420),
    ]]);

    expect(app(PluginBlockRegistry::class)->find('reading-time')?->label())->toBe('Reading time')
        ->and(app(ContentRenderer::class)->render($entry))->toStartWith('<p class="reading-time">3 min read</p><p>harbor');
});

it('renders the block outside an entry from its attrs', function () {
    $block = app(PluginBlockRegistry::class)->find('reading-time');

    expect($block->render(['type' => 'reading-time', 'attrs' => ['minutes' => 7]]))->toBe('<p class="reading-time">7 min read</p>')
        ->and($block->render(['type' => 'reading-time', 'attrs' => []]))->toBe('');
});

it('adds the badge on top of entries when auto insert is on', function () {
    config(['reading-time.auto_insert' => true]);
    $entry = new Entry(['blocks' => [paragraphOf(10)]]);

    expect(EntryContentFilter::apply('<p>Body</p>', $entry))->toBe('<p class="reading-time">1 min read</p><p>Body</p>');
});

it('does not add the badge twice or when auto insert is off', function () {
    $withBlock = new Entry(['blocks' => [['id' => 'b_rt', 'type' => 'reading-time', 'attrs' => []]]]);
    $plain = new Entry(['blocks' => [paragraphOf(10)]]);

    expect(EntryContentFilter::apply('<p>Body</p>', $plain))->toBe('<p>Body</p>');

    config(['reading-time.auto_insert' => true]);

    expect(EntryContentFilter::apply('<p>Body</p>', $withBlock))->toBe('<p>Body</p>');
});

it('adds the reading time to API data', function () {
    $entry = new Entry(['blocks' => [paragraphOf(610)]]);

    expect(ApiEntryFilter::apply(['title' => 'Slow Mornings in Lisbon'], $entry))
        ->toBe(['title' => 'Slow Mornings in Lisbon', 'reading_time' => 4]);
});

it('finds reading-time blocks nested in columns', function () {
    expect(app(ReadingTime::class)->hasBlock([
        ['type' => 'columns', 'children' => [['type' => 'column', 'children' => [['type' => 'reading-time']]]]],
    ]))->toBeTrue()
        ->and(app(ReadingTime::class)->hasBlock([paragraphOf(3), 'junk']))->toBeFalse();
});

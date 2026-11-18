<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Exceptions\InvalidFilterResult;
use App\Verso\Plugins\Exceptions\UnknownFilter;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;

beforeEach(function () {
    $this->filters = app(FilterRegistry::class);
    $this->entry = new Entry(['title' => 'A Weekend in Porto', 'type' => 'post', 'slug' => 'a-weekend-in-porto']);
});

it('returns the value unchanged when no callback is added', function () {
    // A new registry: core adds its own callbacks (like custom page code) to the shared one.
    $filters = new FilterRegistry;

    expect($filters->apply(EntryContentFilter::class, '<p>Ribeira at dusk.</p>', $this->entry))
        ->toBe('<p>Ribeira at dusk.</p>')
        ->and($filters->has(EntryContentFilter::class))->toBeFalse();
});

it('runs callbacks by priority and passes the context', function () {
    $this->filters->add(EntryContentFilter::class, fn (string $html, Entry $entry) => $html.'<p>Filed under '.$entry->title.'</p>', 20);
    $this->filters->add(EntryContentFilter::class, fn (string $html) => '<div>'.$html.'</div>', 5);

    expect(EntryContentFilter::apply('<p>Ribeira at dusk.</p>', $this->entry))
        ->toBe('<div><p>Ribeira at dusk.</p></div><p>Filed under A Weekend in Porto</p>');
});

it('runs callbacks with the same priority in the order they were added', function () {
    Filters::add(EntryContentFilter::class, fn (string $html) => $html.'1');
    Filters::add(EntryContentFilter::class, fn (string $html) => $html.'2');

    expect(Filters::apply(EntryContentFilter::class, 'x', $this->entry))->toBe('x12');
});

it('filters API data', function () {
    Filters::add(ApiEntryFilter::class, fn (array $data) => [...$data, 'mood' => 'sunny']);

    expect(ApiEntryFilter::apply(['title' => 'A Weekend in Porto'], $this->entry))
        ->toBe(['title' => 'A Weekend in Porto', 'mood' => 'sunny']);
});

it('throws when a callback returns the wrong type', function () {
    $this->filters->add(EntryContentFilter::class, fn (string $html) => ['oops']);

    expect(fn () => EntryContentFilter::apply('<p>Hi</p>', $this->entry))
        ->toThrow(InvalidFilterResult::class, 'returned array, but string was expected');
});

it('only accepts filter classes', function () {
    expect(fn () => $this->filters->add(Entry::class, fn ($value) => $value))->toThrow(UnknownFilter::class)
        ->and(fn () => $this->filters->apply('Missing\\Filter', 'x'))->toThrow(UnknownFilter::class);
});

it('clears the callbacks of one filter', function () {
    $this->filters->add(EntryContentFilter::class, fn (string $html) => strtoupper($html));
    $this->filters->clear(EntryContentFilter::class);

    expect($this->filters->has(EntryContentFilter::class))->toBeFalse()
        ->and(EntryContentFilter::apply('quiet', $this->entry))->toBe('quiet');
});

it('is a singleton behind the facade', function () {
    Filters::add(EntryContentFilter::class, fn (string $html) => $html);

    expect(app(FilterRegistry::class)->has(EntryContentFilter::class))->toBeTrue();
});

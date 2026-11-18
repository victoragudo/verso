<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Database\Factories\EntryFactory;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);

    $this->author = User::factory()->author()->create(['name' => 'Marta Ruiz']);
    app(Settings::class)->setMany(['site.name' => 'Casa Ruiz Journal', 'site.tagline' => 'Food, travel and slow living']);
});

function feedXml($response): SimpleXMLElement
{
    return simplexml_load_string($response->getContent());
}

it('returns a valid RSS document', function () {
    $response = $this->get('/feed')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $xml = feedXml($response);

    expect((string) $xml['version'])->toBe('2.0')
        ->and((string) $xml->channel->title)->toBe('Casa Ruiz Journal')
        ->and((string) $xml->channel->description)->toBe('Food, travel and slow living')
        ->and((string) $xml->channel->link)->toBe(url('/'));
});

it('lists published posts, newest first', function () {
    Entry::factory()->published()->by($this->author)->create(['title' => 'Older Post About Olive Oil', 'published_at' => now()->subWeek()]);
    Entry::factory()->published()->by($this->author)->create([
        'title' => 'Cooking Paella for Twelve People',
        'slug' => 'paella-for-twelve',
        'excerpt' => 'Rice, saffron and a very big pan.',
        'published_at' => now()->subDay(),
        'blocks' => EntryFactory::paragraphs('Start the fire one hour early.'),
    ]);

    $items = feedXml($this->get('/feed'))->channel->item;

    expect($items)->toHaveCount(2)
        ->and((string) $items[0]->title)->toBe('Cooking Paella for Twelve People')
        ->and((string) $items[0]->link)->toBe(url('/blog/paella-for-twelve'))
        ->and((string) $items[0]->description)->toBe('Rice, saffron and a very big pan.')
        ->and((string) $items[0]->pubDate)->toBe('Sun, 15 Nov 2026 12:00:00 +0000')
        ->and((string) $items[0]->children('content', true)->encoded)->toContain('Start the fire one hour early.')
        ->and((string) $items[0]->children('dc', true)->creator)->toBe('Marta Ruiz')
        ->and((string) $items[1]->title)->toBe('Older Post About Olive Oil');
});

it('leaves out drafts, scheduled posts and pages', function () {
    Entry::factory()->by($this->author)->create(['title' => 'Draft Winter Soups']);
    Entry::factory()->scheduled()->by($this->author)->create(['title' => 'Scheduled Spring Menu']);
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'About the Family']);

    expect(feedXml($this->get('/feed'))->channel->item)->toHaveCount(0);
});

it('limits the number of items', function () {
    config(['verso.themes.feed_items' => 3]);
    Entry::factory()->count(5)->published()->by($this->author)->create();

    expect(feedXml($this->get('/feed'))->channel->item)->toHaveCount(3);
});

it('escapes titles with special characters', function () {
    Entry::factory()->published()->by($this->author)->create(['title' => 'Salt & Pepper <Notes>']);

    expect((string) feedXml($this->get('/feed'))->channel->item[0]->title)->toBe('Salt & Pepper <Notes>');
});

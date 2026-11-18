<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    app(Settings::class)->setMany(['site.name' => 'Porto Cycling Club', 'site.tagline' => 'Weekend rides along the Douro']);
});

it('renders meta, open graph, twitter and json-ld tags for an entry', function () {
    $post = Entry::factory()->published()->create([
        'title' => 'Choosing a Bike for City Commuting',
        'slug' => 'city-bike',
        'excerpt' => 'Gears, racks and lights that survive cobblestones.',
        'meta' => ['seo' => ['og_image' => 'https://cdn.example.org/bike.jpg']],
    ]);

    $html = Blade::render('<x-verso-seo :entry="$entry" />', ['entry' => $post]);

    expect($html)
        ->toContain('<title>Choosing a Bike for City Commuting | Porto Cycling Club</title>')
        ->toContain('<meta name="description" content="Gears, racks and lights that survive cobblestones.">')
        ->toContain('<link rel="canonical" href="'.url('/blog/city-bike').'">')
        ->toContain('<meta property="og:type" content="article">')
        ->toContain('<meta property="og:image" content="https://cdn.example.org/bike.jpg">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<script type="application/ld+json">')
        ->toContain('"@type": "BlogPosting"')
        ->toContain('"@type": "BreadcrumbList"')
        ->toContain('"@type": "SearchAction"')
        ->not->toContain('name="robots"');
});

it('renders a robots tag for noindex entries', function () {
    $page = Entry::factory()->page()->published()->create(['meta' => ['seo' => ['noindex' => true]]]);

    expect(Blade::render('<x-verso-seo :entry="$entry" />', ['entry' => $page]))
        ->toContain('<meta name="robots" content="noindex, follow">');
});

it('renders the website tags without an entry', function () {
    $html = Blade::render('<x-verso-seo />');

    expect($html)->toContain('<title>Porto Cycling Club | Weekend rides along the Douro</title>')
        ->toContain('"@type": "WebSite"')
        ->not->toContain('"@type": "Article"');
});

it('escapes html in meta values', function () {
    $post = Entry::factory()->published()->create(['title' => 'Rides "fast" & <slow>']);

    expect(Blade::render('<x-verso-seo :entry="$entry" />', ['entry' => $post]))
        ->toContain('Rides &quot;fast&quot; &amp; &lt;slow&gt;')
        ->not->toContain('<slow>');
});

it('stores clean seo fields when an entry is saved from the editor', function () {
    $editor = User::factory()->editor()->create();
    $post = Entry::factory()->published()->create(['meta' => ['reading_time' => 4]]);

    $this->actingAs($editor)->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $post->id]), [
        'title' => $post->title,
        'status' => 'published',
        'meta' => [
            'reading_time' => 4,
            'seo' => [
                'title' => ' Ten Lessons from Remote Work ',
                'description' => 'What a year away from the office taught our team.',
                'canonical' => 'javascript:alert(1)',
                'noindex' => '1',
                'og_image' => 'https://cdn.example.org/remote.jpg',
                'focus_keyphrase' => '  remote work lessons ',
                'nofollow' => 'true',
                'og_title' => '<b>Ten lessons</b> we learned',
                'score' => '142',
            ],
        ],
    ])->assertRedirect();

    expect($post->fresh()->meta)->toBeJsonData([
        'reading_time' => 4,
        'seo' => [
            'title' => 'Ten Lessons from Remote Work',
            'description' => 'What a year away from the office taught our team.',
            'canonical' => null,
            'noindex' => true,
            'og_image' => 'https://cdn.example.org/remote.jpg',
            'sitemap_exclude' => false,
            'focus_keyphrase' => 'remote work lessons',
            'nofollow' => true,
            'og_title' => 'Ten lessons we learned',
            'og_description' => null,
            'score' => 100,
        ],
    ]);
});

it('leaves entries without seo meta untouched', function () {
    $post = Entry::factory()->create(['meta' => ['reading_time' => 7]]);

    expect($post->fresh()->meta)->toBeJsonData(['reading_time' => 7]);
});

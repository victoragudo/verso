<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Seo\FaqSchema;
use App\Verso\Site\Seo\SeoBuilder;

beforeEach(function () {
    app(Settings::class)->setMany([
        'site.name' => 'Harbor Lane Bakery',
        'site.tagline' => 'Bread, pastries and stories from the harbor',
        'site.url' => 'https://harborlane.example.org',
        'site.locale' => 'en',
    ]);
});

// Every node must have a type, and ids must not repeat.
function assertValidGraph(string $json): array
{
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@graph'])->toBeArray()->not->toBeEmpty();

    $ids = [];
    foreach ($data['@graph'] as $node) {
        expect($node)->toHaveKey('@type');
        if (isset($node['@id'])) {
            $ids[] = $node['@id'];
        }
    }

    expect($ids)->toBe(array_values(array_unique($ids)));

    return $data['@graph'];
}

function graphNode(array $graph, string $type): ?array
{
    return collect($graph)->firstWhere('@type', $type);
}

function faqDetails(string $question, string $answer): array
{
    return [
        'id' => 'b_'.substr(md5($question), 0, 6),
        'type' => 'element',
        'attrs' => ['tag' => 'details'],
        'children' => [
            ['id' => 'b_s'.substr(md5($question), 0, 5), 'type' => 'element', 'attrs' => ['tag' => 'summary'], 'content' => $question],
            ['id' => 'b_p'.substr(md5($answer), 0, 5), 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.$answer.'</p>'],
        ],
    ];
}

it('describes a post as a BlogPosting with its author person', function () {
    $author = User::factory()->create(['name' => 'Marta Ilves', 'bio' => 'Baker and writer.', 'social_links' => ['https://bsky.app/profile/marta.example.org']]);
    $post = Entry::factory()->published()->by($author)->create(['title' => 'Why Our Sourdough Rests Overnight', 'slug' => 'sourdough-rests-overnight']);

    $graph = assertValidGraph(app(SeoBuilder::class)->build($post->load('author'))->jsonLd());
    $posting = graphNode($graph, 'BlogPosting');

    expect($posting['headline'])->toBe('Why Our Sourdough Rests Overnight')
        ->and($posting['author'])->toMatchArray([
            '@type' => 'Person',
            '@id' => $author->authorUrl().'#person',
            'name' => 'Marta Ilves',
            'url' => $author->authorUrl(),
            'description' => 'Baker and writer.',
            'sameAs' => ['https://bsky.app/profile/marta.example.org'],
        ])
        ->and($posting['datePublished'])->not->toBeEmpty()
        ->and(graphNode($graph, 'BreadcrumbList')['itemListElement'])->toHaveCount(3);
});

it('uses an Article for article types without their own schema type', function () {
    config(['verso.seo.schema_types' => []]);
    $post = Entry::factory()->published()->create(['title' => 'Rye Flour, Explained']);

    $graph = assertValidGraph(app(SeoBuilder::class)->build($post)->jsonLd());

    expect(graphNode($graph, 'Article'))->not->toBeNull()
        ->and(graphNode($graph, 'BlogPosting'))->toBeNull();
});

it('describes a page as a WebPage with breadcrumbs through its parents', function () {
    $parent = Entry::factory()->page()->published()->create(['title' => 'Visit us', 'slug' => 'visit']);
    $page = Entry::factory()->page()->published()->create(['title' => 'Opening hours', 'slug' => 'hours', 'parent_id' => $parent->id]);

    $graph = assertValidGraph(app(SeoBuilder::class)->build($page)->jsonLd());
    $crumbs = graphNode($graph, 'BreadcrumbList')['itemListElement'];

    expect(graphNode($graph, 'WebPage')['name'])->toBe('Opening hours | Harbor Lane Bakery')
        ->and(array_column($crumbs, 'name'))->toBe(['Harbor Lane Bakery', 'Visit us', 'Opening hours'])
        ->and(array_column($crumbs, 'position'))->toBe([1, 2, 3]);
});

it('adds a FAQPage from details blocks', function () {
    $page = Entry::factory()->page()->published()->create(['title' => 'Questions', 'blocks' => [
        ['id' => 'b_intro1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Answers to what people ask most.</p>'],
        faqDetails('Do you bake gluten free bread?', 'Yes, every <strong>Friday</strong> morning.'),
        ['id' => 'b_cols01', 'type' => 'columns', 'attrs' => [], 'children' => [
            ['id' => 'b_col001', 'type' => 'column', 'attrs' => [], 'children' => [faqDetails('Can I order a cake?', 'Call us two days before.')]],
        ]],
    ]]);

    $faq = graphNode(assertValidGraph(app(SeoBuilder::class)->build($page)->jsonLd()), 'FAQPage');

    expect($faq['mainEntity'])->toBe([
        ['@type' => 'Question', 'name' => 'Do you bake gluten free bread?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes, every Friday morning.']],
        ['@type' => 'Question', 'name' => 'Can I order a cake?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Call us two days before.']],
    ]);
});

it('reads a faq block and skips empty items', function () {
    $faq = new FaqSchema(10);

    expect($faq->questions([
        ['type' => 'faq', 'attrs' => ['items' => [
            ['question' => '¿Abrís los domingos?', 'answer' => 'Sí, de 8:00 a 14:00.'],
            ['question' => 'Sin respuesta', 'answer' => ''],
            'not an item',
        ]]],
    ]))->toBe([['question' => '¿Abrís los domingos?', 'answer' => 'Sí, de 8:00 a 14:00.']]);
});

it('limits the number of questions and skips details without a summary', function () {
    $blocks = [
        ['type' => 'element', 'attrs' => ['tag' => 'details'], 'children' => [['type' => 'paragraph', 'content' => '<p>No question here.</p>']]],
        faqDetails('How long does the starter keep?', 'About a week in the fridge.'),
        faqDetails('Do you sell flour?', 'Only rye and spelt.'),
    ];

    expect((new FaqSchema(1))->questions($blocks))->toHaveCount(1)
        ->and((new FaqSchema(1))->node([], 'https://harborlane.example.org/faq'))->toBeNull();
});

it('prints nofollow and the share overrides', function () {
    $post = Entry::factory()->published()->create(['title' => 'Croissant Day', 'meta' => ['seo' => [
        'nofollow' => true,
        'og_title' => 'Free croissants on Saturday',
        'og_description' => 'Come early, we bake 300.',
    ]]]);

    $seo = app(SeoBuilder::class)->build($post);

    expect($seo->robots)->toBe('index, nofollow')
        ->and($seo->title)->toBe('Croissant Day | Harbor Lane Bakery')
        ->and($seo->openGraph['og:title'])->toBe('Free croissants on Saturday')
        ->and($seo->openGraph['og:description'])->toBe('Come early, we bake 300.')
        ->and($seo->twitter['twitter:title'])->toBe('Free croissants on Saturday');
});

it('combines noindex and nofollow, and the discourage switch wins', function () {
    $post = Entry::factory()->published()->create(['meta' => ['seo' => ['noindex' => true, 'nofollow' => true]]]);

    expect(app(SeoBuilder::class)->build($post)->robots)->toBe('noindex, nofollow');

    app(Settings::class)->set('reading.discourage_search', true);
    $open = Entry::factory()->published()->create();

    expect(app(SeoBuilder::class)->build($open)->robots)->toBe('noindex, nofollow');
});

it('prints a ProfilePage about the person on the author page', function () {
    $author = User::factory()->create(['name' => 'Tomás Rey', 'bio' => 'Pastry chef since 2009.']);
    Entry::factory()->published()->by($author)->create(['title' => 'Laminating Dough by Hand']);

    $html = $this->get($author->authorUrl())->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $html, $match);
    $graph = assertValidGraph($match[1]);
    $profile = graphNode($graph, 'ProfilePage');

    expect($profile['mainEntity'])->toMatchArray(['@type' => 'Person', 'name' => 'Tomás Rey', 'url' => $author->authorUrl(), 'description' => 'Pastry chef since 2009.'])
        ->and($html)->toContain('<meta property="og:type" content="profile">');
});

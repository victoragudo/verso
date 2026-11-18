<?php

use App\Verso\Importer\Content\ContentConverter;
use App\Verso\Importer\Content\ImportedMedia;
use App\Verso\Importer\Content\MediaMap;

beforeEach(function () {
    $this->media = new MediaMap;
    $this->media->add(101, 'https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg', new ImportedMedia(7, '/storage/uploads/2024/03/tram-28-alfama.jpg', 'uploads/2024/03/tram-28-alfama.jpg', 'Yellow tram in Alfama'));
    $this->media->add(102, 'https://harbourlight.example/wp-content/uploads/2024/03/pasteis-de-nata.png', new ImportedMedia(null, '/storage/uploads/2024/03/pasteis-de-nata.png'));
});

function convertContent(string $content, ?MediaMap $media = null): array
{
    return app(ContentConverter::class)->convert($content, 'wordpress:10', $media ?? test()->media);
}

function blockTypes(array $blocks): array
{
    return array_column($blocks, 'type');
}

describe('gutenberg content', function () {
    it('converts paragraphs and keeps inline marks', function () {
        $blocks = convertContent('<!-- wp:paragraph --><p class="has-large-font-size">We took the <strong>night train</strong> to <a href="https://cp.pt">Porto</a>.</p><!-- /wp:paragraph -->');

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('paragraph')
            ->and($blocks[0]['content'])->toBe('We took the <strong>night train</strong> to <a href="https://cp.pt">Porto</a>.')
            ->and($blocks[0]['id'])->toMatch('/^b_[a-f0-9]{6}$/')
            ->and($blocks[0]['children'])->toBe([]);
    });

    it('drops empty paragraphs', function () {
        expect(convertContent('<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->'))->toBe([]);
    });

    it('converts headings with their level', function () {
        $blocks = convertContent('<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Where we stayed</h3><!-- /wp:heading --><!-- wp:heading --><h2>Getting there</h2><!-- /wp:heading -->');

        expect($blocks[0]['attrs'])->toBe(['level' => 3])
            ->and($blocks[0]['content'])->toBe('Where we stayed')
            ->and($blocks[1]['attrs'])->toBe(['level' => 2]);
    });

    it('converts lists with list item blocks', function () {
        $blocks = convertContent("<!-- wp:list {\"ordered\":true} -->\n<ol><!-- wp:list-item -->\n<li>Buy a Viva Viagem card</li>\n<!-- /wp:list-item --><!-- wp:list-item --><li>Take tram 28 early</li><!-- /wp:list-item --></ol>\n<!-- /wp:list -->");

        expect($blocks[0]['type'])->toBe('list')
            ->and($blocks[0]['attrs'])->toBe(['ordered' => true])
            ->and($blocks[0]['content'])->toBe('<li>Buy a Viva Viagem card</li><li>Take tram 28 early</li>');
    });

    it('reads the list type from the tag', function () {
        $blocks = convertContent('<!-- wp:list --><ol><li>First</li></ol><!-- /wp:list -->');

        expect($blocks[0]['attrs']['ordered'])->toBeTrue();
    });

    it('converts quotes with a citation', function () {
        $blocks = convertContent('<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Travel is the only thing you buy that makes you richer.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Or so they say.</p><!-- /wp:paragraph --><cite>Anonymous</cite></blockquote><!-- /wp:quote -->');

        expect($blocks[0]['type'])->toBe('quote')
            ->and($blocks[0]['content'])->toBe('Travel is the only thing you buy that makes you richer.<br>Or so they say.')
            ->and($blocks[0]['attrs'])->toBe(['citation' => 'Anonymous']);
    });

    it('converts images and swaps in the imported file', function () {
        $blocks = convertContent('<!-- wp:image {"id":101,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama-1024x683.jpg" alt="Tram 28" width="1024" height="683"/><figcaption>At dawn</figcaption></figure><!-- /wp:image -->');

        expect($blocks[0]['type'])->toBe('image')
            ->and($blocks[0]['attrs'])->toBe([
                'mediaId' => 7,
                'src' => '/storage/uploads/2024/03/tram-28-alfama.jpg',
                'alt' => 'Tram 28',
                'caption' => 'At dawn',
                'width' => 1024,
                'height' => 683,
            ]);
    });

    it('keeps the old URL and uses the media alt text when needed', function () {
        $blocks = convertContent('<!-- wp:image --><figure><img src="https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg" alt=""/></figure><!-- /wp:image --><!-- wp:image --><figure><img src="https://cdn.example/sunset.jpg" alt="Sunset"/></figure><!-- /wp:image -->');

        expect($blocks[0]['attrs']['alt'])->toBe('Yellow tram in Alfama')
            ->and($blocks[1]['attrs']['src'])->toBe('https://cdn.example/sunset.jpg')
            ->and($blocks[1]['attrs']['mediaId'])->toBeNull();
    });

    it('converts galleries', function () {
        $blocks = convertContent('<!-- wp:gallery {"ids":[101,102]} --><figure class="wp-block-gallery"><!-- wp:image {"id":101} --><figure><img src="https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg" class="wp-image-101" alt=""/></figure><!-- /wp:image --><!-- wp:image {"id":102} --><figure><img src="https://harbourlight.example/wp-content/uploads/2024/03/pasteis-de-nata.png" class="wp-image-102" alt="Tarts"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->');

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('gallery')
            ->and($blocks[0]['attrs']['mediaIds'])->toBe([7])
            ->and($blocks[0]['attrs']['images'][1])->toBe(['mediaId' => null, 'src' => '/storage/uploads/2024/03/pasteis-de-nata.png', 'alt' => 'Tarts']);
    });

    it('converts embeds, old embed blocks too', function () {
        $blocks = convertContent('<!-- wp:embed {"url":"https://vimeo.com/76979871","providerNameSlug":"vimeo"} --><figure><div>https://vimeo.com/76979871</div><figcaption>The Douro valley</figcaption></figure><!-- /wp:embed --><!-- wp:core-embed/youtube --><figure><div>https://www.youtube.com/watch?v=Kx3pQ8xL2aE</div></figure><!-- /wp:core-embed/youtube -->');

        expect($blocks[0]['attrs'])->toBe(['url' => 'https://vimeo.com/76979871', 'provider' => 'vimeo', 'caption' => 'The Douro valley'])
            ->and($blocks[1]['attrs'])->toBe(['url' => 'https://www.youtube.com/watch?v=Kx3pQ8xL2aE']);
    });

    it('converts code and preformatted blocks', function () {
        $blocks = convertContent('<!-- wp:code --><pre class="wp-block-code"><code class="language-php">echo "Olá" &amp;&amp; exit;</code></pre><!-- /wp:code --><!-- wp:preformatted --><pre>Lisbon  09:10\nPorto   12:05</pre><!-- /wp:preformatted -->');

        expect($blocks[0])->toMatchArray(['type' => 'code', 'attrs' => ['language' => 'php'], 'content' => 'echo "Olá" && exit;'])
            ->and($blocks[1]['type'])->toBe('code')
            ->and($blocks[1]['attrs'])->toBe(['language' => null])
            ->and($blocks[1]['content'])->toContain('Porto   12:05');
    });

    it('converts separators', function () {
        expect(blockTypes(convertContent('<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->')))->toBe(['separator']);
    });

    it('converts columns into column children', function () {
        $blocks = convertContent('<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} --><div class="wp-block-column"><!-- wp:heading --><h2>Morning</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Castle walls.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Sardines.</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->');

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]['type'])->toBe('columns')
            ->and($blocks[0]['children'])->toHaveCount(2)
            ->and($blocks[0]['children'][0]['type'])->toBe('column')
            ->and($blocks[0]['children'][0]['attrs'])->toBe(['width' => '66.66%'])
            ->and(blockTypes($blocks[0]['children'][0]['children']))->toBe(['heading', 'paragraph'])
            ->and($blocks[0]['children'][1]['children'][0]['content'])->toBe('Sardines.');
    });

    it('turns a buttons group into button blocks', function () {
        $blocks = convertContent('<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="https://harbourlight.example/newsletter/">Join the newsletter</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a href="/shop/">Buy the guide</a></div><!-- /wp:button --></div><!-- /wp:buttons -->');

        expect(blockTypes($blocks))->toBe(['button', 'button'])
            ->and($blocks[0]['attrs'])->toBe(['url' => 'https://harbourlight.example/newsletter/', 'label' => 'Join the newsletter', 'style' => 'fill'])
            ->and($blocks[1]['attrs']['style'])->toBe('outline');
    });

    it('keeps custom HTML blocks', function () {
        $blocks = convertContent('<!-- wp:html --><iframe src="https://www.google.com/maps/embed?pb=lisbon"></iframe><!-- /wp:html -->');

        expect($blocks[0])->toMatchArray(['type' => 'html', 'content' => '<iframe src="https://www.google.com/maps/embed?pb=lisbon"></iframe>']);
    });

    it('falls back to HTML for unknown blocks', function () {
        $blocks = convertContent('<!-- wp:table --><figure class="wp-block-table"><table><tr><td>Tram 28</td></tr></table></figure><!-- /wp:table --><!-- wp:jetpack/rating-star {"rating":4} --><div class="rating">★★★★</div><!-- /wp:jetpack/rating-star -->');

        expect(blockTypes($blocks))->toBe(['html', 'html'])
            ->and($blocks[0]['content'])->toContain('<td>Tram 28</td>');
    });

    it('drops empty unknown blocks and spacers', function () {
        expect(convertContent('<!-- wp:latest-posts /--><!-- wp:spacer {"height":"40px"} --><div style="height:40px"></div><!-- /wp:spacer --><!-- wp:more --><!--more--><!-- /wp:more -->'))->toBe([]);
    });

    it('unwraps groups', function () {
        $blocks = convertContent('<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Thanks for reading.</p><!-- /wp:paragraph --><!-- wp:separator --><hr/><!-- /wp:separator --></div><!-- /wp:group -->');

        expect(blockTypes($blocks))->toBe(['paragraph', 'separator']);
    });

    it('converts loose HTML between blocks as classic content', function () {
        $blocks = convertContent("A line written before the block editor.\n\n<!-- wp:paragraph --><p>A block paragraph.</p><!-- /wp:paragraph -->");

        expect(blockTypes($blocks))->toBe(['paragraph', 'paragraph'])
            ->and($blocks[0]['content'])->toBe('A line written before the block editor.');
    });

    it('converts the classic block', function () {
        $blocks = convertContent("<!-- wp:freeform --><h3>Old heading</h3>\n\nOld text.<!-- /wp:freeform -->");

        expect(blockTypes($blocks))->toBe(['heading', 'paragraph']);
    });

    it('gives the same ids for the same content', function () {
        $content = '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->';

        $first = convertContent($content);
        $second = convertContent($content);

        expect(array_column($first, 'id'))->toBe(array_column($second, 'id'))
            ->and($first[0]['id'])->not->toBe($first[1]['id']);
    });
});

describe('classic content', function () {
    it('splits paragraphs on blank lines and keeps single line breaks', function () {
        $blocks = convertContent("Porto wakes up late.\n\nWe started near São Bento.\nThe owner has run it since 1987.\r\n\r\nLast line.");

        expect(blockTypes($blocks))->toBe(['paragraph', 'paragraph', 'paragraph'])
            ->and($blocks[1]['content'])->toBe('We started near São Bento.<br>The owner has run it since 1987.');
    });

    it('unwraps paragraphs that already have p tags', function () {
        $blocks = convertContent('<p>Wrapped text.</p>');

        expect($blocks[0])->toMatchArray(['type' => 'paragraph', 'content' => 'Wrapped text.']);
    });

    it('finds headings, lists, quotes, code and separators', function () {
        $blocks = convertContent("<h2>Our three favourites</h2>\n\n<ol>\n<li>Majestic</li>\n<li>Combi</li>\n</ol>\n\n<blockquote><p>Bica is not espresso.</p><cite>A barista</cite></blockquote>\n\n<pre><code>sudo apt install espresso</code></pre>\n\n<hr />");

        expect(blockTypes($blocks))->toBe(['heading', 'list', 'quote', 'code', 'separator'])
            ->and($blocks[0]['attrs'])->toBe(['level' => 2])
            ->and($blocks[1]['attrs'])->toBe(['ordered' => true])
            ->and($blocks[1]['content'])->toBe('<li>Majestic</li><li>Combi</li>')
            ->and($blocks[2]['attrs'])->toBe(['citation' => 'A barista'])
            ->and($blocks[2]['content'])->toBe('Bica is not espresso.')
            ->and($blocks[3]['content'])->toBe('sudo apt install espresso');
    });

    it('turns lone images into image blocks', function () {
        $blocks = convertContent('<a href="https://harbourlight.example/tram/"><img class="alignnone size-medium wp-image-101" src="https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama-300x200.jpg" alt="Tram" width="300" height="200" /></a>');

        expect($blocks[0]['type'])->toBe('image')
            ->and($blocks[0]['attrs']['mediaId'])->toBe(7)
            ->and($blocks[0]['attrs']['width'])->toBe(300);
    });

    it('reads the caption shortcode', function () {
        $blocks = convertContent('[caption id="attachment_101" align="aligncenter" width="640"]<img src="https://harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg" alt="Tram" /> The famous tram 28[/caption]');

        expect($blocks[0]['type'])->toBe('image')
            ->and($blocks[0]['attrs']['caption'])->toBe('The famous tram 28')
            ->and($blocks[0]['attrs']['width'])->toBe(640)
            ->and($blocks[0]['attrs']['src'])->toBe('/storage/uploads/2024/03/tram-28-alfama.jpg');
    });

    it('reads the gallery shortcode', function () {
        $blocks = convertContent('[gallery columns="2" ids="101,102,404"]');

        expect($blocks[0]['type'])->toBe('gallery')
            ->and($blocks[0]['attrs']['mediaIds'])->toBe([7])
            ->and($blocks[0]['attrs']['images'])->toHaveCount(2);
    });

    it('turns embed shortcodes and lone URLs into embeds', function () {
        $blocks = convertContent("[embed]https://www.youtube.com/watch?v=Kx3pQ8xL2aE[/embed]\n\nhttps://vimeo.com/76979871");

        expect(blockTypes($blocks))->toBe(['embed', 'embed'])
            ->and($blocks[1]['attrs']['url'])->toBe('https://vimeo.com/76979871');
    });

    it('keeps complex HTML as an html block', function () {
        $blocks = convertContent('<table><tr><td>Tram 28</td><td>€3.10</td></tr></table>');

        expect($blocks[0]['type'])->toBe('html');
    });

    it('removes the more tag', function () {
        $blocks = convertContent("Intro.\n\n<!--more-->\n\nRest of the story.");

        expect(array_column($blocks, 'content'))->toBe(['Intro.', 'Rest of the story.']);
    });

    it('returns no blocks for empty content', function () {
        expect(convertContent("  \n\n "))->toBe([]);
    });
});

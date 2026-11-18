<?php

use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\BaseBlock;
use App\Verso\Editor\Support\BlockData;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is a shared instance', function () {
    expect(app(BlockRenderer::class))->toBe($this->renderer);
});

it('renders paragraphs and headings', function () {
    $html = $this->renderer->render([
        ['id' => 'b_a1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => '<h2>Day one in Porto</h2>'],
        ['id' => 'b_a2', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>We walked along the <strong>Douro</strong> river.</p>'],
    ]);

    expect($html)->toBe('<h2>Day one in Porto</h2><p>We walked along the <strong>Douro</strong> river.</p>');
});

it('renders a full article with every core block', function () {
    $html = $this->renderer->render([
        ['id' => 'b_1', 'type' => 'heading', 'attrs' => ['level' => 1], 'content' => 'Three days in Porto'],
        ['id' => 'b_2', 'type' => 'paragraph', 'content' => '<p>Porto is <em>steep</em>.</p>'],
        ['id' => 'b_3', 'type' => 'list', 'attrs' => ['ordered' => true], 'content' => '<ol><li>Ribeira</li><li>Livraria Lello</li></ol>'],
        ['id' => 'b_4', 'type' => 'quote', 'attrs' => ['citation' => 'A local baker'], 'content' => '<p>Eat the nata warm.</p>'],
        ['id' => 'b_5', 'type' => 'code', 'attrs' => ['language' => 'bash'], 'content' => 'curl https://example.org'],
        ['id' => 'b_6', 'type' => 'image', 'attrs' => ['src' => '/storage/ribeira.jpg', 'alt' => 'Ribeira at dusk']],
        ['id' => 'b_7', 'type' => 'gallery', 'attrs' => ['mediaIds' => [4], 'images' => [['mediaId' => 4, 'src' => '/storage/tram.jpg', 'alt' => 'Tram 1']]]],
        ['id' => 'b_8', 'type' => 'embed', 'attrs' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
        ['id' => 'b_9', 'type' => 'columns', 'children' => [
            ['id' => 'b_9a', 'type' => 'column', 'children' => [['id' => 'b_9a1', 'type' => 'paragraph', 'content' => 'Left side']]],
            ['id' => 'b_9b', 'type' => 'column', 'children' => [['id' => 'b_9b1', 'type' => 'paragraph', 'content' => 'Right side']]],
        ]],
        ['id' => 'b_10', 'type' => 'separator'],
        ['id' => 'b_11', 'type' => 'button', 'attrs' => ['url' => '/contact', 'label' => 'Book a tour']],
        ['id' => 'b_12', 'type' => 'html', 'content' => '<div class="map">Map</div>'],
        ['id' => 'b_13', 'type' => 'table', 'attrs' => ['rows' => [['Day', 'Plan'], ['1', 'Ribeira']], 'header' => true]],
        ['id' => 'b_14', 'type' => 'video', 'attrs' => ['src' => '/storage/tram.mp4']],
    ]);

    expect($html)
        ->toContain('<h1>Three days in Porto</h1>')
        ->toContain('<p>Porto is <em>steep</em>.</p>')
        ->toContain('<ol><li>Ribeira</li><li>Livraria Lello</li></ol>')
        ->toContain('<blockquote><p>Eat the nata warm.</p><cite>A local baker</cite></blockquote>')
        ->toContain('<pre><code class="language-bash">curl https://example.org</code></pre>')
        ->toContain('alt="Ribeira at dusk"')
        ->toContain('alt="Tram 1"')
        ->toContain('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('<div class="verso-columns"><div class="verso-column"><p>Left side</p></div><div class="verso-column"><p>Right side</p></div></div>')
        ->toContain('<hr>')
        ->toContain('Book a tour</a>')
        ->toContain('<div class="map">Map</div>')
        ->toContain('<th>Day</th>')
        ->toContain('<video src="/storage/tram.mp4"');
});

it('renders nothing for unknown blocks', function () {
    expect($this->renderer->render([
        ['type' => 'carousel', 'content' => '<div>Slides</div>'],
        'not a block',
        ['content' => 'No type'],
        ['type' => ['paragraph'], 'content' => 'Odd type'],
    ]))->toBe('');
});

it('renders an empty list as an empty string', function () {
    expect($this->renderer->render([]))->toBe('');
});

it('removes scripts and unsafe attributes', function () {
    $html = $this->renderer->renderBlock([
        'type' => 'paragraph',
        'content' => '<p onclick="steal()">Hi <script>alert(1)</script><em class="x" style="color:red">there</em> <img src=x onerror=alert(1)></p>',
    ]);

    expect($html)->toBe('<p>Hi <em>there</em> </p>');
});

it('keeps safe links and drops javascript links', function () {
    $safe = $this->renderer->sanitizeInline('<a href="https://example.org/porto" target="_blank">guide</a>');
    $local = $this->renderer->sanitizeInline("<a href='/blog/porto'>post</a>");
    $evil = $this->renderer->sanitizeInline('<a href="javascript:alert(1)">click</a>');

    expect($safe)->toBe('<a href="https://example.org/porto">guide</a>')
        ->and($local)->toBe('<a href="/blog/porto">post</a>')
        ->and($evil)->toBe('<a>click</a>');
});

it('keeps line breaks', function () {
    expect($this->renderer->sanitizeInline('Rua das Flores<br/>Porto'))->toBe('Rua das Flores<br>Porto');
});

it('skips children a block does not accept', function () {
    $html = $this->renderer->renderBlock(['type' => 'columns', 'children' => [
        ['type' => 'paragraph', 'content' => 'Loose text'],
        ['type' => 'column', 'children' => [['type' => 'paragraph', 'content' => 'Inside']]],
    ]]);

    expect($html)->toBe('<div class="verso-columns"><div class="verso-column"><p>Inside</p></div></div>');
});

it('ignores children of blocks without children', function () {
    expect($this->renderer->renderBlock(['type' => 'paragraph', 'content' => 'Main', 'children' => [
        ['type' => 'html', 'content' => '<script>alert(1)</script>'],
    ]]))->toBe('<p>Main</p>');
});

it('stops rendering at the maximum depth', function () {
    config(['verso.editor.max_depth' => 2]);
    app()->forgetInstance(BlockRenderer::class);

    $html = app(BlockRenderer::class)->render([
        ['type' => 'columns', 'children' => [
            ['type' => 'column', 'children' => [['type' => 'paragraph', 'content' => 'Too deep']]],
        ]],
    ]);

    expect($html)->toBe('<div class="verso-columns"><div class="verso-column"></div></div>');
});

it('uses blocks registered by plugins', function () {
    app(BlockRegistry::class)->register(new class extends BaseBlock
    {
        public function name(): string
        {
            return 'recipe-card';
        }

        public function render(BlockData $block, BlockRenderer $renderer): string
        {
            return '<aside class="recipe">'.e($block->string('dish')).'</aside>';
        }
    });

    expect($this->renderer->renderBlock(['type' => 'recipe-card', 'attrs' => ['dish' => 'Caldo verde & broa']]))
        ->toBe('<aside class="recipe">Caldo verde &amp; broa</aside>');
});

it('gives access to its sanitizers', function () {
    expect($this->renderer->sanitizer()->inline('<b>Hi</b>'))->toBe('<b>Hi</b>')
        ->and($this->renderer->urls()->clean('javascript:alert(1)'))->toBeNull();
});

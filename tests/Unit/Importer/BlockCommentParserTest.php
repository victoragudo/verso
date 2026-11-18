<?php

use App\Verso\Importer\Content\BlockCommentParser;

beforeEach(function () {
    $this->parser = new BlockCommentParser;
});

it('detects block comments', function () {
    expect($this->parser->hasBlocks("<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->"))->toBeTrue()
        ->and($this->parser->hasBlocks('<p>Plain classic text</p>'))->toBeFalse();
});

it('parses blocks with names and attributes', function () {
    $blocks = $this->parser->parse('<!-- wp:heading {"level":4} --><h4>Ferry times</h4><!-- /wp:heading -->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/heading')
        ->and($blocks[0]->attr('level'))->toBe(4)
        ->and($blocks[0]->html())->toBe('<h4>Ferry times</h4>');
});

it('keeps plugin namespaces', function () {
    $blocks = $this->parser->parse('<!-- wp:jetpack/map {"zoom":12} --><div>Map</div><!-- /wp:jetpack/map -->');

    expect($blocks[0]->name)->toBe('jetpack/map')
        ->and($blocks[0]->attr('zoom'))->toBe(12);
});

it('parses void blocks', function () {
    $blocks = $this->parser->parse('<!-- wp:latest-posts {"postsToShow":3} /-->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/latest-posts')
        ->and($blocks[0]->innerBlocks)->toBe([])
        ->and($blocks[0]->html())->toBe('');
});

it('nests inner blocks and rebuilds their HTML', function () {
    $content = '<!-- wp:group --><div class="group"><!-- wp:paragraph --><p>Inside</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

    $group = $this->parser->parse($content)[0];

    expect($group->innerBlocks)->toHaveCount(1)
        ->and($group->innerBlocks[0]->name)->toBe('core/paragraph')
        ->and($group->html())->toBe('<div class="group"><p>Inside</p></div>')
        ->and($group->ownHtml())->toBe('<div class="group"></div>');
});

it('keeps HTML between blocks as freeform pieces', function () {
    $blocks = $this->parser->parse("Old intro text\n<!-- wp:separator --><hr><!-- /wp:separator -->\nOutro");

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]->name)->toBeNull()
        ->and($blocks[0]->html())->toBe("Old intro text\n")
        ->and($blocks[2]->html())->toBe("\nOutro");
});

it('closes blocks that were never closed', function () {
    $blocks = $this->parser->parse('<!-- wp:quote --><blockquote><!-- wp:paragraph --><p>Unfinished</p>');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/quote')
        ->and($blocks[0]->innerBlocks[0]->name)->toBe('core/paragraph');
});

it('ignores closers without an opener', function () {
    $blocks = $this->parser->parse('<!-- /wp:paragraph --><!-- wp:separator /-->');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->name)->toBe('core/separator');
});

it('treats broken JSON attributes as empty', function () {
    $blocks = $this->parser->parse('<!-- wp:image {"id":12,} --><figure></figure><!-- /wp:image -->');

    expect($blocks[0]->attrs)->toBe([]);
});

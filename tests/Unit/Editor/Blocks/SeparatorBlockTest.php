<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\SeparatorBlock;

it('is named separator', function () {
    expect((new SeparatorBlock)->name())->toBe('separator');
});

it('renders a horizontal rule and ignores content', function () {
    expect(app(BlockRenderer::class)->renderBlock(['type' => 'separator', 'content' => '<script>alert(1)</script>']))->toBe('<hr>');
});

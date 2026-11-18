<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\QuoteBlock;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named quote', function () {
    expect((new QuoteBlock)->name())->toBe('quote')
        ->and((new QuoteBlock)->rules())->toHaveKey('citation');
});

it('renders paragraphs and a citation', function () {
    expect($this->renderer->renderBlock(['type' => 'quote', 'attrs' => ['citation' => 'José Saramago'], 'content' => '<p>The journey is never over.</p><p>Only travellers end.</p>']))
        ->toBe('<blockquote><p>The journey is never over.</p><p>Only travellers end.</p><cite>José Saramago</cite></blockquote>');
});

it('wraps plain text in a paragraph', function () {
    expect($this->renderer->renderBlock(['type' => 'quote', 'content' => 'Eat the nata warm.']))
        ->toBe('<blockquote><p>Eat the nata warm.</p></blockquote>');
});

it('renders nothing when empty', function () {
    expect($this->renderer->renderBlock(['type' => 'quote', 'content' => '<p></p>', 'attrs' => ['citation' => 'Nobody']]))->toBe('');
});

it('sanitizes the text and the citation', function () {
    expect($this->renderer->renderBlock(['type' => 'quote', 'attrs' => ['citation' => '<img src=x onerror=alert(1)>Ana'], 'content' => '<p onclick="x()">Hi<script>alert(1)</script></p>']))
        ->toBe('<blockquote><p>Hi</p><cite>Ana</cite></blockquote>');
});

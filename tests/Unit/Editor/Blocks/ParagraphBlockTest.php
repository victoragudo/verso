<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\ParagraphBlock;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named paragraph and has no special rules', function () {
    $block = new ParagraphBlock;

    expect($block->name())->toBe('paragraph')
        ->and($block->rules())->toBe([])
        ->and($block->capability())->toBeNull()
        ->and($block->acceptsChild('paragraph'))->toBeFalse();
});

it('wraps inline content in a paragraph', function () {
    expect($this->renderer->renderBlock(['type' => 'paragraph', 'content' => 'Trams run until <strong>midnight</strong>.']))
        ->toBe('<p>Trams run until <strong>midnight</strong>.</p>');
});

it('removes the stored paragraph wrapper', function () {
    expect($this->renderer->renderBlock(['type' => 'paragraph', 'content' => '<p>Bring a <a href="https://www.windy.com">rain jacket</a>.</p>']))
        ->toBe('<p>Bring a <a href="https://www.windy.com">rain jacket</a>.</p>');
});

it('renders nothing when empty', function (string $content) {
    expect($this->renderer->renderBlock(['type' => 'paragraph', 'content' => $content]))->toBe('');
})->with(['', '<p></p>', '<p> </p>', '<script>alert(1)</script>']);

it('sanitizes xss attempts', function () {
    expect($this->renderer->renderBlock(['type' => 'paragraph', 'content' => '<p>Menu <a href="javascript:alert(1)" onclick="x()">here</a><iframe src="//evil.example"></iframe></p>']))
        ->toBe('<p>Menu <a>here</a></p>');
});

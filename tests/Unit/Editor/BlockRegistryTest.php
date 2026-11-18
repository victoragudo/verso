<?php

use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Blocks\ParagraphBlock;
use App\Verso\Editor\Blocks\SeparatorBlock;
use App\Verso\Editor\EditorServiceProvider;

it('registers every core block', function () {
    expect(array_slice(app(BlockRegistry::class)->names(), 0, count(EditorServiceProvider::CORE_BLOCKS)))->toBe([
        'paragraph', 'heading', 'list', 'quote', 'code', 'image', 'gallery', 'embed',
        'columns', 'column', 'separator', 'button', 'html', 'table', 'video', 'section', 'element',
    ])->and(count(EditorServiceProvider::CORE_BLOCKS))->toBe(17);
});

it('is a singleton', function () {
    expect(app(BlockRegistry::class))->toBe(app(BlockRegistry::class));
});

it('registers, finds and lists blocks', function () {
    $registry = new BlockRegistry;
    $paragraph = new ParagraphBlock;
    $registry->register($paragraph);
    $registry->register(new SeparatorBlock);

    expect($registry->has('paragraph'))->toBeTrue()
        ->and($registry->has('carousel'))->toBeFalse()
        ->and($registry->get('paragraph'))->toBe($paragraph)
        ->and($registry->find('carousel'))->toBeNull()
        ->and(array_keys($registry->all()))->toBe(['paragraph', 'separator'])
        ->and($registry->names())->toBe(['paragraph', 'separator']);
});

it('replaces a block with the same name', function () {
    $registry = new BlockRegistry;
    $registry->register(new ParagraphBlock);
    $replacement = new ParagraphBlock;
    $registry->register($replacement);

    expect($registry->get('paragraph'))->toBe($replacement)
        ->and($registry->names())->toHaveCount(1);
});

it('throws for unknown block types', function () {
    (new BlockRegistry)->get('carousel');
})->throws(InvalidArgumentException::class, 'Unknown block type [carousel].');

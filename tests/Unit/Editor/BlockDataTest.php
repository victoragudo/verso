<?php

use App\Verso\Editor\Support\BlockData;

it('reads a stored block', function () {
    $block = BlockData::from([
        'id' => 'b_k2j3h4',
        'type' => 'heading',
        'attrs' => ['level' => 3],
        'content' => 'Where to eat',
        'children' => [['type' => 'paragraph']],
    ], 2);

    expect($block->id)->toBe('b_k2j3h4')
        ->and($block->type)->toBe('heading')
        ->and($block->attrs)->toBe(['level' => 3])
        ->and($block->content)->toBe('Where to eat')
        ->and($block->children)->toBe([['type' => 'paragraph']])
        ->and($block->depth)->toBe(2);
});

it('uses safe defaults for broken data', function () {
    $block = BlockData::from(['id' => ['x'], 'type' => 7, 'attrs' => 'level=2', 'content' => ['<p>'], 'children' => 'none']);

    expect($block->id)->toBe('')
        ->and($block->type)->toBe('')
        ->and($block->attrs)->toBe([])
        ->and($block->content)->toBe('')
        ->and($block->children)->toBe([])
        ->and($block->depth)->toBe(0);
});

it('drops numeric attr keys and children that are not arrays', function () {
    $block = BlockData::from(['attrs' => ['alt' => 'Tram', 0 => 'loose'], 'children' => ['text', ['type' => 'column']]]);

    expect($block->attrs)->toBe(['alt' => 'Tram'])
        ->and($block->children)->toBe([['type' => 'column']]);
});

it('reads typed attrs', function () {
    $block = new BlockData('b_1', 'video', [
        'src' => '/storage/tram.mp4',
        'width' => '640',
        'ratio' => 1.5,
        'loop' => 'true',
        'muted' => false,
        'images' => ['a' => 1, 'b' => 2],
        'odd' => ['nested'],
    ]);

    expect($block->attr('src'))->toBe('/storage/tram.mp4')
        ->and($block->attr('missing', 'none'))->toBe('none')
        ->and($block->string('src'))->toBe('/storage/tram.mp4')
        ->and($block->string('width'))->toBe('640')
        ->and($block->string('ratio'))->toBe('1.5')
        ->and($block->string('odd', 'fallback'))->toBe('fallback')
        ->and($block->int('width'))->toBe(640)
        ->and($block->int('src'))->toBeNull()
        ->and($block->int('missing', 3))->toBe(3)
        ->and($block->bool('loop'))->toBeTrue()
        ->and($block->bool('muted', true))->toBeFalse()
        ->and($block->bool('src', true))->toBeTrue()
        ->and($block->bool('missing'))->toBeFalse()
        ->and($block->bool('missing', true))->toBeTrue()
        ->and($block->list('images'))->toBe([1, 2])
        ->and($block->list('src'))->toBe([]);
});

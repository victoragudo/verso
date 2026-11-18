<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\ColumnBlock;
use App\Verso\Editor\Blocks\ColumnsBlock;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('accepts only column children', function () {
    $columns = new ColumnsBlock;

    expect($columns->name())->toBe('columns')
        ->and($columns->acceptsChild('column'))->toBeTrue()
        ->and($columns->acceptsChild('paragraph'))->toBeFalse();
});

it('lets a column hold any block except columns', function () {
    $column = new ColumnBlock;

    expect($column->name())->toBe('column')
        ->and($column->acceptsChild('paragraph'))->toBeTrue()
        ->and($column->acceptsChild('image'))->toBeTrue()
        ->and($column->acceptsChild('columns'))->toBeFalse()
        ->and($column->acceptsChild('column'))->toBeFalse();
});

it('renders columns with widths', function () {
    expect($this->renderer->renderBlock(['type' => 'columns', 'children' => [
        ['type' => 'column', 'attrs' => ['width' => 66], 'children' => [
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => 'Getting there'],
            ['type' => 'paragraph', 'content' => 'Take the metro line E.'],
        ]],
        ['type' => 'column', 'attrs' => ['width' => 500], 'children' => [['type' => 'paragraph', 'content' => 'Tickets cost 2 euros.']]],
    ]]))->toBe('<div class="verso-columns">'
        .'<div class="verso-column" style="flex-basis: 66%"><h3>Getting there</h3><p>Take the metro line E.</p></div>'
        .'<div class="verso-column"><p>Tickets cost 2 euros.</p></div></div>');
});

it('does not render nested columns', function () {
    expect($this->renderer->renderBlock(['type' => 'columns', 'children' => [
        ['type' => 'column', 'children' => [['type' => 'columns', 'children' => []], ['type' => 'paragraph', 'content' => 'Ok']]],
    ]]))->toBe('<div class="verso-columns"><div class="verso-column"><p>Ok</p></div></div>');
});

it('renders nothing without columns', function () {
    expect($this->renderer->renderBlock(['type' => 'columns', 'children' => []]))->toBe('');
});

it('sanitizes content inside columns', function () {
    expect($this->renderer->renderBlock(['type' => 'columns', 'children' => [
        ['type' => 'column', 'children' => [['type' => 'paragraph', 'content' => '<img src=x onerror=alert(1)>Safe']]],
    ]]))->toBe('<div class="verso-columns"><div class="verso-column"><p>Safe</p></div></div>');
});

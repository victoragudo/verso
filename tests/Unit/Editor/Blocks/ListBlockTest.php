<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\ListBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named list', function () {
    expect((new ListBlock)->name())->toBe('list');
});

it('renders a bulleted list', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'content' => '<ul><li><p>Sardines</p></li><li><p>Port <strong>wine</strong></p></li></ul>']))
        ->toBe('<ul><li>Sardines</li><li>Port <strong>wine</strong></li></ul>');
});

it('renders a numbered list with a start number', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'attrs' => ['ordered' => true, 'start' => 3], 'content' => '<li>Book</li><li>Pack</li>']))
        ->toBe('<ol start="3"><li>Book</li><li>Pack</li></ol>')
        ->and($this->renderer->renderBlock(['type' => 'list', 'attrs' => ['ordered' => true, 'start' => 1], 'content' => '<li>Book</li>']))
        ->toBe('<ol><li>Book</li></ol>');
});

it('uses the ordered attr instead of the stored list tag', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'attrs' => ['ordered' => false], 'content' => '<ol><li>Ribeira</li></ol>']))
        ->toBe('<ul><li>Ribeira</li></ul>');
});

it('keeps nested lists', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'content' => '<ul><li>Porto<ul><li>Foz</li></ul></li></ul>']))
        ->toBe('<ul><li>Porto<ul><li>Foz</li></ul></li></ul>');
});

it('wraps plain text in one item', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'content' => 'Only one stop']))
        ->toBe('<ul><li>Only one stop</li></ul>');
});

it('renders nothing when empty', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'content' => '<ul></ul>']))->toBe('');
});

it('removes unsafe markup from items', function () {
    expect($this->renderer->renderBlock(['type' => 'list', 'content' => '<ul><li onclick="x()">Lisbon<script>alert(1)</script></li><li><a href="javascript:void(0)">Faro</a></li></ul>']))
        ->toBe('<ul><li>Lisbon</li><li><a>Faro</a></li></ul>');
});

it('validates its attrs', function () {
    $rules = (new ListBlock)->rules();

    expect(Validator::make(['ordered' => true, 'start' => 2], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['start' => 0], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['ordered' => 'sometimes'], $rules)->fails())->toBeTrue();
});

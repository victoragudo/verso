<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\HeadingBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named heading', function () {
    expect((new HeadingBlock)->name())->toBe('heading');
});

it('renders the chosen level', function (int $level) {
    expect($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['level' => $level], 'content' => 'Where to eat']))
        ->toBe("<h{$level}>Where to eat</h{$level}>");
})->with([1, 2, 3, 4, 5, 6]);

it('clamps heading levels', function () {
    expect($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['level' => 9], 'content' => 'Menu']))->toBe('<h6>Menu</h6>')
        ->and($this->renderer->renderBlock(['type' => 'heading', 'attrs' => [], 'content' => 'Menu']))->toBe('<h2>Menu</h2>')
        ->and($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['level' => 0], 'content' => 'Menu']))->toBe('<h1>Menu</h1>')
        ->and($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['level' => 'big'], 'content' => 'Menu']))->toBe('<h2>Menu</h2>');
});

it('adds a safe anchor id', function () {
    expect($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['level' => 2, 'anchor' => 'day-two'], 'content' => 'Day two']))
        ->toBe('<h2 id="day-two">Day two</h2>');
});

it('ignores unsafe anchors', function (string $anchor) {
    expect($this->renderer->renderBlock(['type' => 'heading', 'attrs' => ['anchor' => $anchor], 'content' => 'Day two']))
        ->toBe('<h2>Day two</h2>');
})->with(['" onmouseover="alert(1)', '2nd-day', 'day two', '']);

it('sanitizes inline content', function () {
    expect($this->renderer->renderBlock(['type' => 'heading', 'content' => '<h2>Porto <em onclick="x">by night</em><script>alert(1)</script></h2>']))
        ->toBe('<h2>Porto <em>by night</em></h2>');
});

it('validates level and anchor', function () {
    $rules = (new HeadingBlock)->rules();

    expect(Validator::make(['level' => 3, 'anchor' => 'where-to-eat'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['level' => 7], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['anchor' => 'x" onclick="y'], $rules)->fails())->toBeTrue();
});

<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\ButtonBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named button', function () {
    expect((new ButtonBlock)->name())->toBe('button');
});

it('renders a styled link', function () {
    expect($this->renderer->renderBlock(['type' => 'button', 'attrs' => ['url' => '/tours/port-cellars', 'label' => 'Book a cellar tour', 'style' => 'outline']]))
        ->toBe('<div class="verso-buttons"><a class="verso-button verso-button--outline" href="/tours/port-cellars">Book a cellar tour</a></div>');
});

it('opens in a new tab safely', function () {
    expect($this->renderer->renderBlock(['type' => 'button', 'attrs' => ['url' => 'https://visitporto.travel', 'label' => 'Visit Porto', 'newTab' => true]]))
        ->toBe('<div class="verso-buttons"><a class="verso-button verso-button--primary" href="https://visitporto.travel" target="_blank" rel="noopener noreferrer">Visit Porto</a></div>');
});

it('falls back to the primary style', function () {
    expect($this->renderer->renderBlock(['type' => 'button', 'attrs' => ['url' => '/', 'label' => 'Home', 'style' => 'neon" onclick="x']]))
        ->toContain('verso-button--primary');
});

it('escapes the label', function () {
    expect($this->renderer->renderBlock(['type' => 'button', 'attrs' => ['url' => '/', 'label' => '<b>Fish & chips</b>']]))
        ->toContain('>&lt;b&gt;Fish &amp; chips&lt;/b&gt;</a>');
});

it('renders nothing without a safe url or a label', function (array $attrs) {
    expect($this->renderer->renderBlock(['type' => 'button', 'attrs' => $attrs]))->toBe('');
})->with([
    [['url' => 'javascript:alert(1)', 'label' => 'Click']],
    [['url' => '/contact', 'label' => '   ']],
    [['label' => 'Contact']],
]);

it('validates its attrs', function () {
    $rules = (new ButtonBlock)->rules();

    expect(Validator::make(['url' => '/contact', 'label' => 'Contact', 'style' => 'secondary', 'newTab' => false], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['url' => 'javascript:alert(1)'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['style' => 'neon'], $rules)->fails())->toBeTrue();
});

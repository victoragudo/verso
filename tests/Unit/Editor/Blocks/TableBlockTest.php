<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\TableBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named table', function () {
    expect((new TableBlock)->name())->toBe('table');
});

it('renders a header row, body rows and a caption', function () {
    expect($this->renderer->renderBlock(['type' => 'table', 'attrs' => [
        'header' => true,
        'caption' => 'Opening hours',
        'rows' => [['Day', 'Hours'], ['Monday', '<strong>Closed</strong>'], ['Tuesday', '9:00 to 18:00']],
    ]]))->toBe('<figure class="verso-table"><table>'
        .'<thead><tr><th>Day</th><th>Hours</th></tr></thead>'
        .'<tbody><tr><td>Monday</td><td><strong>Closed</strong></td></tr><tr><td>Tuesday</td><td>9:00 to 18:00</td></tr></tbody>'
        .'</table><figcaption>Opening hours</figcaption></figure>');
});

it('renders rows without a header', function () {
    expect($this->renderer->renderBlock(['type' => 'table', 'attrs' => ['rows' => [['Porto', '231,800']]]]))
        ->toBe('<figure class="verso-table"><table><tbody><tr><td>Porto</td><td>231,800</td></tr></tbody></table></figure>');
});

it('sanitizes every cell', function () {
    expect($this->renderer->renderBlock(['type' => 'table', 'attrs' => ['rows' => [['<img src=x onerror=alert(1)>Lisbon', ['bad'], '<a href="javascript:alert(1)">Faro</a>']]]]))
        ->toBe('<figure class="verso-table"><table><tbody><tr><td>Lisbon</td><td></td><td><a>Faro</a></td></tr></tbody></table></figure>');
});

it('renders nothing without rows', function () {
    expect($this->renderer->renderBlock(['type' => 'table', 'attrs' => ['rows' => []]]))->toBe('')
        ->and($this->renderer->renderBlock(['type' => 'table', 'attrs' => ['rows' => ['not a row']]]))->toBe('');
});

it('validates its attrs', function () {
    $rules = (new TableBlock)->rules();

    expect(Validator::make(['rows' => [['A', 'B']], 'header' => true], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['rows' => 'A,B'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['rows' => [[['nested']]]], $rules)->fails())->toBeTrue();
});

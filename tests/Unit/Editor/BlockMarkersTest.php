<?php

use App\Verso\Editor\BlockRenderer;

afterEach(function () {
    app(BlockRenderer::class)->showMarkers(false);
});

function harbourSection(array $design = []): array
{
    return [
        'id' => 'b_harbour',
        'type' => 'section',
        'attrs' => array_filter(['tag' => 'section', 'label' => 'Fresh fish', 'design' => $design]),
        'content' => '',
        'children' => [
            ['id' => 'b_title', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Fresh fish every morning', 'children' => []],
            ['id' => 'b_text', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Straight from the boats of Porto.</p>', 'children' => []],
        ],
    ];
}

it('adds no editor markers outside edit mode', function () {
    $html = app(BlockRenderer::class)->render([harbourSection()]);

    expect($html)->not->toContain('data-verso-block="')
        ->and($html)->toContain('<h2>Fresh fish every morning</h2>');
});

it('marks the first tag of every block with its id and type in edit mode', function () {
    $renderer = app(BlockRenderer::class);
    $renderer->showMarkers();

    $html = $renderer->render([harbourSection(['spacing' => ['padding' => ['top' => '2rem']]])]);

    expect($html)->toMatch('/<section data-verso-block="b_harbour" data-verso-type="section" class="verso-section vs-[a-f0-9]+" aria-label="Fresh fish">/')
        ->and($html)->toContain('<h2 data-verso-block="b_title" data-verso-type="heading">Fresh fish every morning</h2>')
        ->and($html)->toContain('<p data-verso-block="b_text" data-verso-type="paragraph">Straight from the boats of Porto.</p>');
});

it('escapes ids in the markers', function () {
    $renderer = app(BlockRenderer::class);
    $renderer->showMarkers();

    $html = $renderer->render([['id' => 'b_"x', 'type' => 'heading', 'attrs' => [], 'content' => 'Menu', 'children' => []]]);

    expect($html)->toContain('data-verso-block="b_&quot;x"');
});

it('leaves blocks hidden on every screen out of the page', function () {
    $hidden = harbourSection(['visibility' => ['hidden' => true]]);
    $html = app(BlockRenderer::class)->render([$hidden, ['id' => 'b_after', 'type' => 'heading', 'attrs' => [], 'content' => 'Opening hours', 'children' => []]]);

    expect($html)->not->toContain('Fresh fish every morning')
        ->and($html)->toContain('Opening hours');
});

it('keeps blocks hidden only on some screens and hides them with css', function () {
    $html = app(BlockRenderer::class)->render([harbourSection(['visibility' => ['hidden' => ['base' => true, 'mobile' => false]]])]);

    expect($html)->toContain('Fresh fish every morning')
        ->and($html)->toContain('display:none');
});

it('shows hidden blocks in edit mode, marked and without the hiding css', function () {
    $renderer = app(BlockRenderer::class);
    $renderer->showMarkers();

    $html = $renderer->render([harbourSection(['visibility' => ['hidden' => true]])]);

    expect($html)->toContain('data-verso-block="b_harbour" data-verso-type="section" data-verso-hidden')
        ->and($html)->toContain('Fresh fish every morning')
        ->and($html)->not->toContain('display:none');
});

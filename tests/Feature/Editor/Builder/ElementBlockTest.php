<?php

use App\Models\User;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Editor\Support\ElementPolicy;
use Illuminate\Support\Facades\Validator;

// The first validation error of a list of blocks saved by this user, or null.
function elementError(array $blocks, ?User $user = null): ?string
{
    $rule = $user === null ? ValidBlocks::trusted() : new ValidBlocks($user);
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [$rule]]);

    return $validator->fails() ? $validator->errors()->first('blocks') : null;
}

function element(string $tag, array $attributes = [], string $content = '', array $children = [], string $id = 'b_el'): array
{
    return ['id' => $id, 'type' => 'element', 'attrs' => ['tag' => $tag, 'attributes' => $attributes], 'content' => $content, 'children' => $children];
}

it('renders a nav with aria label and nested blocks', function () {
    $html = app(BlockRenderer::class)->render([
        element('nav', ['aria-label' => 'Opening hours', 'class' => 'hours wide', 'data-season' => 'summer'], '', [
            ['id' => 'b_p', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Monday to Friday, 8 to 18</p>'],
        ]),
    ]);

    expect($html)->toBe('<nav class="verso-element hours wide" aria-label="Opening hours" data-season="summer"><p>Monday to Friday, 8 to 18</p></nav>');
});

it('renders text, safe links and boolean attributes', function () {
    $renderer = app(BlockRenderer::class);

    expect($renderer->render([element('a', ['href' => 'https://harbor-bakery.example/menu', 'target' => '_blank'], 'See the <strong>menu</strong>')]))
        ->toBe('<a class="verso-element" href="https://harbor-bakery.example/menu" rel="noopener" target="_blank">See the <strong>menu</strong></a>')
        ->and($renderer->render([element('details', ['open' => 'open'], '', [element('summary', [], 'Allergens', [], 'b_sum')])]))
        ->toBe('<details class="verso-element" open><summary class="verso-element">Allergens</summary></details>')
        ->and($renderer->render([element('time', ['datetime' => '2026-12-24T08:00'], 'Christmas Eve')]))
        ->toContain('datetime="2026-12-24T08:00"');
});

it('removes unsafe text markup and adds the style class', function () {
    $html = app(BlockRenderer::class)->render([[
        ...element('span', [], 'Fresh<script>alert(1)</script> bread'),
        'attrs' => ['tag' => 'span', 'design' => ['color' => ['text' => '#1f6f5c']]],
    ]]);

    expect($html)->not->toContain('<script')
        ->and($html)->toMatch('/<span class="verso-element vs-[a-z0-9]+">/');
});

it('accepts every tag of the allow list', function (string $tag) {
    expect(elementError([element($tag)]))->toBeNull();
})->with(array_keys(ElementPolicy::TAGS));

it('refuses dangerous tags and attributes', function (array $block, string $message) {
    expect(elementError([$block]))->toBe($message);
})->with([
    'script tag' => [element('script'), 'Block 1: the selected tag is invalid.'],
    'iframe tag' => [element('iframe'), 'Block 1: the selected tag is invalid.'],
    'style tag' => [element('style'), 'Block 1: the selected tag is invalid.'],
    'event handler' => [element('div', ['onclick' => 'alert(1)']), 'Block 1: the attribute "onclick" is not allowed on <div>. Use the HTML block for it.'],
    'style attribute' => [element('div', ['style' => 'color:red']), 'Block 1: the attribute "style" is not allowed on <div>. Use the HTML block for it.'],
    'javascript link' => [element('a', ['href' => 'javascript:alert(1)']), 'Block 1: the address in "href" is not allowed.'],
    'href on a div' => [element('div', ['href' => '/menu']), 'Block 1: the attribute "href" is not allowed on <div>. Use the HTML block for it.'],
    'quote in a class' => [element('div', ['class' => 'card" onmouseover="x']), 'Block 1: the value of "class" is not valid.'],
    'multi line value' => [element('div', ['title' => "Fresh\nbread"]), 'Block 1: the value of "title" must be a short single line of text.'],
    'list of attributes' => [element('div', ['id', 'class']), 'Block 1: the attributes must be a list of names and values.'],
    'no tag' => [['id' => 'b_x', 'type' => 'element', 'attrs' => []], 'Block 1: the tag field is required.'],
]);

it('limits the number of attributes', function () {
    config(['verso.builder.element.max_attributes' => 2]);
    app()->forgetInstance(ElementPolicy::class);
    app()->forgetInstance(BlockRegistry::class);

    expect(elementError([element('div', ['id' => 'menu', 'title' => 'Menu', 'lang' => 'en'])]))
        ->toBe('Block 1: an element can have at most 2 attributes.');
});

it('only lets roles with the element feature add elements', function () {
    expect(elementError([element('section')], User::factory()->author()->create()))
        ->toBe('Block 1: you are not allowed to add "element" blocks.')
        ->and(elementError([element('section')], User::factory()->editor()->create()))->toBeNull();
});

it('keeps the raw HTML block limited to its capability', function () {
    $html = ['id' => 'b_h', 'type' => 'html', 'attrs' => [], 'content' => '<script>console.log("hi")</script>'];

    expect(elementError([$html], User::factory()->editor()->create()))
        ->toBe('Block 1: you are not allowed to add "html" blocks.');
});

it('sends the element tags to the editor', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', 'page'))
        ->assertInertia(fn ($page) => $page
            ->where('editor.elementTags.0', ['value' => 'div', 'label' => 'Box (div)'])
            ->where('editor.features', fn ($features) => collect($features)->contains('element_block')));
});

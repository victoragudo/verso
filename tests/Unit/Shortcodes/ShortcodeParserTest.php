<?php

use App\Verso\Shortcodes\Parsing\ShortcodeLimits;
use App\Verso\Shortcodes\Parsing\ShortcodeNode;
use App\Verso\Shortcodes\Parsing\ShortcodeParser;
use App\Verso\Shortcodes\Parsing\TextNode;

function shortcodeParser(int $depth = 3, int $count = 50, int $length = 500): ShortcodeParser
{
    return new ShortcodeParser(new ShortcodeLimits($depth, $count, $length));
}

// Text of every node, with shortcodes shown as <name>.
function parsedShape(array $nodes): array
{
    return array_map(fn (TextNode|ShortcodeNode $node): string => $node instanceof TextNode ? $node->text : '<'.$node->name.'>', $nodes);
}

it('reads double quoted, single quoted, bare values and flags', function () {
    [$node] = shortcodeParser()->parse('[form slug="contact" title=\'Ask Marta\' width=600 ajax]');

    expect($node)->toBeInstanceOf(ShortcodeNode::class)
        ->and($node->name)->toBe('form')
        ->and($node->attrs)->toBe(['slug' => 'contact', 'title' => 'Ask Marta', 'width' => '600', 'ajax' => true])
        ->and($node->raw)->toBe('[form slug="contact" title=\'Ask Marta\' width=600 ajax]')
        ->and($node->enclosing())->toBeFalse();
});

it('keeps the text around shortcodes in order', function () {
    $nodes = shortcodeParser()->parse('Order a cake: [form slug="cakes"] or call us.');

    expect(parsedShape($nodes))->toBe(['Order a cake: ', '<form>', ' or call us.']);
});

it('allows brackets and spaces inside quoted values', function () {
    [$node] = shortcodeParser()->parse('[form title="Pastéis [fresh] daily" text=\'a ] b\']');

    expect($node->attrs)->toBe(['title' => 'Pastéis [fresh] daily', 'text' => 'a ] b']);
});

it('decodes HTML entities in values, because the text is sanitized HTML', function () {
    [$node] = shortcodeParser()->parse('[form title="Caf&eacute; &amp; Bar &quot;Lumiar&quot;"]');

    expect($node->attrs['title'])->toBe('Café & Bar "Lumiar"');
});

it('reads self closing tags', function () {
    $nodes = shortcodeParser()->parse('[form slug="contact" /] and [newsletter/]');

    expect(parsedShape($nodes))->toBe(['<form>', ' and ', '<newsletter>'])
        ->and($nodes[0]->attrs)->toBe(['slug' => 'contact']);
});

it('reads enclosing shortcodes with inner text and nested shortcodes', function () {
    [$node] = shortcodeParser()->parse('[box tone="warm"]Open on Sunday. [form slug="contact"][/box]');

    expect($node->enclosing())->toBeTrue()
        ->and($node->inner)->toBe('Open on Sunday. [form slug="contact"]')
        ->and(parsedShape($node->children))->toBe(['Open on Sunday. ', '<form>']);
});

it('treats a tag as self closing when the name cannot wrap text', function () {
    $nodes = shortcodeParser()->parse('[form slug="a"]middle[/form]', fn (string $name): bool => $name !== 'form');

    expect(parsedShape($nodes))->toBe(['<form>', 'middle[/form]']);
});

it('prints escaped shortcodes as text', function (string $input, string $text) {
    $nodes = shortcodeParser()->parse($input);

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0])->toBeInstanceOf(TextNode::class)
        ->and($nodes[0]->text)->toBe($text);
})->with([
    'simple' => ['[[form]]', '[form]'],
    'with attributes' => ['[[form slug="contact"]]', '[form slug="contact"]'],
    'enclosing' => ['[[box]inner[/box]]', '[box]inner[/box]'],
]);

it('leaves broken input as plain text', function (string $input) {
    $nodes = shortcodeParser()->parse($input);

    expect(collect($nodes)->every(fn ($node) => $node instanceof TextNode))->toBeTrue()
        ->and(implode('', array_map(fn (TextNode $node) => $node->text, $nodes)))->toBe($input);
})->with([
    'no closing bracket' => ['[form slug="contact"'],
    'unclosed quote' => ['[form slug="contact]'],
    'name starts with a digit' => ['[1form]'],
    'empty brackets' => ['[]'],
    'stray closing tag' => ['[/form]'],
    'space before name' => ['[ form]'],
    'markdown link' => ['[Lisbon bakery](https://example.org)'],
    'value missing after equals' => ['[form slug= ]'],
    'lone bracket' => ['price [ 2 euros'],
]);

it('limits the number of shortcodes per text and keeps the rest as text', function () {
    $nodes = shortcodeParser(count: 2)->parse('[a][b][c]');

    expect(parsedShape($nodes))->toBe(['<a>', '<b>', '[c]']);
});

it('marks shortcodes nested deeper than the limit', function () {
    [$outer] = shortcodeParser(depth: 2)->parse('[box][panel][form][/panel][/box]');
    [$inner] = $outer->children;

    expect($outer->valid())->toBeTrue()
        ->and($inner->name)->toBe('panel')
        ->and($inner->error)->toBe('Shortcodes are nested too deeply.')
        ->and($inner->children[0])->toBeInstanceOf(TextNode::class);
});

it('marks attribute values that are too long', function () {
    [$node] = shortcodeParser(length: 10)->parse('[form title="A very long heading text"]');

    expect($node->valid())->toBeFalse()
        ->and($node->error)->toContain('"title" is too long');
});

it('lists every shortcode with all() and finds them with contains()', function () {
    $parser = shortcodeParser();

    expect(array_map(fn ($node) => $node->name, $parser->all('[box][form slug="a"][/box] [shop-product id=4]')))->toBe(['box', 'form', 'shop-product'])
        ->and($parser->contains('No shortcodes [here'))->toBeFalse()
        ->and($parser->contains('Plain text'))->toBeFalse()
        ->and($parser->contains('See [form]'))->toBeTrue();
});

it('handles multibyte text around shortcodes', function () {
    $nodes = shortcodeParser()->parse('Pão de ló 🍰 [form slug="bolo"] Olá');

    expect(parsedShape($nodes))->toBe(['Pão de ló 🍰 ', '<form>', ' Olá']);
});

it('reads names with dashes and underscores', function () {
    [$node] = shortcodeParser()->parse('[shop-product product="linen-apron" show_button=false]');

    expect($node->name)->toBe('shop-product')
        ->and($node->attrs)->toBe(['product' => 'linen-apron', 'show_button' => 'false']);
});

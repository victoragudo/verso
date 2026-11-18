<?php

use App\Models\User;
use App\Verso\Editor\Rules\ValidBlocks;
use Illuminate\Support\Facades\Validator;

// Runs the rule on a list of blocks and returns the first error, or null when it passes.
function blocksError(mixed $blocks, ?ValidBlocks $rule = null): ?string
{
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [$rule ?? new ValidBlocks]]);

    return $validator->fails() ? $validator->errors()->first('blocks') : null;
}

it('passes a realistic article', function () {
    $blocks = [
        ['id' => 'b_h1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Where to eat in Porto', 'children' => []],
        ['id' => 'b_p1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Start at <strong>Café Santiago</strong>.</p>'],
        ['id' => 'b_i1', 'type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/img/francesinha.jpg', 'alt' => 'A francesinha']],
        ['id' => 'b_c1', 'type' => 'columns', 'attrs' => [], 'children' => [
            ['id' => 'b_c1a', 'type' => 'column', 'attrs' => ['width' => 50], 'children' => [
                ['id' => 'b_c1a1', 'type' => 'list', 'attrs' => ['ordered' => false], 'content' => '<li>Bolhão market</li>'],
            ]],
            ['id' => 'b_c1b', 'type' => 'column', 'attrs' => ['width' => 50], 'children' => []],
        ]],
        ['id' => 'b_e1', 'type' => 'embed', 'attrs' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
        ['id' => 'b_t1', 'type' => 'table', 'attrs' => ['rows' => [['Dish', 'Price'], ['Francesinha', '12 euros']], 'header' => true]],
    ];

    expect(blocksError($blocks))->toBeNull();
});

it('passes null and an empty list', function () {
    expect(blocksError(null))->toBeNull()
        ->and(blocksError([]))->toBeNull();
});

it('fails when the value is not a list', function (mixed $value) {
    expect(blocksError($value))->toBe('The content must be a list of blocks.');
})->with([
    'text' => '<p>Hello</p>',
    'object' => [['first' => ['id' => 'b_1', 'type' => 'paragraph']]],
    'number' => 12,
]);

it('fails for items that are not blocks', function () {
    expect(blocksError(['just text']))->toBe('Block 1: it is not a block.');
});

it('fails for unknown types', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'carousel']]))->toBe('Block 1: the block type is unknown.')
        ->and(blocksError([['id' => 'b_1']]))->toBe('Block 1: the block type is unknown.');
});

it('fails for missing, long or repeated ids', function () {
    expect(blocksError([['type' => 'separator']]))->toBe('Block 1: the id must be a text of 1 to 64 characters.')
        ->and(blocksError([['id' => str_repeat('a', 65), 'type' => 'separator']]))->toBe('Block 1: the id must be a text of 1 to 64 characters.')
        ->and(blocksError([['id' => 42, 'type' => 'separator']]))->toBe('Block 1: the id must be a text of 1 to 64 characters.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'separator'], ['id' => 'b_1', 'type' => 'separator']]))->toBe('Block 2: the id "b_1" is used twice.');
});

it('fails when content is not text', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'paragraph', 'content' => ['<p>Hi</p>']]]))->toBe('Block 1: the content must be text.');
});

it('fails when attrs or children have the wrong shape', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'heading', 'attrs' => 'level=2']]))->toBe('Block 1: the attrs must be an object.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'heading', 'attrs' => [2]]]))->toBe('Block 1: the attrs must be an object.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => 'none']]))->toBe('Block 1: the children must be a list of blocks.');
});

it('reports attribute errors from the block rules', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'heading', 'attrs' => ['level' => 8]]]))->toBe('Block 1: the level field must be between 1 and 6.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']]]))->toBe('Block 1: the src must be a safe web address.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'button', 'attrs' => ['url' => 'data:text/html,hi', 'label' => 'Go']]]))->toBe('Block 1: the url must be a safe web address.');
});

it('fails for children a block does not accept', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => [['id' => 'b_2', 'type' => 'paragraph']]]]))
        ->toBe('Block 1.1: a "paragraph" block cannot be placed inside a "columns" block.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'paragraph', 'children' => [['id' => 'b_2', 'type' => 'paragraph']]]]))
        ->toBe('Block 1.1: a "paragraph" block cannot be placed inside a "paragraph" block.')
        ->and(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => [['id' => 'b_2', 'type' => 'column', 'children' => [['id' => 'b_3', 'type' => 'columns']]]]]]))
        ->toBe('Block 1.1.1: a "columns" block cannot be placed inside a "column" block.');
});

it('reports the position of nested errors', function () {
    expect(blocksError([
        ['id' => 'b_1', 'type' => 'separator'],
        ['id' => 'b_2', 'type' => 'columns', 'children' => [
            ['id' => 'b_3', 'type' => 'column'],
            ['id' => 'b_4', 'type' => 'column', 'children' => [['id' => 'b_5', 'type' => 'heading', 'attrs' => ['level' => 0]]]],
        ]],
    ]))->toBe('Block 2.2.1: the level field must be between 1 and 6.');
});

it('limits the number of blocks', function () {
    config(['verso.editor.max_blocks' => 3]);
    $blocks = array_map(fn (int $i): array => ['id' => "b_{$i}", 'type' => 'separator'], range(1, 4));

    expect(blocksError(array_slice($blocks, 0, 3)))->toBeNull()
        ->and(blocksError($blocks))->toBe('Block 4: there are too many blocks.');
});

it('limits nesting depth', function () {
    config(['verso.editor.max_depth' => 2]);

    expect(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => [['id' => 'b_2', 'type' => 'column']]]]))->toBeNull()
        ->and(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => [['id' => 'b_2', 'type' => 'column', 'children' => [['id' => 'b_3', 'type' => 'separator']]]]]]))
        ->toBe('Block 1.1.1: blocks are nested too deeply.');
});

it('lets admins save raw html blocks', function () {
    $admin = User::factory()->admin()->create();

    expect(blocksError([['id' => 'b_1', 'type' => 'html', 'content' => '<div id="map"></div>']], new ValidBlocks($admin)))->toBeNull();
});

it('stops other users from saving raw html blocks', function () {
    $editor = User::factory()->editor()->create();
    $error = 'Block 1: you are not allowed to add "html" blocks.';

    expect(blocksError([['id' => 'b_1', 'type' => 'html', 'content' => '<script>alert(1)</script>']], new ValidBlocks($editor)))->toBe($error)
        ->and(blocksError([['id' => 'b_1', 'type' => 'html', 'content' => '<b>x</b>']]))->toBe($error);
});

it('also checks html blocks hidden inside columns', function () {
    $author = User::factory()->author()->create();

    expect(blocksError([['id' => 'b_1', 'type' => 'columns', 'children' => [
        ['id' => 'b_2', 'type' => 'column', 'children' => [['id' => 'b_3', 'type' => 'html', 'content' => '<script>alert(1)</script>']]],
    ]]], new ValidBlocks($author)))->toBe('Block 1.1.1: you are not allowed to add "html" blocks.');
});

it('uses the logged in user when none is given', function () {
    $this->actingAs(User::factory()->admin()->create());

    expect(blocksError([['id' => 'b_1', 'type' => 'html', 'content' => '<hr>']]))->toBeNull();
});

it('skips capability checks for trusted code', function () {
    expect(blocksError([['id' => 'b_1', 'type' => 'html', 'content' => '<hr>']], ValidBlocks::trusted()))->toBeNull();
});

it('can be reused for several values', function () {
    $rule = new ValidBlocks;
    $blocks = [['id' => 'b_1', 'type' => 'separator']];

    expect(blocksError($blocks, $rule))->toBeNull()
        ->and(blocksError($blocks, $rule))->toBeNull();
});

it('lets an editor keep a raw html block an admin saved before', function () {
    $editor = User::factory()->editor()->create();
    $stored = [
        ['id' => 'b_intro', 'type' => 'paragraph', 'content' => '<p>Opening hours</p>'],
        ['id' => 'b_map', 'type' => 'html', 'attrs' => [], 'content' => '<iframe src="https://maps.example.org/harbour"></iframe>'],
    ];
    $sent = [
        ['id' => 'b_map', 'type' => 'html', 'content' => '<iframe src="https://maps.example.org/harbour"></iframe>'],
        ['id' => 'b_intro', 'type' => 'paragraph', 'content' => '<p>Opening hours, updated for summer</p>'],
    ];

    expect(blocksError($sent, new ValidBlocks($editor, stored: $stored)))->toBeNull();
});

it('still needs the html capability when the stored html changes', function () {
    $editor = User::factory()->editor()->create();
    $stored = [['id' => 'b_map', 'type' => 'html', 'content' => '<iframe src="https://maps.example.org/harbour"></iframe>']];
    $sent = [['id' => 'b_map', 'type' => 'html', 'content' => '<script>steal()</script>']];

    expect(blocksError($sent, new ValidBlocks($editor, stored: $stored)))->toBe('Block 1: you are not allowed to add "html" blocks.');
});

it('still needs the html capability for a new html block next to a stored one', function () {
    $editor = User::factory()->editor()->create();
    $stored = [['id' => 'b_map', 'type' => 'html', 'content' => '<div id="map"></div>']];
    $sent = [
        ['id' => 'b_map', 'type' => 'html', 'content' => '<div id="map"></div>'],
        ['id' => 'b_new', 'type' => 'html', 'content' => '<div id="map"></div>'],
    ];

    expect(blocksError($sent, new ValidBlocks($editor, stored: $stored)))->toBe('Block 2: you are not allowed to add "html" blocks.');
});

it('still needs the html capability when the attrs or type of a stored block change', function () {
    $editor = User::factory()->editor()->create();
    $stored = [
        ['id' => 'b_map', 'type' => 'html', 'attrs' => ['label' => 'Map'], 'content' => '<div id="map"></div>'],
        ['id' => 'b_note', 'type' => 'paragraph', 'content' => '<script>steal()</script>'],
    ];

    expect(blocksError([['id' => 'b_map', 'type' => 'html', 'attrs' => ['label' => 'Other'], 'content' => '<div id="map"></div>']], new ValidBlocks($editor, stored: $stored)))
        ->toBe('Block 1: you are not allowed to add "html" blocks.')
        ->and(blocksError([['id' => 'b_note', 'type' => 'html', 'content' => '<script>steal()</script>']], new ValidBlocks($editor, stored: $stored)))
        ->toBe('Block 1: you are not allowed to add "html" blocks.');
});

it('finds unchanged html blocks moved into or out of columns', function () {
    $author = User::factory()->author()->create();
    $stored = [['id' => 'b_cols', 'type' => 'columns', 'children' => [
        ['id' => 'b_col', 'type' => 'column', 'children' => [['id' => 'b_embed', 'type' => 'html', 'content' => '<div class="booking"></div>']]],
    ]]];

    expect(blocksError([['id' => 'b_embed', 'type' => 'html', 'content' => '<div class="booking"></div>']], new ValidBlocks($author, stored: $stored)))->toBeNull();
});

<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\PluginBlock;
use Inertia\Testing\AssertableInertia as Assert;

// A box block that wraps its children in an aside and accepts paragraphs and headings.
function sidebarBoxBlock(): PluginBlock
{
    return new class implements HasChildBlocks, PluginBlock
    {
        public function name(): string
        {
            return 'sidebar-box';
        }

        public function label(): string
        {
            return 'Sidebar box';
        }

        public function acceptsChild(string $type): bool
        {
            return in_array($type, ['paragraph', 'heading'], true);
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '<aside>'.app(ContentRenderer::class)->renderChildren($block, $entry).'</aside>';
        }
    };
}

function boxedParagraph(string $id, string $text): array
{
    return ['id' => $id, 'type' => 'paragraph', 'attrs' => [], 'content' => $text, 'children' => []];
}

beforeEach(function () {
    $block = sidebarBoxBlock();
    app(PluginBlockRegistry::class)->register($block);
    app(BlockRegistry::class)->register(new PluginBlockAdapter($block));
});

it('tells the editor which plugin blocks hold children', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pluginBlocks', fn ($blocks) => collect($blocks)->firstWhere('name', 'sidebar-box')['children'] === true)
        );
});

it('lets the adapter accept only the children the block allows', function () {
    $adapter = app(BlockRegistry::class)->find('sidebar-box');

    expect($adapter->acceptsChild('paragraph'))->toBeTrue()
        ->and($adapter->acceptsChild('gallery'))->toBeFalse();
});

it('saves allowed children and rejects others', function () {
    $editor = User::factory()->editor()->create();
    $box = fn (array $children) => [['id' => 'b_box1', 'type' => 'sidebar-box', 'attrs' => [], 'content' => '', 'children' => $children]];

    $this->actingAs($editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Where to eat in Porto',
        'status' => 'draft',
        'blocks' => $box([boxedParagraph('b_p1', 'Try the francesinha at Cafe Santiago.')]),
    ])->assertSessionHasNoErrors();

    $this->actingAs($editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Where to sleep in Porto',
        'status' => 'draft',
        'blocks' => $box([['id' => 'b_sep', 'type' => 'separator', 'attrs' => [], 'content' => '', 'children' => []]]),
    ])->assertSessionHasErrors('blocks');

    expect(Entry::firstWhere('slug', 'where-to-eat-in-porto')->blocks[0]['children'][0]['content'])
        ->toBe('Try the francesinha at Cafe Santiago.');
});

it('renders the children through the content renderer', function () {
    $entry = new Entry(['blocks' => [[
        'id' => 'b_box2', 'type' => 'sidebar-box', 'attrs' => [], 'children' => [
            boxedParagraph('b_p2', 'Book the river cruise early.'),
            'not a block',
        ],
    ]]]);

    expect(app(ContentRenderer::class)->render($entry))->toBe('<aside><p>Book the river cruise early.</p></aside>');
});

it('renders the children inside core columns too', function () {
    $html = app(BlockRenderer::class)->render([[
        'id' => 'b_cols', 'type' => 'columns', 'attrs' => [], 'children' => [[
            'id' => 'b_col', 'type' => 'column', 'attrs' => [], 'children' => [[
                'id' => 'b_box3', 'type' => 'sidebar-box', 'attrs' => [], 'children' => [boxedParagraph('b_p3', 'Take the tram to Foz.')],
            ]],
        ]],
    ]]);

    expect($html)->toContain('<aside><p>Take the tram to Foz.</p></aside>');
});

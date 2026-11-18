<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\EntryContentFilter;

beforeEach(function () {
    app(PluginBlockRegistry::class)->register(new class implements PluginBlock
    {
        public function name(): string
        {
            return 'map';
        }

        public function label(): string
        {
            return 'Map';
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '<figure class="map">'.e((string) ($block['attrs']['place'] ?? '')).' in '.e((string) $entry?->title).'</figure>';
        }
    });

    $this->entry = new Entry([
        'title' => 'Three Days in Kyoto',
        'blocks' => [
            ['id' => 'b_1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Where we stayed'],
            ['id' => 'b_2', 'type' => 'map', 'attrs' => ['place' => 'Gion']],
            ['id' => 'b_3', 'type' => 'paragraph', 'attrs' => [], 'content' => 'A small <strong>ryokan</strong>.'],
            ['id' => 'b_4', 'type' => 'carousel', 'attrs' => []],
        ],
    ]);
});

it('renders core and plugin blocks in order and skips unknown ones', function () {
    expect(app(ContentRenderer::class)->render($this->entry))
        ->toBe('<h2>Where we stayed</h2><figure class="map">Gion in Three Days in Kyoto</figure><p>A small <strong>ryokan</strong>.</p>');
});

it('applies the entry content filter', function () {
    Filters::add(EntryContentFilter::class, fn (string $html, Entry $entry) => '<article>'.$html.'</article>');

    expect(app(ContentRenderer::class)->render($this->entry))->toStartWith('<article><h2>')->toEndWith('</p></article>');
});

it('renders an entry without blocks as an empty string', function () {
    expect(app(ContentRenderer::class)->render(new Entry(['title' => 'Empty draft'])))->toBe('');
});

it('renders a single block without an entry', function () {
    expect(app(ContentRenderer::class)->renderBlock(['type' => 'map', 'attrs' => ['place' => 'Arashiyama']]))
        ->toBe('<figure class="map">Arashiyama in </figure>')
        ->and(app(ContentRenderer::class)->renderBlock(['type' => 'paragraph', 'content' => 'Matcha']))->toBe('<p>Matcha</p>');
});

<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\BlockOutputCache;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Events\PluginEnabled;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Plugins\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// A plugin block whose output changes on every render, like a block that greets the visitor.
class VisitorGreetingBlock implements PluginBlock
{
    public static int $renders = 0;

    public function name(): string
    {
        return 'visitor-greeting';
    }

    public function label(): string
    {
        return 'Visitor greeting';
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        self::$renders++;

        return '<p>Welcome back, visit number '.self::$renders.'</p>';
    }
}

function paragraphEntry(string $text): Entry
{
    return Entry::factory()->published()->create([
        'title' => 'Harvest notes from the allotment',
        'blocks' => [['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.$text.'</p>', 'children' => []]],
    ]);
}

it('renders core blocks once and then reads them from the cache', function () {
    $entry = paragraphEntry('The leeks survived the frost.');
    $renderer = app(ContentRenderer::class);
    $first = $renderer->render($entry);

    $cached = Cache::store('array')->get(app(BlockOutputCache::class)->key($entry));

    expect($cached)->toBe($first)
        ->and($renderer->render($entry))->toBe($first);
});

it('renders again when the blocks change', function () {
    $entry = paragraphEntry('The leeks survived the frost.');
    $renderer = app(ContentRenderer::class);
    $renderer->render($entry);

    $entry->blocks = [['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>The beans did not.</p>', 'children' => []]];

    expect($renderer->render($entry))->toContain('The beans did not.');
});

it('never caches entries with plugin blocks', function () {
    app(PluginBlockRegistry::class)->register(new VisitorGreetingBlock);
    VisitorGreetingBlock::$renders = 0;
    $entry = Entry::factory()->published()->create([
        'blocks' => [
            ['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Hello again.</p>', 'children' => []],
            ['id' => 'b_greet1', 'type' => 'visitor-greeting', 'attrs' => [], 'content' => '', 'children' => []],
        ],
    ]);
    $renderer = app(ContentRenderer::class);

    $nested = [['id' => 'b_cols01', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
        ['id' => 'b_col001', 'type' => 'column', 'attrs' => [], 'content' => '', 'children' => [
            ['id' => 'b_greet2', 'type' => 'visitor-greeting', 'attrs' => [], 'content' => '', 'children' => []],
        ]],
    ]]];

    expect($renderer->hasPluginBlocks($entry->blocks))->toBeTrue()
        ->and($renderer->hasPluginBlocks($nested))->toBeTrue()
        ->and($renderer->hasPluginBlocks([['type' => 'paragraph', 'children' => []]]))->toBeFalse();

    $renderer->render($entry);
    $renderer->render($entry);

    expect(VisitorGreetingBlock::$renders)->toBe(2);
});

it('still runs the content filter on cached HTML', function () {
    $entry = paragraphEntry('Compost needs air.');
    $renderer = app(ContentRenderer::class);
    $renderer->render($entry);

    Filters::add(EntryContentFilter::class, fn (string $html) => $html.'<aside>Members only tips</aside>');

    expect($renderer->render($entry))->toEndWith('<aside>Members only tips</aside>');
});

it('drops cached HTML when media, plugins or settings change', function (Closure $change) {
    $entry = paragraphEntry('Rain all week.');
    $cache = app(BlockOutputCache::class);
    $before = $cache->key($entry);

    $change();

    expect($cache->key($entry))->not->toBe($before);
})->with([
    'media saved' => [fn () => Media::factory()->create(['alt' => 'Seed trays on the windowsill'])],
    'plugin enabled' => [fn () => event(new PluginEnabled(new Plugin(slug: 'reading-time', path: base_path('plugins/reading-time'), manifest: null)))],
    'settings changed' => [fn () => app(Settings::class)->set('site.name', 'Allotment Diaries')],
]);

it('costs no query on a cached render', function () {
    $entry = paragraphEntry('Garlic goes in before winter.');
    $renderer = app(ContentRenderer::class);
    $renderer->render($entry);

    DB::enableQueryLog();
    $renderer->render($entry);

    expect(DB::getQueryLog())->toBe([]);
});

it('renders without a cache when it is turned off', function () {
    $cache = new BlockOutputCache(null);
    $calls = 0;
    $render = function () use (&$calls) {
        $calls++;

        return '<p>Frost tonight.</p>';
    };
    $entry = paragraphEntry('Frost tonight.');

    $cache->remember($entry, $render);
    $cache->remember($entry, $render);

    expect($calls)->toBe(2)->and($cache->enabled())->toBeFalse();
});

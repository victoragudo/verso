<?php

use App\Models\User;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Admin\EntryListColumns;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\AdminEntryColumnsFilter;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ShopFixtures;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Belén Ruiz']);
});

// The first row of the entries list of a content type.
function firstRow($test, string $type = 'post', array $query = []): array
{
    $row = [];

    $test->actingAs($test->admin)
        ->get(route('admin.entries.index', ['contentType' => $type, ...$query]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$row) {
            $row = $page->toArray()['props']['entries']['data'][0] ?? [];
        });

    return $row;
}

describe('the columns of a content type', function () {
    it('lists image, taxonomies, comments, words, last modified and SEO for posts', function () {
        $columns = collect(app(EntryListColumns::class)->for(app(ContentTypeRegistry::class)->get('post')))
            ->map(fn (ListColumn $column): array => $column->toArray());

        expect($columns->pluck('key')->all())->toBe(['featured_image', 'tax.category', 'tax.tag', 'comments', 'word_count', 'updated_at', 'seo'])
            ->and($columns->firstWhere('key', 'tax.category'))->toBe(['key' => 'tax.category', 'label' => 'Categories', 'kind' => 'tags', 'defaultFrom' => 'lg'])
            ->and($columns->firstWhere('key', 'seo')['defaultFrom'])->toBe('3xl');
    });

    it('has no taxonomy columns for pages', function () {
        $keys = collect(app(EntryListColumns::class)->for(app(ContentTypeRegistry::class)->get('page')))->pluck('key');

        expect($keys)->not->toContain('tax.category')->toContain('word_count');
    });

    it('lets plugins add columns', function () {
        Filters::add(AdminEntryColumnsFilter::class, fn (array $columns): array => [...$columns, new ListColumn('reading_time', 'Reading time', 'number', '2xl')]);
        Filters::add(AdminEntryRowFilter::class, fn (array $row): array => [...$row, 'columns' => [...$row['columns'], 'reading_time' => 4]]);
        Entry::factory()->published()->by($this->admin)->create(['title' => 'Slow mornings in Kyoto']);

        $this->actingAs($this->admin)
            ->get(route('admin.entries.index', 'post'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->contains('key', 'reading_time'))
                ->where('entries.data.0.columns.reading_time', 4)
            );
    });
});

describe('the values of a row', function () {
    it('sends categories, tags, the comment count and the word count', function () {
        $post = Entry::factory()->published()->by($this->admin)->create([
            'title' => 'Ten markets in Valencia',
            'blocks' => [
                ['id' => 'b_1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>We walked to the <strong>Mercado Central</strong> at dawn.</p>', 'children' => []],
                ['id' => 'b_2', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
                    ['id' => 'b_3', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Oranges, saffron and jamón.</p>', 'children' => []],
                ]],
            ],
        ]);
        $post->terms()->attach([
            Term::factory()->create(['name' => 'Food'])->id,
            Term::factory()->create(['name' => 'Travel'])->id,
            Term::factory()->tag()->create(['name' => 'Spain'])->id,
        ]);
        Comment::factory()->count(3)->create(['entry_id' => $post->id]);

        $columns = firstRow($this)['columns'];

        expect($columns['tax.category'])->toEqualCanonicalizing(['Food', 'Travel'])
            ->and($columns['tax.tag'])->toBe(['Spain'])
            ->and($columns['comments'])->toBe(3)
            ->and($columns['word_count'])->toBe(12)
            ->and($columns['featured_image'])->toBeNull();
    });

    it('sends a small featured image', function () {
        $photo = Media::factory()->create(['alt' => 'Orange stall at the Mercado Central']);
        Entry::factory()->published()->by($this->admin)->create(['meta' => ['featured_image' => ['mediaId' => $photo->id]]]);

        $image = firstRow($this)['columns']['featured_image'];

        expect($image['alt'])->toBe('Orange stall at the Mercado Central')
            ->and($image['src'])->toContain('w=80')->toContain('h=80');
    });

    it('rates the SEO fields', function (array $seo, string $status) {
        Entry::factory()->published()->by($this->admin)->create(['meta' => ['seo' => $seo]]);

        expect(firstRow($this)['columns']['seo'])->toBe($status);
    })->with([
        'both' => [['title' => 'Valencia markets guide', 'description' => 'Where to buy saffron and oranges.'], 'complete'],
        'one' => [['title' => 'Valencia markets guide'], 'partial'],
        'none' => [[], 'missing'],
        'green light' => [['score' => 82], 'good'],
        'amber light' => [['title' => 'Valencia markets guide', 'score' => 45], 'ok'],
        'red light' => [['score' => 10], 'poor'],
    ]);
});

describe('the entries list', function () {
    it('sends the columns and the saved view of the user', function () {
        AdminPreferences::of($this->admin)->saveListView('entries.post', ['tax.category', 'word_count'], 'compact');

        $this->actingAs($this->admin->fresh())
            ->get(route('admin.entries.index', 'post'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('columns', 7)
                ->where('listView', ['key' => 'entries.post', 'columns' => ['tax.category', 'word_count'], 'density' => 'compact'])
            );
    });

    it('counts each status and the trash in one query and pages without a count query', function () {
        Entry::factory()->count(3)->published()->by($this->admin)->create();
        Entry::factory()->count(2)->by($this->admin)->create(['status' => 'draft']);
        Entry::factory()->published()->by($this->admin)->create()->delete();
        config(['verso.admin.per_page' => 2]);

        $this->actingAs($this->admin)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.all', 5)
                ->where('counts.published', 3)
                ->where('counts.draft', 2)
                ->where('counts.trash', 1)
                ->where('entries.total', 5)
                ->where('entries.current_page', 2)
                ->where('entries.last_page', 3)
                ->has('entries.data', 2)
            );

        $this->actingAs($this->admin)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'status' => 'trash']))
            ->assertInertia(fn (Assert $page) => $page->where('entries.total', 1)->has('entries.data', 1));
    });

    it('still counts the matches when searching', function () {
        Entry::factory()->published()->by($this->admin)->create(['title' => 'Sourdough at high altitude']);
        Entry::factory()->published()->by($this->admin)->create(['title' => 'Fermenting hot sauce']);

        $this->actingAs($this->admin)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'search' => 'sourdough']))
            ->assertInertia(fn (Assert $page) => $page->where('entries.total', 1)->where('entries.data.0.title', 'Sourdough at high altitude'));
    });
});

describe('the Shop plugin columns', function () {
    it('adds price, stock and SKU to the product list', function () {
        ShopFixtures::boot();
        ShopFixtures::product('Olive oil 500 ml', 1250, ['sku' => 'OIL-500', 'track_stock' => true, 'stock' => 14]);

        $this->actingAs($this->admin)
            ->get(route('admin.entries.index', 'product'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.0.key', 'price')
                ->where('columns.1.key', 'stock')
                ->where('entries.data.0.columns.price', '€12.50')
                ->where('entries.data.0.columns.stock', 14)
                ->where('entries.data.0.columns.sku', 'OIL-500')
            );
    });
});

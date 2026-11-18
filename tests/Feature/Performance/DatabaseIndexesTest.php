<?php

use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PluginFixtures;

// Index names of a table.
function indexNames(string $table): array
{
    return array_column(Schema::getIndexes($table), 'name');
}

// The SQLite query plan of a builder, as one line.
function sqlitePlan(Builder|Illuminate\Database\Query\Builder $query): string
{
    return collect(DB::select('explain query plan '.$query->toSql(), $query->getBindings()))->pluck('detail')->implode(' | ');
}

it('adds the entry, term and comment indexes', function () {
    expect(indexNames('entries'))->toContain(
        'entries_type_status_published_at_index',
        'entries_type_updated_at_index',
        'entries_status_type_index',
        'entries_parent_id_menu_order_index',
    )
        ->and(indexNames('terms'))->toContain('terms_taxonomy_slug_unique', 'terms_taxonomy_name_index')
        ->and(indexNames('entry_term'))->toContain('entry_term_term_id_index')
        ->and(indexNames('comments'))->toContain('comments_entry_id_status_created_at_index');
});

it('adds the analytics rollup index with the plugin', function () {
    PluginFixtures::boot('analytics');

    expect(indexNames('analytics_events'))->toContain('analytics_events_day_path_index')
        ->and(indexNames('analytics_daily'))->toContain('analytics_daily_dimension_day_index');
});

describe('query plans on SQLite', function () {
    beforeEach(function () {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Query plans are only checked on SQLite.');
        }
    });

    it('reads published entries of a type by date from an index', function () {
        $query = Entry::query()->ofType('post')->published()->orderByDesc('published_at')->orderByDesc('id')->limit(10);

        expect(sqlitePlan($query))->toContain('entries_type_status_published_at_index');
    });

    it('finds a term by taxonomy and slug from an index', function () {
        expect(sqlitePlan(Term::query()->inTaxonomy('category')->where('slug', 'travel')))->toContain('terms_taxonomy_slug_unique');
    });

    it('finds the entries of a term through the pivot index', function () {
        $query = DB::table('entry_term')->select('entry_id')->where('term_id', 4);

        expect(sqlitePlan($query))->toContain('entry_term_term_id_index');
    });

    it('lists the approved comments of an entry from an index', function () {
        $query = Comment::query()->where('entry_id', 12)->where('status', 'approved')->orderBy('created_at');

        expect(sqlitePlan($query))->toContain('comments_entry_id_status_created_at_index');
    });

    it('lists the child pages of a page from an index', function () {
        $query = Entry::query()->where('parent_id', 3)->orderBy('menu_order');

        expect(sqlitePlan($query))->toContain('entries_parent_id_menu_order_index');
    });

    it('lists entries of a type by last change from an index', function () {
        $query = Entry::query()->where('type', 'page')->orderByDesc('updated_at')->limit(20);

        expect(sqlitePlan($query))->toContain('entries_type_updated_at_index');
    });

    it('reads the raw views of one day by path from an index', function () {
        PluginFixtures::boot('analytics');
        $query = DB::table('analytics_events')->select('path')->selectRaw('count(*) as views')->where('day', '2026-11-16')->groupBy('path');

        expect(sqlitePlan($query))->toContain('analytics_events_day_path_index');
    });
});

it('lists the entries of a term with the pivot subquery', function () {
    $travel = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Travel', 'slug' => 'travel']);
    $food = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Food', 'slug' => 'food']);
    $lisbon = Entry::factory()->published()->create(['title' => 'Three days in Lisbon', 'slug' => 'three-days-in-lisbon']);
    $paella = Entry::factory()->published()->create(['title' => 'Paella for beginners', 'slug' => 'paella-for-beginners']);
    $lisbon->terms()->attach($travel);
    $paella->terms()->attach($food);

    $this->get('/category/travel')->assertOk()->assertSee('Three days in Lisbon')->assertDontSee('Paella for beginners');
});

<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\WordPressImporter;
use App\Verso\Media\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// A tiny valid PNG, so image size detection works.
const IMPORT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAIAAAA2iEnWAAAAEklEQVR4nGP4z8DAwMDAwMDAAAAPAQH/0tBpZwAAAABJRU5ErkJggg==';

beforeEach(function () {
    config(['verso.importer.download_retry_delay' => 0]);
    Storage::fake('public');
    Http::fake([
        'harbourlight.example/wp-content/uploads/2024/03/tram-28-alfama.jpg' => Http::response(base64_decode(IMPORT_PNG), 200, ['Content-Type' => 'image/jpeg']),
        'harbourlight.example/wp-content/uploads/2024/03/pasteis-de-nata.png' => Http::response(base64_decode(IMPORT_PNG), 200, ['Content-Type' => 'image/png']),
        'harbourlight.example/wp-content/uploads/2024/04/harbour-map.pdf' => Http::response('Not found', 404),
    ]);

    $this->admin = User::factory()->admin()->create(['email' => 'owner@verso.example']);
});

function runImport(?User $user = null): ImportReport
{
    return app(WordPressImporter::class)->import(base_path('tests/Fixtures/wordpress-export.xml'), $user ?? test()->admin);
}

function importedEntry(int $wordpressId): Entry
{
    return Entry::withTrashed()->with('terms')->get()->firstOrFail(fn (Entry $entry) => ($entry->meta['wordpress']['id'] ?? null) === (string) $wordpressId);
}

it('imports posts, pages and custom post types', function () {
    $report = runImport();

    expect(Entry::query()->count())->toBe(8)
        ->and(Entry::query()->where('type', 'post')->count())->toBe(6)
        ->and(Entry::query()->where('type', 'page')->count())->toBe(2)
        ->and($report->count('entries', ImportReport::CREATED))->toBe(8)
        ->and($report->count('entries', ImportReport::SKIPPED))->toBe(2);
});

it('keeps titles, slugs, dates and statuses', function () {
    runImport();

    $lisbon = importedEntry(10);
    expect($lisbon->title)->toBe('A Weekend in Lisbon')
        ->and($lisbon->slug)->toBe('a-weekend-in-lisbon')
        ->and($lisbon->status)->toBe(EntryStatus::Published)
        ->and($lisbon->published_at->toDateTimeString())->toBe('2024-03-18 09:30:00')
        ->and($lisbon->created_at->toDateTimeString())->toBe('2024-03-18 09:30:00')
        ->and($lisbon->excerpt)->toBe('Two slow days on the seven hills of Lisbon.');

    $draft = importedEntry(12);
    expect($draft->status)->toBe(EntryStatus::Draft)
        ->and($draft->slug)->toBe('packing-list-for-the-rota-vicentina')
        ->and($draft->published_at)->toBeNull();

    expect(importedEntry(13)->status)->toBe(EntryStatus::Scheduled)
        ->and(importedEntry(13)->published_at->toDateTimeString())->toBe('2030-01-15 10:00:00')
        ->and(importedEntry(14)->status)->toBe(EntryStatus::Draft);
});

it('maps unknown custom post types to posts', function () {
    runImport();

    $recipe = importedEntry(30);

    expect($recipe->type)->toBe('post')
        ->and($recipe->meta['wordpress'])->toBeJsonData(['id' => '30', 'type' => 'recipe', 'link' => 'https://harbourlight.example/recipes/grilled-sardines-with-peppers/']);
});

it('converts the post content into blocks', function () {
    runImport();

    $types = array_column(importedEntry(10)->blocks, 'type');

    expect($types)->toBe(['paragraph', 'heading', 'list', 'list', 'quote', 'image', 'gallery', 'embed', 'code', 'separator', 'columns', 'button', 'html', 'paragraph'])
        ->and(array_column(importedEntry(11)->blocks, 'type'))->toBe(['paragraph', 'paragraph', 'heading', 'list', 'image', 'quote', 'separator', 'paragraph']);
});

it('creates authors with the author role and a random password', function () {
    $report = runImport();

    $maria = User::query()->where('email', 'maria.lopes@harbourlight.example')->firstOrFail();
    $tom = User::query()->where('email', 'tom.reed@harbourlight.example')->firstOrFail();

    expect($maria->name)->toBe('Maria Lopes')
        ->and($maria->role)->toBe(Role::Author)
        ->and(strlen($maria->password))->toBeGreaterThan(20)
        ->and($tom->name)->toBe('Tom Reed')
        ->and($report->count('authors', ImportReport::CREATED))->toBe(2)
        ->and(importedEntry(10)->author_id)->toBe($maria->id)
        ->and(importedEntry(11)->author_id)->toBe($tom->id);
});

it('reuses users that already exist and never changes their role', function () {
    $maria = User::factory()->editor()->create(['email' => 'maria.lopes@harbourlight.example', 'name' => 'Maria L.']);

    runImport();

    expect($maria->fresh()->role)->toBe(Role::Editor)
        ->and($maria->fresh()->name)->toBe('Maria L.')
        ->and(User::query()->where('email', 'maria.lopes@harbourlight.example')->count())->toBe(1)
        ->and(importedEntry(10)->author_id)->toBe($maria->id);
});

it('gives posts by unknown authors to the person who runs the import', function () {
    $path = tempnam(sys_get_temp_dir(), 'wxr');
    file_put_contents($path, str_replace('<dc:creator><![CDATA[tomreed]]></dc:creator>', '<dc:creator><![CDATA[ghostwriter]]></dc:creator>', file_get_contents(base_path('tests/Fixtures/wordpress-export.xml'))));

    app(WordPressImporter::class)->import($path, $this->admin);

    expect(importedEntry(11)->author_id)->toBe($this->admin->id);
});

it('imports categories with parents and tags', function () {
    runImport();

    $travel = Term::query()->where('taxonomy', 'category')->where('slug', 'travel')->firstOrFail();
    $portugal = Term::query()->where('taxonomy', 'category')->where('slug', 'portugal')->firstOrFail();

    expect($portugal->parent_id)->toBe($travel->id)
        ->and($travel->description)->toBe('Trips by train, ferry and on foot.')
        ->and(Term::query()->where('slug', 'food')->value('name'))->toBe('Food & Drink')
        ->and(Term::query()->where('taxonomy', 'tag')->pluck('slug')->sort()->values()->all())->toBe(['coffee', 'lisbon', 'tiles'])
        ->and(Term::query()->where('slug', 'main-menu')->exists())->toBeFalse()
        ->and(Term::query()->where('slug', 'portuguese')->exists())->toBeFalse();
});

it('attaches terms to entries', function () {
    runImport();

    $lisbon = importedEntry(10);

    expect($lisbon->termsIn('category')->pluck('slug')->all())->toBe(['portugal'])
        ->and($lisbon->termsIn('tag')->pluck('slug')->sort()->values()->all())->toBe(['lisbon', 'tiles'])
        ->and(importedEntry(30)->termsIn('category')->pluck('slug')->all())->toBe(['food']);
});

it('reuses terms that already exist', function () {
    $travel = Term::factory()->create(['name' => 'Travel stories', 'slug' => 'travel']);

    runImport();

    expect(Term::query()->where('taxonomy', 'category')->where('slug', 'travel')->count())->toBe(1)
        ->and($travel->fresh()->name)->toBe('Travel stories');
});

it('links pages to their parent even when the child comes first', function () {
    runImport();

    $about = importedEntry(20);
    $team = importedEntry(21);

    expect($team->parent_id)->toBe($about->id)
        ->and($team->path())->toBe('/about/our-team')
        ->and($team->menu_order)->toBe(2);
});

it('downloads attachments and keeps the WordPress folders', function () {
    $report = runImport();

    Storage::disk('public')->assertExists('uploads/2024/03/tram-28-alfama.jpg');
    Storage::disk('public')->assertExists('uploads/2024/03/pasteis-de-nata.png');

    expect($report->count('media', ImportReport::CREATED))->toBe(2)
        ->and($report->count('media', ImportReport::FAILED))->toBe(1)
        ->and(implode("\n", $report->messages()))->toContain('harbour-map.pdf: the server answered 404');
});

it('swaps image URLs in blocks for the downloaded files', function () {
    runImport();

    $image = collect(importedEntry(10)->blocks)->firstWhere('type', 'image');

    expect($image['attrs']['src'])->toBe(Storage::disk('public')->url('uploads/2024/03/tram-28-alfama.jpg'))
        ->and($image['attrs']['alt'])->toBe('Tram 28 in Alfama');
});

it('saves the featured image and warns when it is missing', function () {
    $report = runImport();

    expect(importedEntry(10)->meta['featured_image'])->toBeJsonData([
        'mediaId' => Media::query()->where('path', 'uploads/2024/03/tram-28-alfama.jpg')->value('id'),
        'src' => Storage::disk('public')->url('uploads/2024/03/tram-28-alfama.jpg'),
        'alt' => 'Yellow tram 28 on a steep street in Alfama',
    ])
        ->and(importedEntry(30)->meta)->not->toHaveKey('featured_image')
        ->and(implode("\n", $report->messages()))->toContain('featured image of "Grilled Sardines with Peppers"');
});

it('skips downloads when they are turned off', function () {
    config(['verso.importer.download_attachments' => false]);

    $report = runImport();

    Http::assertNothingSent();
    expect($report->count('media', ImportReport::SKIPPED))->toBe(3);
});

it('keeps going when a download throws', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out after 30 seconds'));

    $report = runImport();

    expect($report->count('media', ImportReport::FAILED))->toBe(3)
        ->and(Entry::query()->count())->toBe(8)
        ->and(implode("\n", $report->messages()))->toContain('Connection timed out');
});

it('refuses files larger than the limit', function () {
    config(['verso.importer.max_download_bytes' => 10]);

    $report = runImport();

    expect($report->count('media', ImportReport::FAILED))->toBe(3)
        ->and(implode("\n", $report->messages()))->toContain('larger than 10 bytes');
});

it('stores SEO meta from Yoast and Rank Math', function () {
    app(Settings::class)->set('site.name', 'Harbourlight Journal');

    runImport();

    expect(importedEntry(10)->meta['seo'])->toMatchArray([
        'title' => 'A Weekend in Lisbon - Harbourlight Journal',
        'description' => 'Two slow days in Lisbon: trams, fado and the best custard tarts in Belém.',
        'og_image' => Storage::disk('public')->url('uploads/2024/03/tram-28-alfama.jpg'),
    ])
        ->and(importedEntry(11)->meta['seo'])->toMatchArray([
            'title' => 'Coffee Along the Douro - Porto coffee guide',
            'canonical' => 'https://harbourlight.example/porto-coffee/',
            'noindex' => true,
        ])
        ->and(importedEntry(12)->meta)->not->toHaveKey('seo');
});

it('creates redirects for old URLs', function () {
    runImport();

    $redirects = Redirect::query()->pluck('to_path', 'from_path')->all();

    expect($redirects)->toMatchArray([
        '/2024/03/18/a-weekend-in-lisbon' => '/blog/a-weekend-in-lisbon',
        '/2024/03/a-weekend-in-lisbon' => '/blog/a-weekend-in-lisbon',
        '/?p=10' => '/blog/a-weekend-in-lisbon',
        '/2023/11/05/coffee-along-the-douro' => '/blog/coffee-along-the-douro',
        '/recipes/grilled-sardines-with-peppers' => '/blog/grilled-sardines-with-peppers',
        '/?page_id=20' => '/about',
        '/?p=20' => '/about',
        '/?page_id=21' => '/about/our-team',
    ])
        ->and($redirects)->not->toHaveKey('/about')
        ->and($redirects)->not->toHaveKey('/our-team')
        ->and($redirects)->not->toHaveKey('/?p=12')
        ->and($redirects)->not->toHaveKey('/?p=13')
        ->and(Redirect::query()->where('status_code', '!=', 301)->count())->toBe(0);
});

it('skips date redirects when they are turned off', function () {
    config(['verso.importer.date_redirects' => false]);

    runImport();

    expect(Redirect::query()->where('from_path', '/2024/03/a-weekend-in-lisbon')->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', '/2024/03/18/a-weekend-in-lisbon')->exists())->toBeTrue();
});

it('does not copy anything when it runs again', function () {
    runImport();
    $counts = [Entry::withTrashed()->count(), Term::query()->count(), User::query()->count(), Redirect::query()->count()];
    $blocks = importedEntry(10)->blocks;

    $report = runImport();

    expect([Entry::withTrashed()->count(), Term::query()->count(), User::query()->count(), Redirect::query()->count()])->toBe($counts)
        ->and($report->count('entries', ImportReport::UPDATED))->toBe(8)
        ->and($report->count('entries', ImportReport::CREATED))->toBe(0)
        ->and($report->count('redirects', ImportReport::CREATED))->toBe(0)
        ->and($report->count('media', ImportReport::SKIPPED))->toBe(2)
        ->and(importedEntry(10)->blocks)->toBe($blocks)
        ->and(importedEntry(10)->revisions()->count())->toBe(0);
});

it('updates content that changed in WordPress and keeps other meta', function () {
    runImport();
    $lisbon = importedEntry(10);
    $lisbon->update(['meta' => [...$lisbon->meta, 'layout' => 'wide']]);

    $path = tempnam(sys_get_temp_dir(), 'wxr');
    file_put_contents($path, str_replace('A Weekend in Lisbon]]></title>', 'A Long Weekend in Lisbon]]></title>', file_get_contents(base_path('tests/Fixtures/wordpress-export.xml'))));
    app(WordPressImporter::class)->import($path, $this->admin);

    $lisbon = importedEntry(10);
    expect($lisbon->title)->toBe('A Long Weekend in Lisbon')
        ->and($lisbon->meta['layout'])->toBe('wide')
        ->and($lisbon->revisions()->count())->toBe(1);
});

it('does not bring back entries that were trashed after an import', function () {
    runImport();
    importedEntry(11)->moveToTrash();

    $report = runImport();

    expect(importedEntry(11)->trashed())->toBeTrue()
        ->and($report->count('entries', ImportReport::SKIPPED))->toBe(3);
});

it('reports progress after each item', function () {
    $steps = [];

    app(WordPressImporter::class)->import(base_path('tests/Fixtures/wordpress-export.xml'), $this->admin, function (int $done) use (&$steps) {
        $steps[] = $done;
    });

    expect($steps)->toBe(range(1, 13))
        ->and(app(WordPressImporter::class)->countItems(base_path('tests/Fixtures/wordpress-export.xml')))->toBe(13);
});

it('warns that comments wait for the comments table', function () {
    Schema::drop('comments');

    $report = runImport();

    expect($report->messages())->toContain('Comments were not imported because the comments table does not exist yet.')
        ->and($report->count('comments', ImportReport::SKIPPED))->toBe(4);
});

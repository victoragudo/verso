<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Models\Redirect;
use App\Verso\Importer\Http\Presenters\ImportPresenter;
use App\Verso\Importer\Models\Import;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\ImportedEntry;
use App\Verso\Importer\WordPress\ImportState;
use App\Verso\Importer\WordPress\RedirectWriter;
use App\Verso\Importer\WordPress\TermImporter;
use App\Verso\Importer\Wxr\WxrTerm;
use Carbon\CarbonImmutable;

function partsImportState(): ImportState
{
    return new ImportState(User::factory()->admin()->create(), new ImportReport);
}

it('updates a redirect that points somewhere else', function () {
    $entry = Entry::factory()->published()->create(['type' => 'post', 'slug' => 'night-train-to-porto']);
    Redirect::point('/2022/05/night-train-to-porto', '/blog/old-night-train');
    $state = partsImportState();

    (new RedirectWriter)->write(new ImportedEntry($entry->id, 55, 'post', 'https://harbourlight.example/2022/05/night-train-to-porto/', 'night-train-to-porto', CarbonImmutable::parse('2022-05-14 08:00:00')), $state);

    expect(Redirect::findForPath('/2022/05/night-train-to-porto')->to_path)->toBe('/blog/night-train-to-porto')
        ->and($state->report->count('redirects', ImportReport::UPDATED))->toBe(1)
        ->and($state->report->count('redirects', ImportReport::CREATED))->toBe(2);
});

it('writes no redirects for drafts', function () {
    $entry = Entry::factory()->create(['type' => 'post']);

    (new RedirectWriter)->write(new ImportedEntry($entry->id, 56, 'post', 'https://harbourlight.example/draft-notes/', 'draft-notes', null), partsImportState());

    expect(Redirect::query()->count())->toBe(0);
});

it('warns when a term cannot get its parent', function () {
    $state = partsImportState();
    $terms = app(TermImporter::class);

    $terms->import(new WxrTerm('post_tag', 'porto', 'Porto', 'portugal'), $state);
    $terms->import(new WxrTerm('post_tag', 'portugal', 'Portugal'), $state);
    $terms->resolveParents($state);

    expect(Term::query()->where('slug', 'porto')->value('parent_id'))->toBeNull()
        ->and($state->report->messages()[0])->toContain('"Porto" cannot have that parent');
});

it('skips terms of unknown taxonomies', function () {
    $state = partsImportState();

    expect(app(TermImporter::class)->import(new WxrTerm('product_cat', 'shoes', 'Shoes'), $state))->toBeNull()
        ->and($state->report->count('terms', ImportReport::SKIPPED))->toBe(1);
});

it('presents an import for the admin pages', function () {
    $import = Import::factory()->running()->create(['total' => 40, 'processed' => 10, 'original_name' => 'quinta.xml']);

    $summary = ImportPresenter::summary($import);
    $detail = ImportPresenter::detail($import);

    expect($summary)->toMatchArray(['status' => 'running', 'status_label' => 'Running', 'original_name' => 'quinta.xml', 'percent' => 25, 'total' => 40])
        ->and($summary)->not->toHaveKey('report')
        ->and($detail)->toHaveKeys(['report', 'error', 'started_at', 'duration'])
        ->and($detail['duration'])->toBeNull();
});

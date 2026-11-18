<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Importer\Enums\ImportStatus;
use App\Verso\Importer\Jobs\RunWordPressImport;
use App\Verso\Importer\Models\Import;
use App\Verso\Importer\WordPress\WordPressImporter;
use App\Verso\System\Server\RuntimeLimits;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['verso.importer.download_attachments' => false]);
    Storage::fake('local');
    Http::fake();
});

function queuedImport(string $disk = 'local', ?string $contents = null): Import
{
    Storage::disk($disk)->put('imports/harbourlight.xml', $contents ?? file_get_contents(base_path('tests/Fixtures/wordpress-export.xml')));

    return Import::factory()->create([
        'disk' => $disk,
        'path' => 'imports/harbourlight.xml',
        'original_name' => 'harbourlight.WordPress.2026-11-16.xml',
        'user_id' => User::factory()->admin(),
    ]);
}

it('runs the import and saves the report', function () {
    $import = queuedImport();

    (new RunWordPressImport($import->id))->handle(app(WordPressImporter::class), app(RuntimeLimits::class));

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total)->toBe(13)
        ->and($import->processed)->toBe(13)
        ->and($import->started_at)->not->toBeNull()
        ->and($import->finished_at)->not->toBeNull()
        ->and(collect($import->report['rows'])->firstWhere('kind', 'entries')['created'])->toBe(8)
        ->and(Entry::query()->count())->toBe(8);

    Storage::disk('local')->assertMissing('imports/harbourlight.xml');
});

it('gives posts with unknown authors to the uploader', function () {
    $import = queuedImport(contents: str_replace('<dc:creator><![CDATA[tomreed]]></dc:creator>', '<dc:creator><![CDATA[ghostwriter]]></dc:creator>', file_get_contents(base_path('tests/Fixtures/wordpress-export.xml'))));

    RunWordPressImport::dispatchSync($import->id);

    expect(Entry::query()->where('slug', 'coffee-along-the-douro')->value('author_id'))->toBe($import->user_id);
});

it('saves progress while it runs', function () {
    config(['verso.importer.progress_every' => 5]);
    $import = queuedImport();
    $seen = [];

    Import::updated(function (Import $saved) use (&$seen) {
        $seen[] = $saved->processed;
    });

    RunWordPressImport::dispatchSync($import->id);

    expect($seen)->toContain(5)->toContain(10);
});

it('marks the run as failed for a broken file', function () {
    $import = queuedImport(contents: '<?xml version="1.0"?><rss><channel><title>Not WordPress</title></channel></rss>');

    RunWordPressImport::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->error)->toContain('not a WordPress export');
});

it('marks the run as failed when the file is gone', function () {
    $import = queuedImport();
    Storage::disk('local')->delete('imports/harbourlight.xml');

    RunWordPressImport::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed);
});

it('reads files from cloud disks through a temporary copy', function () {
    Storage::fake('s3');
    $import = queuedImport('s3');

    RunWordPressImport::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed);
});

it('does nothing for finished or missing runs', function () {
    $import = Import::factory()->completed()->create();

    RunWordPressImport::dispatchSync($import->id);
    RunWordPressImport::dispatchSync(987654);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and(Entry::query()->count())->toBe(0);
});

it('marks the run as failed when the queue gives up', function () {
    $import = Import::factory()->running()->create();

    (new RunWordPressImport($import->id))->failed(new RuntimeException('Job timed out after 3600 seconds.'));

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->error)->toBe('Job timed out after 3600 seconds.');
});

it('writes the failure in the language of the person who started the import', function () {
    app('translator')->setLoaded(['*' => ['*' => ['es' => ['The import stopped before it finished.' => 'La importación se detuvo antes de terminar.']]]]);
    $import = Import::factory()->running()->create(['user_id' => User::factory()->admin()->create(['admin_locale' => 'es'])]);

    (new RunWordPressImport($import->id))->failed(null);

    expect($import->fresh()->error)->toBe('La importación se detuvo antes de terminar.')
        ->and(app()->getLocale())->toBe('en');
});

it('uses the queue settings from config', function () {
    config(['verso.importer.queue' => 'imports', 'verso.importer.job_timeout' => 900]);

    $job = new RunWordPressImport(1);

    expect($job->queue)->toBe('imports')
        ->and($job->timeout)->toBe(900)
        ->and($job->tries)->toBe(1);
});

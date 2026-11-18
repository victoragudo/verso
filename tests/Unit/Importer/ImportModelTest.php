<?php

use App\Verso\Importer\Enums\ImportStatus;
use App\Verso\Importer\Models\Import;
use Illuminate\Support\Carbon;

it('starts as pending', function () {
    $import = Import::factory()->create();

    expect($import->fresh()->status)->toBe(ImportStatus::Pending)
        ->and($import->processed)->toBe(0)
        ->and($import->user)->not->toBeNull();
});

it('marks a run as running', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    $import = Import::factory()->create(['error' => 'Old failure']);

    $import->markRunning();

    expect($import->fresh()->status)->toBe(ImportStatus::Running)
        ->and($import->started_at->toDateTimeString())->toBe('2026-11-16 10:00:00')
        ->and($import->error)->toBeNull();
});

it('refuses to restart a finished run', function () {
    Import::factory()->completed()->create()->markRunning();
})->throws(LogicException::class);

it('records progress', function () {
    $import = Import::factory()->running()->create();

    $import->recordProgress(40, ['counts' => ['posts' => ['created' => 40]]]);

    expect($import->fresh()->processed)->toBe(40)
        ->and($import->fresh()->report['counts']['posts']['created'])->toBe(40);
});

it('marks a run as completed with its report', function () {
    $import = Import::factory()->running()->create();

    $import->markCompleted(['counts' => [], 'messages' => ['Skipped 2 spam comments.']]);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($import->finished_at)->not->toBeNull()
        ->and($import->report['messages'])->toBe(['Skipped 2 spam comments.']);
});

it('marks a run as failed with the error', function () {
    $import = Import::factory()->running()->create();

    $import->markFailed('The export file is broken near line 12.');

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->error)->toBe('The export file is broken near line 12.');
});

it('measures how long a run took', function () {
    $import = Import::factory()->make(['started_at' => '2026-11-16 10:00:00', 'finished_at' => '2026-11-16 10:03:20']);

    expect($import->durationInSeconds())->toBe(200)
        ->and(Import::factory()->make()->durationInSeconds())->toBeNull();
});

it('knows which statuses are final', function (ImportStatus $status, bool $finished) {
    expect($status->isFinished())->toBe($finished);
})->with([
    [ImportStatus::Pending, false],
    [ImportStatus::Running, false],
    [ImportStatus::Completed, true],
    [ImportStatus::Failed, true],
]);

it('has readable status labels', function () {
    expect(ImportStatus::Completed->label())->toBe('Completed');
});

it('stores the total when it starts', function () {
    $import = Import::factory()->create(['processed' => 5]);

    $import->markRunning(240);

    expect($import->fresh()->total)->toBe(240)
        ->and($import->fresh()->processed)->toBe(0);
});

it('works out how far a run is', function () {
    expect(Import::factory()->running()->make(['total' => 200, 'processed' => 50])->percentDone())->toBe(25)
        ->and(Import::factory()->running()->make(['total' => 3, 'processed' => 2])->percentDone())->toBe(66)
        ->and(Import::factory()->running()->make(['total' => null, 'processed' => 9])->percentDone())->toBeNull()
        ->and(Import::factory()->completed()->make(['total' => null])->percentDone())->toBe(100);
});

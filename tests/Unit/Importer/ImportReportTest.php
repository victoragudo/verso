<?php

use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Support\WordPressDates;

it('counts outcomes per kind', function () {
    $report = new ImportReport;
    $report->add('entries', ImportReport::CREATED);
    $report->add('entries', ImportReport::CREATED);
    $report->add('comments', ImportReport::SKIPPED, 4);

    expect($report->count('entries', ImportReport::CREATED))->toBe(2)
        ->and($report->count('comments', ImportReport::SKIPPED))->toBe(4)
        ->and($report->count('media', ImportReport::FAILED))->toBe(0)
        ->and($report->hasFailures())->toBeFalse();
});

it('knows when something failed', function () {
    $report = new ImportReport;
    $report->add('media', ImportReport::FAILED);

    expect($report->hasFailures())->toBeTrue();
});

it('gives one row per kind', function () {
    $report = new ImportReport;
    $report->add('terms', ImportReport::UPDATED, 3);

    expect($report->rows())->toHaveCount(count(ImportReport::KINDS))
        ->and($report->rows()[1])->toBe(['kind' => 'terms', 'created' => 0, 'updated' => 3, 'skipped' => 0, 'failed' => 0]);
});

it('keeps warnings up to the limit and counts the rest', function () {
    $report = new ImportReport(messageLimit: 2);
    $report->warn('Could not download https://harbourlight.example/a.jpg');
    $report->warn('Could not download https://harbourlight.example/b.jpg');
    $report->warn('Could not download https://harbourlight.example/c.jpg');

    expect($report->messages())->toHaveCount(2)
        ->and($report->toArray()['dropped_messages'])->toBe(1)
        ->and($report->toArray()['messages'][1])->toContain('b.jpg');
});

it('reads GMT dates first', function () {
    $dates = new WordPressDates('Europe/Lisbon');

    expect($dates->parse('2024-07-01 08:00:00', '2024-07-01 09:00:00')->toDateTimeString())->toBe('2024-07-01 08:00:00');
});

it('falls back to the local date in the site timezone', function () {
    $dates = new WordPressDates('Europe/Madrid');

    expect($dates->parse('0000-00-00 00:00:00', '2024-07-01 10:00:00')->toDateTimeString())->toBe('2024-07-01 08:00:00');
});

it('returns null for empty or broken dates', function () {
    $dates = new WordPressDates;

    expect($dates->parse('', ''))->toBeNull()
        ->and($dates->parse('0000-00-00 00:00:00', '0000-00-00 00:00:00'))->toBeNull()
        ->and($dates->parse('not a date at all'))->toBeNull();
});

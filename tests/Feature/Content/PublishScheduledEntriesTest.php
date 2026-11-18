<?php

use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Jobs\PublishScheduledEntries;
use App\Verso\Content\Models\Entry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

it('publishes scheduled entries whose date has come', function () {
    Carbon::setTestNow('2026-11-16 08:00:00');
    $due = Entry::factory()->create(['status' => EntryStatus::Scheduled, 'published_at' => '2026-11-16 07:59:00']);
    $later = Entry::factory()->create(['status' => EntryStatus::Scheduled, 'published_at' => '2026-11-16 09:00:00']);
    Event::fake([EntryPublished::class]);

    $count = app()->call([new PublishScheduledEntries, 'handle']);

    expect($count)->toBe(1)
        ->and($due->fresh()->isPublished())->toBeTrue()
        ->and($due->fresh()->published_at->toDateTimeString())->toBe('2026-11-16 07:59:00')
        ->and($later->fresh()->status)->toBe(EntryStatus::Scheduled);
    Event::assertDispatchedTimes(EntryPublished::class, 1);
});

it('does nothing when no entry is due', function () {
    Entry::factory()->scheduled()->create();

    expect(app()->call([new PublishScheduledEntries, 'handle']))->toBe(0);
});

it('runs every minute through the scheduler', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'verso:publish-scheduled-entries');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});

it('can be dispatched', function () {
    $entry = Entry::factory()->create(['status' => EntryStatus::Scheduled, 'published_at' => now()->subMinutes(5)]);

    PublishScheduledEntries::dispatch();

    expect($entry->fresh()->isPublished())->toBeTrue();
});

<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Jobs\RecordQueueHeartbeat;
use Verso\Plugins\Newsletter\Support\QueueHeartbeat;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00:00', 'UTC'));
    $this->admin = User::factory()->admin()->create(['name' => 'Beatriz Lopes']);
});

function useDatabaseQueue(): void
{
    config(['queue.default' => 'database']);
}

it('shows no notice with the sync queue', function () {
    config(['queue.default' => 'sync']);

    expect(app(QueueHeartbeat::class)->notice())->toBeNull();
});

it('warns when another queue is used and no worker was ever seen', function () {
    useDatabaseQueue();

    $notice = app(QueueHeartbeat::class)->notice();

    expect($notice)->not->toBeNull()
        ->and($notice['title'])->toContain('no queue worker is running')
        ->and($notice['message'])->toContain('"database"')
        ->and($notice['message'])->toContain('php artisan queue:work')
        ->and($notice['lastSeen'])->toBeNull();
});

it('hides the notice while the heartbeat is fresh', function () {
    useDatabaseQueue();
    app(QueueHeartbeat::class)->record();

    $this->travel(14)->minutes();

    expect(app(QueueHeartbeat::class)->notice())->toBeNull();
});

it('warns again once the heartbeat is older than the allowed minutes', function () {
    useDatabaseQueue();
    app(QueueHeartbeat::class)->record();

    $this->travel(16)->minutes();

    expect(app(QueueHeartbeat::class)->notice()['lastSeen'] ?? null)->toBe('2026-11-17T09:00:00+00:00');
});

it('reads the allowed minutes from config', function () {
    useDatabaseQueue();
    config(['newsletter.heartbeat_stale_minutes' => 60]);
    app(QueueHeartbeat::class)->record();

    $this->travel(45)->minutes();

    expect(app(QueueHeartbeat::class)->notice())->toBeNull();
});

it('treats a connection that uses the sync driver as sync', function () {
    config(['queue.default' => 'inline', 'queue.connections.inline' => ['driver' => 'sync']]);

    expect(app(QueueHeartbeat::class)->usesSyncQueue())->toBeTrue()
        ->and(app(QueueHeartbeat::class)->notice())->toBeNull();
});

it('ignores a broken stored heartbeat', function () {
    app(Settings::class)->set(QueueHeartbeat::SETTING, 'last tuesday-ish');

    expect(app(QueueHeartbeat::class)->lastSeen())->toBeNull();
});

it('records the heartbeat when a worker runs the job', function () {
    (new RecordQueueHeartbeat)->handle(app(QueueHeartbeat::class));

    expect(app(QueueHeartbeat::class)->lastSeen()?->toIso8601String())->toBe('2026-11-17T09:00:00+00:00');
});

it('schedules the heartbeat job on the newsletter queue', function () {
    config(['newsletter.queue' => 'mail']);
    Queue::fake();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'newsletter:queue-heartbeat');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');

    $event->run(app());

    Queue::assertPushedOn('mail', RecordQueueHeartbeat::class);
});

it('shares the notice with the newsletter admin pages', function () {
    useDatabaseQueue();
    $campaign = autumnIssue();

    $this->actingAs($this->admin)
        ->get(route('admin.newsletter.campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page->where('queueNotice.title', 'Campaigns will not be sent: no queue worker is running'));

    $this->actingAs($this->admin)
        ->get(route('admin.newsletter.campaigns.edit', $campaign))
        ->assertInertia(fn (Assert $page) => $page->whereNot('queueNotice', null));

    $this->actingAs($this->admin)
        ->get(route('admin.newsletter.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('queueNotice', null));
});

it('sends a null notice to the admin pages when all is well', function () {
    config(['queue.default' => 'sync']);

    $this->actingAs($this->admin)
        ->get(route('admin.newsletter.campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page->where('queueNotice', null));
});

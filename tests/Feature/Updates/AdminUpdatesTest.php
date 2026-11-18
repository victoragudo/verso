<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Jobs\RunCoreUpdate;
use App\Verso\Updates\Models\CoreUpdate;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
    $this->admin = User::factory()->admin()->create(['name' => 'Ines Navarro']);
    Http::preventStrayRequests();
    Process::fake();
});

afterEach(function () {
    $this->sandbox->destroy();
});

function knowRelease(string $version, array $overrides = []): void
{
    Http::fake(['api.github.com/*' => Http::response([UpdateSandbox::githubRelease($version, $overrides)])]);
    app(UpdateChecker::class)->check();
}

it('shows the updates page with the versions and the changelog', function () {
    knowRelease('1.2.0', ['body' => "## Highlights\n\n- New **gallery** layouts\n\n<script>alert('x')</script>"]);

    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/updates/index')
            ->where('check.configured', true)
            ->where('check.currentVersion', '0.1.0')
            ->where('check.latest.version', '1.2.0')
            ->where('check.updateAvailable', true)
            ->where('check.latest.notes_html', fn (string $html) => str_contains($html, '<strong>gallery</strong>') && ! str_contains($html, '<script>'))
            ->where('settings.channel', 'stable')
            ->where('settings.automatic', 'off')
            ->has('channels', 2)
            ->has('automaticOptions', 3)
            ->where('databaseBackupProblem', null)
            ->where('signatureRequired', false)
            ->where('activeUpdate', null)
            ->has('updates', 0)
            ->where('pollInterval', config('verso.updates.poll_interval'))
        );

    Http::assertSentCount(1);
});

it('never calls the feed when the page loads', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('check.latest', null)->where('check.checkedAt', null));

    Http::assertNothingSent();
});

it('is only for users who can manage updates', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.updates.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.updates.check'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.updates.store'), ['version' => '1.2.0'])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.updates.settings'), ['channel' => 'beta', 'automatic' => 'minor'])->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.updates.index'))->assertRedirect(route('login'));
});

it('checks the feed on demand', function () {
    Http::fake(['api.github.com/*' => Http::response([UpdateSandbox::githubRelease('1.3.0')])]);

    $this->actingAs($this->admin)
        ->post(route('admin.updates.check'))
        ->assertRedirect(route('admin.updates.index'))
        ->assertInertiaFlash('toast.message', 'Verso 1.3.0 is available.');

    expect(app(UpdateChecker::class)->status()->latest?->version)->toBe('1.3.0');
});

it('shows a failed check as an error toast', function () {
    Http::fake(['api.github.com/*' => Http::response('rate limited', 429)]);

    $this->actingAs($this->admin)
        ->post(route('admin.updates.check'))
        ->assertInertiaFlash('toast.type', 'error');
});

it('starts an update after the response and shows its progress', function () {
    Bus::fake();
    knowRelease('1.2.0');

    $this->actingAs($this->admin)
        ->post(route('admin.updates.store'), ['version' => '1.2.0'])
        ->assertRedirect(route('admin.updates.index'));

    $update = CoreUpdate::query()->sole();

    expect($update->status)->toBe(UpdateStatus::Pending)
        ->and($update->trigger)->toBe(UpdateTrigger::Admin)
        ->and($update->user_id)->toBe($this->admin->id);

    Bus::assertDispatchedAfterResponse(RunCoreUpdate::class, fn (RunCoreUpdate $job) => $job->updateId === $update->id);

    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeUpdate.id', $update->id)
            ->where('activeUpdate.finished', false)
            ->where('activeUpdate.steps.0.key', 'lock')
            ->where('activeUpdate.steps.0.status', 'pending')
            ->where('activeUpdate.steps.0.label', 'Make sure no other update runs')
            ->has('activeUpdate.steps', 13)
            ->where('updates.0.user', 'Ines Navarro')
        );
});

it('sends the update to the queue when running after the response is off', function () {
    Bus::fake();
    config(['verso.updates.run_after_response' => false, 'verso.updates.queue' => 'maintenance']);
    knowRelease('1.2.0');

    $this->actingAs($this->admin)->post(route('admin.updates.store'), ['version' => '1.2.0']);

    Bus::assertDispatched(RunCoreUpdate::class, fn (RunCoreUpdate $job) => $job->queue === 'maintenance');
});

it('runs the whole update in the request cycle with the sync queue', function () {
    $zip = $this->sandbox->package('1.2.0');
    Http::fake([
        'api.github.com/*' => Http::response([UpdateSandbox::githubRelease('1.2.0')]),
        UpdateSandbox::packageUrl('1.2.0').'.sha256' => Http::response(hash('sha256', $zip)),
        UpdateSandbox::packageUrl('1.2.0') => Http::response($zip),
        UpdateSandbox::SITE_URL.'/*' => Http::response('ok'),
    ]);
    app(UpdateChecker::class)->check();

    $this->actingAs($this->admin)->post(route('admin.updates.store'), ['version' => '1.2.0']);

    expect(CoreUpdate::query()->sole()->status)->toBe(UpdateStatus::Completed)
        ->and($this->sandbox->read('VERSION'))->toBe("1.2.0\n");

    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeUpdate.finished', true)
            ->where('activeUpdate.status', 'completed')
            ->where('activeUpdate.steps.12.status', 'done')
            ->where('updates.0.has_backup', true)
        );
});

it('refuses a version that is not the known newest one', function () {
    Bus::fake();
    knowRelease('1.2.0');

    $this->actingAs($this->admin)
        ->post(route('admin.updates.store'), ['version' => '1.1.0'])
        ->assertInertiaFlash('toast.message', 'This release is no longer the newest one. Check for updates again.');

    expect(CoreUpdate::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('needs consent when the database cannot be backed up', function () {
    Bus::fake();
    config(['database.connections.'.UpdateSandbox::CONNECTION.'.database' => ':memory:']);
    knowRelease('1.2.0');

    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertInertia(fn (Assert $page) => $page->where('databaseBackupProblem', 'The SQLite database is not a file on disk.'));

    $this->actingAs($this->admin)
        ->post(route('admin.updates.store'), ['version' => '1.2.0'])
        ->assertInertiaFlash('toast.type', 'error');

    expect(CoreUpdate::query()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->post(route('admin.updates.store'), ['version' => '1.2.0', 'allow_without_database_backup' => true]);

    expect(CoreUpdate::query()->sole()->allow_without_database_backup)->toBeTrue();
});

it('refuses to start while another update runs', function () {
    Bus::fake();
    knowRelease('1.2.0');
    $this->actingAs($this->admin)->post(route('admin.updates.store'), ['version' => '1.2.0']);

    $this->actingAs($this->admin)
        ->post(route('admin.updates.store'), ['version' => '1.2.0'])
        ->assertInertiaFlash('toast.message', 'Another update is already waiting or running.');

    expect(CoreUpdate::query()->count())->toBe(1);
});

it('saves the update settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.updates.settings'), ['channel' => 'beta', 'automatic' => 'security'])
        ->assertRedirect(route('admin.updates.index'));

    expect(app(Settings::class)->get('updates.channel'))->toBe('beta')
        ->and(app(Settings::class)->get('updates.automatic'))->toBe('security');
});

it('validates the update settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.updates.settings'), ['channel' => 'nightly', 'automatic' => 'always'])
        ->assertSessionHasErrors(['channel', 'automatic']);
});

it('shows a dashboard notice to admins when a release is available', function () {
    knowRelease('1.2.0', ['name' => 'Verso 1.2.0 [security]']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notices.0.key', 'core-update')
            ->where('notices.0.icon', 'updates')
            ->where('notices.0.title', 'Verso 1.2.0 is available')
            ->where('notices.0.tone', 'warning')
            ->where('notices.0.href', '/admin/updates')
        );

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notices', fn ($notices) => ! collect($notices)->contains('key', 'core-update')));
});

it('shows no dashboard notice when the site is up to date', function () {
    knowRelease('0.1.0');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notices', fn ($notices) => ! collect($notices)->contains('key', 'core-update')));
});

it('adds the Updates page to the admin nav for admins', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.updates.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains(fn ($item) => $item['key'] === 'updates' && $item['icon'] === 'updates')));
});

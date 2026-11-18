<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Actions\ExportSubmissions;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\Models\Submission;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    $this->editor = User::factory()->editor()->create(['name' => 'Teresa Gomes']);
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Saraiva']);
    $this->form = bakeryContactForm();
});

function bakeryMessage(array $data = [], string $status = 'new', array $attributes = []): Submission
{
    return Submission::query()->create([
        'form_id' => test()->form->id,
        'status' => $status,
        'data' => [
            'name' => 'Duarte Pinto',
            'email' => 'duarte.pinto@example.pt',
            'topic' => 'Catering',
            'message' => 'Lunch for 30 people at our office in Alvalade, next Friday.',
            'consent' => true,
            ...$data,
        ],
        'email' => $data['email'] ?? 'duarte.pinto@example.pt',
        ...$attributes,
    ]);
}

it('lists the inbox without spam, with counts per status', function () {
    bakeryMessage();
    bakeryMessage(['name' => 'Leonor Faria', 'email' => 'leonor@example.pt'], 'read');
    bakeryMessage(['message' => 'Cheap backlinks'], 'spam');

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/forms/submissions', false)
            ->has('submissions.data', 2)
            ->where('submissions.data.0.name', 'Leonor Faria')
            ->where('submissions.data.1.preview', 'Lunch for 30 people at our office in Alvalade, next Friday.')
            ->where('counts', ['inbox' => 2, 'new' => 1, 'read' => 1, 'spam' => 1, 'archived' => 0])
            ->where('urls.export', null)
        );
});

it('filters by form, status and search text', function () {
    bakeryMessage();
    bakeryMessage(['name' => 'Leonor Faria', 'email' => 'leonor@example.pt', 'message' => 'A birthday cake with strawberries.']);
    bakeryMessage([], 'spam');

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.index', ['form' => $this->form->id, 'status' => 'spam']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('filters.status', 'spam')
            ->where('urls.export', route('admin.forms.submissions.export', ['form' => $this->form->id, 'status' => 'spam']))
        );

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.index', ['search' => 'STRAWBERRIES']))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1)->where('submissions.data.0.email', 'leonor@example.pt'));
});

it('shows only messages sent since a day in the site time zone', function () {
    app(Settings::class)->set('site.timezone', 'Europe/Lisbon');
    bakeryMessage(['name' => 'Early bird'])->forceFill(['created_at' => '2026-07-09 22:30:00'])->save();
    bakeryMessage(['name' => 'Night owl'])->forceFill(['created_at' => '2026-07-09 23:30:00'])->save();

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.index', ['since' => '2026-07-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.name', 'Night owl')
            ->where('filters.since', '2026-07-10')
        );

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.index', ['since' => 'yesterday']))
        ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 2)->where('filters.since', ''));
});

it('opens a message and marks it as read', function () {
    $submission = bakeryMessage();

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/forms/submission', false)
            ->where('submission.fields.0', ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'value' => 'Duarte Pinto', 'file' => null])
            ->where('submission.fields.4.value', 'Yes')
        );

    expect($submission->fresh())
        ->status->toBe(SubmissionStatus::Read)
        ->read_at->not->toBeNull();
});

it('moves a message to another status', function () {
    $submission = bakeryMessage();

    $this->actingAs($this->editor)->put(route('admin.forms.submissions.status', $submission), ['status' => 'archived'])->assertSessionHasNoErrors();
    expect($submission->fresh()->status)->toBe(SubmissionStatus::Archived);

    $this->actingAs($this->editor)->put(route('admin.forms.submissions.status', $submission), ['status' => 'new'])->assertSessionHasNoErrors();
    expect($submission->fresh())->status->toBe(SubmissionStatus::New)->read_at->toBeNull();

    $this->actingAs($this->editor)->put(route('admin.forms.submissions.status', $submission), ['status' => 'deleted'])->assertSessionHasErrors(['status']);
});

it('changes or deletes many messages at once', function () {
    $first = bakeryMessage();
    $second = bakeryMessage();
    $third = bakeryMessage();

    $this->actingAs($this->editor)->post(route('admin.forms.submissions.bulk'), ['ids' => [$first->id, $second->id], 'action' => 'spam'])->assertSessionHasNoErrors();
    expect(Submission::query()->where('status', 'spam')->count())->toBe(2);

    $this->actingAs($this->editor)->post(route('admin.forms.submissions.bulk'), ['ids' => [$first->id, $third->id], 'action' => 'delete'])->assertSessionHasNoErrors();
    expect(Submission::query()->pluck('id')->all())->toBe([$second->id]);
});

it('deletes one message with its uploaded file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('forms/1/plan.pdf', '%PDF-1.4');
    $submission = bakeryMessage(attributes: ['files' => ['plan' => ['path' => 'forms/1/plan.pdf', 'name' => 'plan.pdf', 'size' => 8, 'mime' => 'application/pdf']]]);

    $this->actingAs($this->editor)
        ->delete(route('admin.forms.submissions.destroy', $submission))
        ->assertRedirect(route('admin.forms.submissions.index', ['form' => $this->form->id]));

    expect(Submission::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('forms/1/plan.pdf');
});

it('erases everything one person sent for a privacy request', function () {
    bakeryMessage();
    bakeryMessage(['message' => 'A second question about gluten free bread.']);
    bakeryMessage(['name' => 'Leonor Faria', 'email' => 'leonor@example.pt']);

    $this->actingAs($this->editor)
        ->post(route('admin.forms.submissions.erase'), ['email' => 'Duarte.Pinto@example.pt'])
        ->assertSessionHasNoErrors();

    expect(Submission::query()->pluck('email')->all())->toBe(['leonor@example.pt']);
});

it('exports the messages of a form as CSV with one column per field', function () {
    bakeryMessage();
    bakeryMessage(['name' => '=HYPERLINK("http://evil.example")', 'email' => 'mallory@example.pt'], 'spam');

    $csv = $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.export', ['form' => $this->form->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

    expect($rows[0])->toBe(['id', 'status', 'submitted_at', 'Your name', 'Email', 'Topic', 'Message', 'I agree that you store my message.'])
        ->and($rows[1][3])->toBe('Duarte Pinto')
        ->and($rows[1][7])->toBe('Yes')
        ->and($rows[2][3])->toStartWith("'=HYPERLINK");

    $spamOnly = $this->actingAs($this->editor)->get(route('admin.forms.submissions.export', ['form' => $this->form->id, 'status' => 'spam']))->streamedContent();
    expect(substr_count(trim($spamOnly), "\n"))->toBe(1);
});

it('quotes cells that spreadsheets would run as formulas', function (string $value, string $expected) {
    expect(ExportSubmissions::safeCell($value))->toBe($expected);
})->with([
    ['=SUM(A1:A9)', "'=SUM(A1:A9)"],
    ['+351 912 345 678', "'+351 912 345 678"],
    ['@home', "'@home"],
    ['Plain text', 'Plain text'],
    ['', ''],
]);

it('gives the admin uploaded files as a download', function () {
    Storage::fake('local');
    Storage::disk('local')->put('forms/1/a1b2.pdf', '%PDF-1.4 sketch');
    $submission = bakeryMessage(attributes: ['files' => ['sketch' => ['path' => 'forms/1/a1b2.pdf', 'name' => 'cake-sketch.pdf', 'size' => 15, 'mime' => 'application/pdf']]]);

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.file', ['submission' => $submission->id, 'field' => 'sketch']))
        ->assertOk()
        ->assertDownload('cake-sketch.pdf');

    $this->actingAs($this->editor)
        ->get(route('admin.forms.submissions.file', ['submission' => $submission->id, 'field' => 'message']))
        ->assertNotFound();
});

it('deletes messages older than the retention period', function () {
    $old = bakeryMessage();
    $old->forceFill(['created_at' => now()->subDays(400)])->save();
    $recent = bakeryMessage();

    $this->artisan('forms:prune')->expectsOutput('Deleted 1 old submissions.')->assertSuccessful();

    expect(Submission::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('keeps everything when the retention period is 0 days', function () {
    app(Settings::class)->set('forms.retention_days', 0);
    $old = bakeryMessage();
    $old->forceFill(['created_at' => now()->subYears(5)])->save();

    $this->artisan('forms:prune')->assertSuccessful();

    expect(Submission::query()->count())->toBe(1);
});

it('schedules the retention job', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($event) => $event->description === 'forms:prune' && $event->expression === '15 3 * * *'))->toBeTrue();
});

it('shows and saves the shared settings without sending the secret back', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.forms.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/forms/settings', false)
            ->where('settings.retention_days', 365)
            ->where('settings.captcha_provider', 'none')
            ->where('settings.has_captcha_secret', false)
        );

    $this->actingAs($this->admin)->put(route('admin.forms.settings.update'), [
        'retention_days' => 90, 'max_links' => 2, 'min_seconds' => 4,
        'blocked_words' => "Casino\nfree money, crypto",
        'captcha_provider' => 'hcaptcha', 'captcha_site_key' => '10000000-ffff-ffff-ffff-000000000001', 'captcha_secret' => '0x0000000000000000000000000000000000000000',
    ])->assertSessionHasNoErrors();

    $stored = app(Settings::class)->get('forms.captcha_secret');
    expect($stored)->not->toContain('0x00000')
        ->and(Crypt::decryptString($stored))->toBe('0x0000000000000000000000000000000000000000');

    $this->actingAs($this->admin)
        ->get(route('admin.forms.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.retention_days', 90)
            ->where('settings.blocked_words', "casino\nfree money\ncrypto")
            ->where('settings.captcha_secret', '')
            ->where('settings.has_captcha_secret', true)
        );

    // An empty secret keeps the saved one.
    $this->actingAs($this->admin)->put(route('admin.forms.settings.update'), [
        'retention_days' => 90, 'max_links' => 2, 'min_seconds' => 4, 'blocked_words' => '',
        'captcha_provider' => 'hcaptcha', 'captcha_site_key' => '10000000-ffff-ffff-ffff-000000000001', 'captcha_secret' => '',
    ])->assertSessionHasNoErrors();

    expect(Crypt::decryptString(app(Settings::class)->get('forms.captcha_secret')))->toBe('0x0000000000000000000000000000000000000000');
});

it('asks for both captcha keys', function () {
    $this->actingAs($this->admin)->put(route('admin.forms.settings.update'), [
        'retention_days' => 90, 'max_links' => 2, 'min_seconds' => 4, 'blocked_words' => '',
        'captcha_provider' => 'turnstile', 'captcha_site_key' => '', 'captcha_secret' => '',
    ])->assertSessionHasErrors(['captcha_site_key']);

    $this->actingAs($this->admin)->put(route('admin.forms.settings.update'), [
        'retention_days' => 90, 'max_links' => 2, 'min_seconds' => 4, 'blocked_words' => '',
        'captcha_provider' => 'turnstile', 'captcha_site_key' => '0x4AAAA', 'captcha_secret' => '',
    ])->assertSessionHasErrors(['captcha_secret']);
});

it('keeps the settings page for people who manage settings', function () {
    $this->actingAs($this->editor)->get(route('admin.forms.settings.edit'))->assertForbidden();
    $this->actingAs($this->editor)->put(route('admin.forms.settings.update'), ['retention_days' => 1])->assertForbidden();
});

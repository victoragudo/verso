<?php

use App\Models\User;
use App\Verso\Importer\Enums\ImportStatus;
use App\Verso\Importer\Jobs\RunWordPressImport;
use App\Verso\Importer\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create(['name' => 'Beatriz Sousa']);
});

function exportUpload(string $name = 'harbourlight.WordPress.2026-11-16.xml'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, file_get_contents(base_path('tests/Fixtures/wordpress-export.xml')));
}

it('shows the upload page with recent imports', function () {
    Import::factory()->completed()->create(['user_id' => $this->admin->id, 'original_name' => 'oldblog.xml']);
    Import::factory()->failed()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.import.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/importer/index')
            ->has('imports', 2)
            ->where('imports.0.status', 'failed')
            ->where('imports.1.original_name', 'oldblog.xml')
            ->where('imports.1.user', 'Beatriz Sousa')
            ->where('imports.1.percent', 100)
            ->where('maxUploadKb', config('verso.importer.max_upload_kb'))
        );
});

it('lists only the most recent imports', function () {
    config(['verso.importer.recent_limit' => 3]);
    Import::factory()->count(5)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.import.index'))
        ->assertInertia(fn (Assert $page) => $page->has('imports', 3));
});

it('uploads a file and queues the import', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)->post(route('admin.import.store'), ['file' => exportUpload()]);

    $import = Import::query()->sole();
    $response->assertRedirect(route('admin.import.show', $import));

    expect($import->status)->toBe(ImportStatus::Pending)
        ->and($import->original_name)->toBe('harbourlight.WordPress.2026-11-16.xml')
        ->and($import->user_id)->toBe($this->admin->id)
        ->and($import->source)->toBe('wordpress');

    Storage::disk('local')->assertExists($import->path);
    Queue::assertPushed(RunWordPressImport::class, fn (RunWordPressImport $job) => $job->importId === $import->id);
});

it('runs the import right away with the sync queue', function () {
    config(['verso.importer.download_attachments' => false]);

    $this->actingAs($this->admin)->post(route('admin.import.store'), ['file' => exportUpload()]);

    expect(Import::query()->sole()->status)->toBe(ImportStatus::Completed);
});

it('validates the upload', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.import.store'), [])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->admin)
        ->post(route('admin.import.store'), ['file' => UploadedFile::fake()->create('export.zip', 20)])
        ->assertSessionHasErrors(['file' => 'Upload the .xml file from Tools > Export in WordPress.']);

    Queue::assertNothingPushed();
});

it('refuses files over the size limit', function () {
    Queue::fake();
    config(['verso.importer.max_upload_kb' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.import.store'), ['file' => exportUpload()])
        ->assertSessionHasErrors('file');
});

it('shows the status of an import', function () {
    $import = Import::factory()->completed()->create(['user_id' => $this->admin->id, 'total' => 12]);

    $this->actingAs($this->admin)
        ->get(route('admin.import.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/importer/show')
            ->where('import.id', $import->id)
            ->where('import.status', 'completed')
            ->where('import.status_label', 'Completed')
            ->where('import.duration', 120)
            ->where('import.report.rows.1.created', 8)
            ->has('import.report.messages', 1)
            ->where('pollInterval', config('verso.importer.poll_interval'))
        );
});

it('shows the error of a failed import', function () {
    $import = Import::factory()->failed()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.import.show', $import))
        ->assertInertia(fn (Assert $page) => $page->where('import.error', 'The file is not a WordPress export.'));
});

it('returns 404 for unknown imports', function () {
    $this->actingAs($this->admin)->get('/admin/import/4040')->assertNotFound();
});

it('is only for users who can import content', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $import = Import::factory()->create();

    $this->actingAs($user)->get(route('admin.import.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.import.store'), ['file' => exportUpload()])->assertForbidden();
    $this->actingAs($user)->get(route('admin.import.show', $import))->assertForbidden();
})->with(['editor', 'author', 'contributor']);

it('sends guests to the login page', function () {
    $this->get(route('admin.import.index'))->assertRedirect(route('login'));
});

it('adds an Import link to the admin nav for admins only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'importer')));

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => ! collect($nav)->contains('key', 'importer')));
});

<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\WordPressImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['verso.importer.download_retry_delay' => 0]);
    Storage::fake('public');
    Http::fake([
        '*.png' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAIAAAA2iEnWAAAAEklEQVR4nGP4z8DAwMDAwMDAAAAPAQH/0tBpZwAAAABJRU5ErkJggg=='), 200),
        '*' => Http::response('Gone', 410),
    ]);

    $this->admin = User::factory()->admin()->create();
});

function importFixture(User $user): ImportReport
{
    return app(WordPressImporter::class)->import(base_path('tests/Fixtures/wordpress-export.xml'), $user);
}

// Swap the real comments table for a custom one, like a plugin might use.
function replaceCommentsTable(Closure $columns): void
{
    Schema::drop('comments');
    Schema::create('comments', $columns);
}

it('imports approved comments with replies and authors', function () {
    $report = importFixture($this->admin);

    $lisbon = Entry::query()->where('slug', 'a-weekend-in-lisbon')->firstOrFail();
    $comments = DB::table('comments')->orderBy('id')->get();
    $maria = User::query()->where('email', 'maria.lopes@harbourlight.example')->firstOrFail();

    expect($comments)->toHaveCount(2)
        ->and($comments[0]->entry_id)->toBe($lisbon->id)
        ->and($comments[0]->author_name)->toBe('Inês Carvalho')
        ->and($comments[0]->author_email)->toBe('ines.carvalho@correio.example')
        ->and($comments[0]->status)->toBe('approved')
        ->and($comments[0]->created_at)->toBe('2024-03-19 21:14:02')
        ->and($comments[0]->user_id)->toBeNull()
        ->and($comments[1]->parent_id)->toBe($comments[0]->id)
        ->and($comments[1]->user_id)->toBe($maria->id)
        ->and($report->count('comments', ImportReport::CREATED))->toBe(2)
        ->and($report->count('comments', ImportReport::SKIPPED))->toBe(2)
        ->and($report->messages())->not->toContain('Comments were not imported because the comments table does not exist yet.');
});

it('does not copy comments on a second run', function () {
    importFixture($this->admin);
    $report = importFixture($this->admin);

    expect(DB::table('comments')->count())->toBe(2)
        ->and($report->count('comments', ImportReport::CREATED))->toBe(0);
});

it('works with a polymorphic comments table', function () {
    replaceCommentsTable(function (Blueprint $table) {
        $table->id();
        $table->morphs('commentable');
        $table->string('author_name');
        $table->text('body');
        $table->timestamps();
    });

    importFixture($this->admin);

    $comment = DB::table('comments')->first();

    expect($comment->commentable_type)->toBe((new Entry)->getMorphClass())
        ->and($comment->body)->toBe('Next time try the ginjinha bar near Rossio!');
});

it('skips comments when the table cannot link them to entries', function () {
    replaceCommentsTable(function (Blueprint $table) {
        $table->id();
        $table->text('content');
        $table->timestamps();
    });

    $report = importFixture($this->admin);

    expect(DB::table('comments')->count())->toBe(0)
        ->and($report->count('comments', ImportReport::SKIPPED))->toBe(4);
});

it('adds media rows when the media table exists', function () {
    importFixture($this->admin);

    $media = DB::table('media')->first();
    $maria = User::query()->where('email', 'maria.lopes@harbourlight.example')->firstOrFail();
    $lisbon = Entry::query()->where('slug', 'a-weekend-in-lisbon')->firstOrFail();
    $gallery = collect($lisbon->blocks)->firstWhere('type', 'gallery');

    expect(DB::table('media')->count())->toBe(1)
        ->and($media->path)->toBe('uploads/2024/03/pasteis-de-nata.png')
        ->and($media->disk)->toBe('public')
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->width)->toBe(2)
        ->and($media->height)->toBe(3)
        ->and($media->alt)->toBe('A tray of warm pastéis de nata')
        ->and($media->uploaded_by)->toBe($maria->id)
        ->and(json_decode($media->meta, true))->toMatchArray(['wordpress' => ['id' => '102', 'url' => 'https://harbourlight.example/wp-content/uploads/2024/03/pasteis-de-nata.png']])
        ->and($gallery['attrs']['mediaIds'])->toBe([$media->id]);
});

it('does not add media rows twice', function () {
    importFixture($this->admin);
    importFixture($this->admin);

    expect(DB::table('media')->count())->toBe(1);
});

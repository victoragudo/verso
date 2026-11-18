<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->editor = User::factory()->editor()->create(['name' => 'Ines Duarte']);
});

describe('library page', function () {
    it('lists media newest first', function () {
        Media::factory()->create(['filename' => 'old-market.jpg', 'created_at' => now()->subWeek()]);
        Media::factory()->create(['filename' => 'new-harbour.jpg', 'uploaded_by' => $this->editor->id]);

        $this->actingAs($this->editor)
            ->get(route('admin.media.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/media/index')
                ->has('media.data', 2)
                ->where('media.data.0.filename', 'new-harbour.jpg')
                ->where('media.data.0.uploaded_by', 'Ines Duarte')
                ->where('media.data.0.type', 'image')
                ->where('filters', ['search' => '', 'type' => null])
                ->has('types', 4)
                ->where('upload.max_kb', 10240)
            );
    });

    it('searches and filters by type', function () {
        Media::factory()->create(['filename' => 'garden-tomatoes.jpg', 'alt' => 'Tomatoes']);
        Media::factory()->video()->create(['filename' => 'garden-tour.mp4']);
        Media::factory()->document()->create(['filename' => 'garden-plan.pdf']);
        Media::factory()->create(['filename' => 'bike.jpg', 'alt' => 'Bike']);

        $this->actingAs($this->editor)
            ->get(route('admin.media.index', ['search' => 'garden', 'type' => 'image']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('media.data', 1)
                ->where('media.data.0.filename', 'garden-tomatoes.jpg')
                ->where('filters', ['search' => 'garden', 'type' => 'image'])
            );

        $this->actingAs($this->editor)
            ->get(route('admin.media.index', ['type' => 'document']))
            ->assertInertia(fn (Assert $page) => $page->has('media.data', 1)->where('media.data.0.filename', 'garden-plan.pdf'));
    });

    it('pages the library', function () {
        config(['verso.media.per_page' => 3]);
        Media::factory()->count(5)->create();

        $this->actingAs($this->editor)
            ->get(route('admin.media.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('media.data', 2)->where('media.total', 5));
    });

    it('redirects guests and forbids contributors', function () {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->contributor()->create())
            ->get(route('admin.media.index'))
            ->assertForbidden();
    });
});

describe('upload', function () {
    it('uploads a file from the library page', function () {
        $this->actingAs($this->editor)
            ->from(route('admin.media.index'))
            ->post(route('admin.media.store'), ['file' => UploadedFile::fake()->image('olive-grove.jpg', 800, 600), 'alt' => 'Olive trees'])
            ->assertRedirect(route('admin.media.index'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'File uploaded.']);

        $media = Media::query()->sole();
        expect($media->alt)->toBe('Olive trees')
            ->and($media->uploaded_by)->toBe($this->editor->id)
            ->and($media->width)->toBe(800);
        Storage::disk('public')->assertExists($media->path);
    });

    it('returns json for the picker', function () {
        $this->actingAs($this->editor)
            ->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('olive-grove.png', 400, 300)])
            ->assertCreated()
            ->assertJsonPath('filename', 'olive-grove.png')
            ->assertJsonPath('mime_type', 'image/png')
            ->assertJsonPath('width', 400)
            ->assertJsonPath('can.delete', true);
    });

    it('rejects a missing file and a bad type', function () {
        $this->actingAs($this->editor)->postJson(route('admin.media.store'), [])->assertJsonValidationErrors('file');

        $this->actingAs($this->editor)
            ->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload')])
            ->assertJsonValidationErrors('file');

        expect(Media::query()->count())->toBe(0);
    });

    it('rejects files over the size limit', function () {
        config(['verso.media.max_upload_kb' => 50]);

        $this->actingAs($this->editor)
            ->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('concert.mp4', 51, 'video/mp4')])
            ->assertJsonValidationErrors('file');
    });

    it('forbids users without the upload capability', function () {
        $this->actingAs(User::factory()->contributor()->create())
            ->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('olive-grove.jpg')])
            ->assertForbidden();
    });
});

describe('details and editing', function () {
    it('shows a file with the entries that use it', function () {
        $media = Media::factory()->create();
        $entry = Entry::factory()->create(['title' => 'Our First Month with Solar Panels', 'meta' => ['featured_media_id' => $media->id]]);

        $this->actingAs($this->editor)
            ->getJson(route('admin.media.show', $media))
            ->assertOk()
            ->assertJsonPath('id', $media->id)
            ->assertJsonPath('usage.0.title', 'Our First Month with Solar Panels')
            ->assertJsonPath('usage.0.edit_url', '/admin/entries/post/'.$entry->id.'/edit');
    });

    it('saves alt text and caption', function () {
        $media = Media::factory()->create();

        $this->actingAs($this->editor)
            ->put(route('admin.media.update', $media), ['alt' => 'Snow on the peaks', 'caption' => 'Taken near Benasque'])
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'File details saved.']);

        expect($media->fresh())->alt->toBe('Snow on the peaks')->caption->toBe('Taken near Benasque');
    });

    it('saves through json and validates length', function () {
        $media = Media::factory()->create();

        $this->actingAs($this->editor)
            ->putJson(route('admin.media.update', $media), ['alt' => 'Snow on the peaks'])
            ->assertOk()
            ->assertJsonPath('alt', 'Snow on the peaks');

        $this->actingAs($this->editor)
            ->putJson(route('admin.media.update', $media), ['alt' => str_repeat('a', 256)])
            ->assertJsonValidationErrors('alt');
    });

    it('lets authors edit only their own files', function () {
        $author = User::factory()->author()->create();
        $own = Media::factory()->create(['uploaded_by' => $author->id]);
        $other = Media::factory()->create();

        $this->actingAs($author)->put(route('admin.media.update', $own), ['alt' => 'Mine'])->assertRedirect();
        $this->actingAs($author)->put(route('admin.media.update', $other), ['alt' => 'Not mine'])->assertForbidden();
    });

    it('returns 404 for unknown files', function () {
        $this->actingAs($this->editor)->getJson('/admin/media/999')->assertNotFound();
    });
});

describe('delete', function () {
    it('deletes an unused file', function () {
        $media = Media::factory()->create();

        $this->actingAs($this->editor)
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'File deleted.']);

        expect($media->fresh())->toBeNull();
    });

    it('keeps a file that entries use', function () {
        $media = Media::factory()->create();
        Entry::factory()->create(['blocks' => [['id' => 'b_1', 'type' => 'gallery', 'attrs' => ['mediaIds' => [$media->id]], 'children' => []]]]);

        $this->actingAs($this->editor)
            ->delete(route('admin.media.destroy', $media))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This file is used in 1 entry. Remove it there first.']);

        $this->actingAs($this->editor)
            ->deleteJson(route('admin.media.destroy', $media))
            ->assertStatus(409)
            ->assertJsonPath('usage', 1);

        expect($media->fresh())->not->toBeNull();
    });

    it('deletes a used file when forced', function () {
        $media = Media::factory()->create();
        Entry::factory()->create(['meta' => ['featured_media_id' => $media->id]]);

        $this->actingAs($this->editor)
            ->deleteJson(route('admin.media.destroy', $media), ['force' => true])
            ->assertNoContent();

        expect($media->fresh())->toBeNull();
    });

    it('forbids authors from deleting files of others', function () {
        $media = Media::factory()->create();

        $this->actingAs(User::factory()->author()->create())
            ->delete(route('admin.media.destroy', $media))
            ->assertForbidden();
    });
});

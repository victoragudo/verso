<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

// A writer who may only write pages: no posts.
beforeEach(function () {
    $this->pageWriter = userWith(['entries.page.create', 'entries.page.edit_own', 'entries.page.delete_own'], 'Leonor Brito');
    $this->colleague = User::factory()->editor()->create(['name' => 'Gonçalo Pires']);
});

it('opens the list and editor of the allowed content type only', function () {
    $this->actingAs($this->pageWriter)->get(route('admin.entries.index', 'page'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.create', true));
    $this->actingAs($this->pageWriter)->get(route('admin.entries.create', 'page'))->assertOk();
    $this->actingAs($this->pageWriter)->get(route('admin.entries.index', 'post'))->assertForbidden();
    $this->actingAs($this->pageWriter)->get(route('admin.entries.create', 'post'))->assertForbidden();
});

it('saves own drafts of the type but never publishes without the publish capability', function () {
    $this->actingAs($this->pageWriter)
        ->post(route('admin.entries.store', 'page'), ['title' => 'Opening hours', 'status' => 'published', 'blocks' => []])
        ->assertSessionHasErrors('status');

    $this->actingAs($this->pageWriter)
        ->post(route('admin.entries.store', 'page'), ['title' => 'Opening hours', 'status' => 'draft', 'blocks' => []])
        ->assertRedirect();

    expect(Entry::query()->where('title', 'Opening hours')->value('status'))->toBe(EntryStatus::Draft);
});

it('publishes per content type', function () {
    $publisher = userWith(['entries.page.create', 'entries.page.edit_own', 'entries.page.publish']);
    $page = Entry::factory()->page()->by($publisher)->create();
    $post = Entry::factory()->by($publisher)->create();

    expect($publisher->can('publish', $page))->toBeTrue()
        ->and($publisher->can('publish', $post))->toBeFalse()
        ->and($publisher->can('publish', [Entry::class, 'page']))->toBeTrue();
});

it('edits and deletes entries of others only with the "others" capabilities', function () {
    $theirs = Entry::factory()->page()->by($this->colleague)->create();
    $mine = Entry::factory()->page()->by($this->pageWriter)->create();

    expect($this->pageWriter->can('update', $theirs))->toBeFalse()
        ->and($this->pageWriter->can('delete', $theirs))->toBeFalse()
        ->and($this->pageWriter->can('update', $mine))->toBeTrue()
        ->and($this->pageWriter->can('delete', $mine))->toBeTrue();

    $chief = userWith(['entries.page.edit_others', 'entries.page.delete_others']);

    expect($chief->can('update', $theirs))->toBeTrue()
        ->and($chief->can('delete', $theirs))->toBeTrue()
        ->and($chief->can('update', Entry::factory()->by($this->colleague)->create()))->toBeFalse();
});

it('hides unpublished entries of others without read_private', function () {
    Entry::factory()->page()->published()->by($this->colleague)->create(['title' => 'About the bakery']);
    Entry::factory()->page()->by($this->colleague)->create(['title' => 'Secret recipe draft']);
    Entry::factory()->page()->by($this->pageWriter)->create(['title' => 'My own draft']);

    $this->actingAs($this->pageWriter)
        ->get(route('admin.entries.index', 'page'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 2)
            ->where('counts.all', 2)
            ->where('entries.data', fn ($rows) => ! collect($rows)->contains('title', 'Secret recipe draft')));

    $reader = userWith(['entries.page.edit_own', 'entries.page.read_private']);

    $this->actingAs($reader)
        ->get(route('admin.entries.index', 'page'))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 3));
});

it('keeps the old capabilities working for every content type', function () {
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.entries.index', 'post'))->assertOk();
    $this->actingAs($author)->get(route('admin.entries.index', 'page'))->assertOk();
    expect($author->can('publish', [Entry::class, 'page']))->toBeTrue();
});

it('lets editors change any media, and others only their own files', function () {
    $uploader = userWith(['upload_files']);
    $theirs = Media::factory()->create(['uploaded_by' => $this->colleague->id]);
    $mine = Media::factory()->create(['uploaded_by' => $uploader->id]);

    expect($uploader->can('update', $mine))->toBeTrue()
        ->and($uploader->can('delete', $theirs))->toBeFalse()
        ->and($this->colleague->can('delete', $mine))->toBeTrue()
        ->and(userWith(['upload_files', 'edit_others_media'])->can('delete', $theirs))->toBeTrue();
});

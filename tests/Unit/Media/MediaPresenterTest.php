<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->presenter = app(MediaPresenter::class);
});

it('turns an image into an array with a square thumbnail', function () {
    $user = User::factory()->editor()->create(['name' => 'Marta Soto']);
    $media = Media::factory()->create(['path' => '2026/11/market.jpg', 'filename' => 'market.jpg', 'uploaded_by' => $user->id]);

    $item = $this->presenter->item($media, $user);

    expect($item)->toMatchArray([
        'id' => $media->id,
        'filename' => 'market.jpg',
        'type' => 'image',
        'uploaded_by' => 'Marta Soto',
        'can' => ['update' => true, 'delete' => true],
    ])
        ->and($item['thumbnail_url'])->toContain('w=400')->toContain('h=400')->toContain('fit=crop');
});

it('has no thumbnail for documents', function () {
    $item = $this->presenter->item(Media::factory()->document()->create());

    expect($item['thumbnail_url'])->toBeNull()
        ->and($item['type'])->toBe('document')
        ->and($item['can'])->toBe(['update' => false, 'delete' => false]);
});

it('lists the entries that use a file', function () {
    $media = Media::factory()->create();
    $entry = Entry::factory()->create(['title' => 'Cooking Paella for Twelve People']);

    $detail = $this->presenter->detail($media, collect([$entry]));

    expect($detail['usage'])->toBe([[
        'id' => $entry->id,
        'title' => 'Cooking Paella for Twelve People',
        'type' => 'post',
        'trashed' => false,
        'edit_url' => '/admin/entries/post/'.$entry->id.'/edit',
    ]]);
});

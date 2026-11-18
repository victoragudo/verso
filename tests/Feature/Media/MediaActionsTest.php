<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Media\Actions\DeleteMedia;
use App\Verso\Media\Actions\UpdateMedia;
use App\Verso\Media\Exceptions\MediaInUseException;
use App\Verso\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('updates alt text and caption', function () {
    $media = Media::factory()->create(['alt' => null, 'caption' => null]);

    app(UpdateMedia::class)->handle($media, ['alt' => '  Bread cooling on a rack ', 'caption' => 'Our Sunday loaf']);

    expect($media->fresh())
        ->alt->toBe('Bread cooling on a rack')
        ->caption->toBe('Our Sunday loaf');
});

it('stores empty text as null and leaves missing fields alone', function () {
    $media = Media::factory()->create(['alt' => 'Old alt', 'caption' => 'Old caption']);

    app(UpdateMedia::class)->handle($media, ['alt' => '']);

    expect($media->fresh())->alt->toBeNull()->caption->toBe('Old caption');
});

it('deletes an unused file from disk and database', function () {
    Storage::disk('public')->putFileAs('2026/11', UploadedFile::fake()->image('harbour.jpg'), 'harbour.jpg');
    $media = Media::factory()->create(['path' => '2026/11/harbour.jpg']);

    app(DeleteMedia::class)->handle($media);

    expect(Media::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing('2026/11/harbour.jpg');
});

it('removes cached sizes of a deleted image', function () {
    Storage::disk('public')->putFileAs('2026/11', UploadedFile::fake()->image('harbour.jpg', 400, 300), 'harbour.jpg');
    $media = Media::factory()->create(['path' => '2026/11/harbour.jpg']);
    $this->get($media->imageUrl(['w' => 200]))->assertOk();
    expect(Storage::disk('local')->allFiles('glide-cache'))->not->toBeEmpty();

    app(DeleteMedia::class)->handle($media);

    expect(Storage::disk('local')->allFiles('glide-cache'))->toBeEmpty();
});

it('refuses to delete a file that an entry uses', function () {
    $media = Media::factory()->create();
    Entry::factory()->create(['blocks' => [['id' => 'b_1', 'type' => 'image', 'attrs' => ['mediaId' => $media->id], 'children' => []]]]);

    try {
        app(DeleteMedia::class)->handle($media);
        $this->fail('The file was deleted.');
    } catch (MediaInUseException $exception) {
        expect($exception->getMessage())->toBe('This file is used in 1 entry.')
            ->and($exception->entries)->toHaveCount(1);
    }

    expect($media->fresh())->not->toBeNull();
});

it('deletes a used file when forced', function () {
    $media = Media::factory()->create();
    Entry::factory()->count(2)->create(['meta' => ['featured_media_id' => $media->id]]);

    app(DeleteMedia::class)->handle($media, force: true);

    expect($media->fresh())->toBeNull();
});

it('words the message for many entries', function () {
    $exception = new MediaInUseException(Entry::factory()->count(3)->make());

    expect($exception->getMessage())->toBe('This file is used in 3 entries.');
});

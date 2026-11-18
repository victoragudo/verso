<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// The media table belongs to the Media module. Create it here when it is not there yet.
function ensureMediaTable(): void
{
    if (Schema::hasTable('media')) {
        return;
    }

    Schema::create('media', function (Blueprint $table) {
        $table->id();
        $table->string('disk');
        $table->string('path');
        $table->string('filename');
        $table->string('mime_type');
        $table->unsignedBigInteger('size');
        $table->unsignedInteger('width')->nullable();
        $table->unsignedInteger('height')->nullable();
        $table->string('alt')->nullable();
        $table->text('caption')->nullable();
        $table->foreignId('uploaded_by')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
}

function insertMedia(array $attributes): int
{
    return (int) DB::table('media')->insertGetId(array_merge([
        'disk' => 'public',
        'size' => 245000,
        'width' => null,
        'height' => null,
        'alt' => null,
        'caption' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

beforeEach(function () {
    Storage::fake('public');
    ensureMediaTable();
});

it('lists media, newest first', function () {
    insertMedia(['path' => 'uploads/2026/11/harbor.jpg', 'filename' => 'harbor.jpg', 'mime_type' => 'image/jpeg', 'width' => 1600, 'height' => 900, 'alt' => 'Fishing boats in the harbor']);
    $video = insertMedia(['path' => 'uploads/2026/11/tram.mp4', 'filename' => 'tram.mp4', 'mime_type' => 'video/mp4']);

    $this->getJson('/api/v1/media')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $video)
        ->assertJsonPath('data.1.alt', 'Fishing boats in the harbor')
        ->assertJsonPath('data.1.width', 1600)
        ->assertJsonPath('data.1.url', Storage::disk('public')->url('uploads/2026/11/harbor.jpg'))
        ->assertJsonPath('meta.total', 2);
});

it('filters media by type', function () {
    insertMedia(['path' => 'uploads/harbor.jpg', 'filename' => 'harbor.jpg', 'mime_type' => 'image/jpeg']);
    insertMedia(['path' => 'uploads/menu.pdf', 'filename' => 'menu.pdf', 'mime_type' => 'application/pdf']);

    $this->getJson('/api/v1/media?type=image')->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'harbor.jpg');
    $this->getJson('/api/v1/media?type=document')->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'menu.pdf');
    $this->getJson('/api/v1/media?type=spreadsheet')->assertJsonValidationErrors('type');
});

it('shows one media item', function () {
    $id = insertMedia(['path' => 'uploads/harbor.jpg', 'filename' => 'harbor.jpg', 'mime_type' => 'image/jpeg', 'caption' => 'Morning light in Nazaré']);

    $this->getJson('/api/v1/media/'.$id)
        ->assertOk()
        ->assertJsonPath('data.caption', 'Morning light in Nazaré')
        ->assertJsonPath('data.height', null);

    $this->getJson('/api/v1/media/999999')->assertNotFound();
});

it('returns 404 when the media table does not exist', function () {
    // The user avatar points to media, and Postgres and MySQL refuse to drop a table that is still referenced.
    Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['avatar_media_id']));
    Schema::drop('media');

    $this->getJson('/api/v1/media')->assertNotFound();
});

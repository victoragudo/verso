<?php

use App\Models\User;
use App\Verso\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->author = User::factory()->author()->create();
});

it('returns paginated media as json', function () {
    Media::factory()->count(3)->create();

    $this->actingAs($this->author)
        ->getJson(route('admin.media.library'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'url', 'filename', 'mime_type', 'type', 'size', 'width', 'height', 'alt', 'caption', 'thumbnail_url']],
            'current_page', 'last_page', 'per_page', 'total', 'next_page_url',
        ]);
});

it('searches and limits to the accepted type', function () {
    Media::factory()->create(['filename' => 'canal-bike.jpg', 'alt' => 'Bike by the canal']);
    Media::factory()->video()->create(['filename' => 'canal-walk.mp4']);
    Media::factory()->create(['filename' => 'bread.jpg', 'alt' => 'Bread']);

    $this->actingAs($this->author)
        ->getJson(route('admin.media.library', ['search' => 'canal', 'accept' => 'image']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'canal-bike.jpg');

    $this->actingAs($this->author)
        ->getJson(route('admin.media.library', ['accept' => 'video']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'video');

    $this->actingAs($this->author)
        ->getJson(route('admin.media.library', ['accept' => 'any']))
        ->assertJsonCount(3, 'data');
});

it('uses the configured page size', function () {
    config(['verso.media.per_page' => 2]);
    Media::factory()->count(3)->create();

    $this->actingAs($this->author)
        ->getJson(route('admin.media.library', ['page' => 2]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('last_page', 2);
});

it('is closed to subscribers and guests', function () {
    $this->getJson(route('admin.media.library'))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.media.library'))
        ->assertForbidden();
});

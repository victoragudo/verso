<?php

use App\Models\User;
use App\Verso\Media\Actions\UploadMedia;
use App\Verso\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 11, 16));
    $this->upload = app(UploadMedia::class);
});

it('stores an image in a year and month folder with its size', function () {
    $user = User::factory()->author()->create();

    $media = $this->upload->handle(UploadedFile::fake()->image('Sunset over the Douro.jpg', 1600, 900), $user, ['alt' => 'Orange sky over the river']);

    expect($media->path)->toBe('2026/11/sunset-over-the-douro.jpg')
        ->and($media->disk)->toBe('public')
        ->and($media->filename)->toBe('Sunset over the Douro.jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->width)->toBe(1600)
        ->and($media->height)->toBe(900)
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->alt)->toBe('Orange sky over the river')
        ->and($media->uploaded_by)->toBe($user->id);

    Storage::disk('public')->assertExists('2026/11/sunset-over-the-douro.jpg');
});

it('reads the size of png and webp images', function (string $name, string $mime) {
    $media = $this->upload->handle(UploadedFile::fake()->image($name, 320, 240));

    expect($media->mime_type)->toBe($mime)
        ->and([$media->width, $media->height])->toBe([320, 240]);
})->with([
    ['bakery-logo.png', 'image/png'],
    ['bakery-banner.webp', 'image/webp'],
]);

it('gives a new name when the file name is taken', function () {
    $first = $this->upload->handle(UploadedFile::fake()->image('market.jpg'));
    $second = $this->upload->handle(UploadedFile::fake()->image('market.jpg'));
    $third = $this->upload->handle(UploadedFile::fake()->image('Market.JPEG'));

    expect([$first->path, $second->path, $third->path])
        ->toBe(['2026/11/market.jpg', '2026/11/market-2.jpg', '2026/11/market-3.jpg']);
});

it('uses a safe name when the original has none', function () {
    $media = $this->upload->handle(UploadedFile::fake()->image('¿¿¿.png'));

    expect($media->path)->toBe('2026/11/file.png');
});

it('stores documents without a size', function () {
    $media = $this->upload->handle(UploadedFile::fake()->create('Bakery price list.pdf', 120, 'application/pdf'));

    expect($media->path)->toBe('2026/11/bakery-price-list.pdf')
        ->and($media->mime_type)->toBe('application/pdf')
        ->and($media->width)->toBeNull()
        ->and($media->height)->toBeNull()
        ->and($media->uploaded_by)->toBeNull();
});

it('refuses file types that are not allowed', function () {
    $this->upload->handle(UploadedFile::fake()->create('install.sh', 4, 'application/x-sh'));
})->throws(ValidationException::class);

it('refuses files that are too big', function () {
    config(['verso.media.max_upload_kb' => 100]);
    app()->forgetInstance(UploadMedia::class);

    app(UploadMedia::class)->handle(UploadedFile::fake()->create('wedding-film.mp4', 101, 'video/mp4'));
})->throws(ValidationException::class);

it('follows the allowed types from the config', function () {
    config(['verso.media.allowed_mimes' => ['image/png']]);

    expect(fn () => app(UploadMedia::class)->handle(UploadedFile::fake()->image('harbour.jpg')))
        ->toThrow(ValidationException::class);
    expect(Media::query()->count())->toBe(0);
});

it('uses the configured disk and folder format', function () {
    Storage::fake('s3');
    config(['verso.media.disk' => 's3', 'verso.media.folder_format' => 'Y']);

    $media = app(UploadMedia::class)->handle(UploadedFile::fake()->image('harbour.jpg'));

    expect($media->disk)->toBe('s3')->and($media->path)->toBe('2026/harbour.jpg');
    Storage::disk('s3')->assertExists('2026/harbour.jpg');
});

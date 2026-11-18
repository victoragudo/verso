<?php

use App\Verso\Media\Enums\MediaType;

it('finds the type from a mime type', function (string $mime, MediaType $type) {
    expect(MediaType::fromMime($mime))->toBe($type);
})->with([
    ['image/jpeg', MediaType::Image],
    ['image/webp', MediaType::Image],
    ['video/mp4', MediaType::Video],
    ['audio/mpeg', MediaType::Audio],
    ['application/pdf', MediaType::Document],
    ['text/plain', MediaType::Document],
]);

it('gives the mime prefix of each type', function () {
    expect(MediaType::Image->mimePrefix())->toBe('image/')
        ->and(MediaType::Video->mimePrefix())->toBe('video/')
        ->and(MediaType::Audio->mimePrefix())->toBe('audio/')
        ->and(MediaType::Document->mimePrefix())->toBeNull();
});

it('has a plural label for filters', function () {
    expect(MediaType::Image->label())->toBe('Images')
        ->and(MediaType::Document->label())->toBe('Documents');
});

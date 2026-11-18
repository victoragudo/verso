<?php

use App\Verso\Importer\Content\BlockIds;
use App\Verso\Importer\Content\ImportedMedia;
use App\Verso\Importer\Content\MediaMap;

beforeEach(function () {
    $this->map = new MediaMap;
    $this->photo = new ImportedMedia(14, 'https://verso.example/storage/uploads/2024/03/ribeira.jpg', 'uploads/2024/03/ribeira.jpg', 'Ribeira at dusk');
    $this->map->add(88, 'https://harbourlight.example/wp-content/uploads/2024/03/ribeira.jpg', $this->photo);
});

it('finds media by WordPress id', function () {
    expect($this->map->byId(88))->toBe($this->photo)
        ->and($this->map->byId(89))->toBeNull()
        ->and($this->map->count())->toBe(1);
});

it('finds media by URL with or without https and query strings', function () {
    expect($this->map->byUrl('http://harbourlight.example/wp-content/uploads/2024/03/ribeira.jpg?ver=2'))->toBe($this->photo)
        ->and($this->map->byUrl('https://harbourlight.example/wp-content/uploads/2024/03/RIBEIRA.jpg'))->toBe($this->photo)
        ->and($this->map->byUrl(''))->toBeNull();
});

it('finds resized and scaled copies', function () {
    expect($this->map->byUrl('https://harbourlight.example/wp-content/uploads/2024/03/ribeira-1024x683.jpg'))->toBe($this->photo)
        ->and($this->map->byUrl('https://harbourlight.example/wp-content/uploads/2024/03/ribeira-scaled.jpg'))->toBe($this->photo)
        ->and($this->map->byUrl('https://harbourlight.example/wp-content/uploads/2024/03/douro.jpg'))->toBeNull();
});

it('prefers the id over the URL', function () {
    $other = new ImportedMedia(15, '/storage/uploads/douro.jpg');
    $this->map->add(90, 'https://harbourlight.example/wp-content/uploads/douro.jpg', $other);

    expect($this->map->find(90, 'https://harbourlight.example/wp-content/uploads/2024/03/ribeira.jpg'))->toBe($other)
        ->and($this->map->find(null, 'https://harbourlight.example/wp-content/uploads/2024/03/ribeira.jpg'))->toBe($this->photo)
        ->and($this->map->find(404, 'https://harbourlight.example/wp-content/uploads/2024/03/ribeira.jpg'))->toBe($this->photo);
});

it('makes stable block ids from a seed', function () {
    $first = new BlockIds('wordpress:10');
    $second = new BlockIds('wordpress:10');
    $other = new BlockIds('wordpress:11');

    $ids = [$first->next(), $first->next()];

    expect($ids[0])->toMatch('/^b_[a-f0-9]{6}$/')
        ->and($ids[0])->not->toBe($ids[1])
        ->and($second->next())->toBe($ids[0])
        ->and($other->next())->not->toBe($ids[0]);
});

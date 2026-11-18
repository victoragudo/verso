<?php

use PHPUnit\Framework\ExpectationFailedException;

it('ignores object key order', function () {
    expect(['type' => 'recipe', 'id' => '30', 'seo' => ['title' => 'Sardines', 'noindex' => false]])
        ->toBeJsonData(['id' => '30', 'seo' => ['noindex' => false, 'title' => 'Sardines'], 'type' => 'recipe']);
});

it('still checks values strictly', function () {
    expect(fn () => expect(['reading_time' => '4'])->toBeJsonData(['reading_time' => 4]))
        ->toThrow(ExpectationFailedException::class);
});

it('still checks the order of lists', function () {
    expect(fn () => expect(['tags' => ['lisbon', 'porto']])->toBeJsonData(['tags' => ['porto', 'lisbon']]))
        ->toThrow(ExpectationFailedException::class);
});

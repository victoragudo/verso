<?php

use App\Verso\Comments\Support\SpamScore;

it('counts links', function () {
    $score = new SpamScore(3);

    expect($score->score('No links here, just a kind word about your bread recipe.'))->toBe(0)
        ->and($score->score('See https://cheap-watches.example and http://pills.example'))->toBe(2)
        ->and($score->score('Visit www.casino.example', 'and https://loans.example'))->toBe(2);
});

it('flags spam at the configured link count', function () {
    $score = new SpamScore(3);

    expect($score->isSpam('Two links: https://a.example https://b.example'))->toBeFalse()
        ->and($score->isSpam('https://a.example https://b.example www.c.example'))->toBeTrue();
});

it('never flags when the limit is zero', function () {
    expect((new SpamScore(0))->isSpam('https://a.example https://b.example https://c.example'))->toBeFalse();
});

it('reads the limit from config', function () {
    config()->set('verso.comments.spam_links', 1);
    app()->forgetInstance(SpamScore::class);

    expect(app(SpamScore::class)->isSpam('https://a.example'))->toBeTrue();
});

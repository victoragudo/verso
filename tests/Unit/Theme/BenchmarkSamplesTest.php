<?php

use App\Verso\Theme\Benchmark\Samples;

it('finds nearest rank percentiles', function () {
    $samples = new Samples([12.0, 3.0, 7.5, 1.0, 9.0, 4.0, 2.0, 8.0, 6.0, 5.0]);

    expect($samples->percentile(50))->toBe(5.0)
        ->and($samples->percentile(90))->toBe(9.0)
        ->and($samples->percentile(95))->toBe(12.0)
        ->and($samples->percentile(100))->toBe(12.0)
        ->and($samples->percentile(1))->toBe(1.0);
});

it('works out the mean and the sample count', function () {
    $samples = new Samples([2.0, 4.0, 9.0]);

    expect($samples->mean())->toBe(5.0)
        ->and($samples->count())->toBe(3);
});

it('keeps percentiles inside 1 and 100', function () {
    $samples = new Samples([0.4, 0.9]);

    expect($samples->percentile(0))->toBe(0.4)
        ->and($samples->percentile(250))->toBe(0.9);
});

it('needs at least one sample', function () {
    new Samples([]);
})->throws(InvalidArgumentException::class);

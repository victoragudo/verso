<?php

use App\Verso\Fields\Definitions\Location;
use App\Verso\Fields\Definitions\LocationContext;

it('matches when every rule of one group holds', function () {
    $location = Location::fromArray([
        [['param' => 'content_type', 'operator' => '==', 'value' => 'post'], ['param' => 'taxonomy_term', 'operator' => '==', 'value' => '7']],
        [['param' => 'content_type', 'operator' => '==', 'value' => 'page'], ['param' => 'page_template', 'operator' => '==', 'value' => 'landing']],
    ]);

    expect($location->matches(new LocationContext('post', '', [7, 9], 'editor')))->toBeTrue()
        ->and($location->matches(new LocationContext('post', '', [9], 'editor')))->toBeFalse()
        ->and($location->matches(new LocationContext('page', 'landing', [], 'editor')))->toBeTrue()
        ->and($location->matches(new LocationContext('page', '', [], 'editor')))->toBeFalse()
        ->and($location->matches(new LocationContext('product', 'landing', [7], 'editor')))->toBeFalse();
});

it('supports the not equal operator', function () {
    $location = Location::fromArray([[
        ['param' => 'content_type', 'operator' => '==', 'value' => 'post'],
        ['param' => 'user_role', 'operator' => '!=', 'value' => 'contributor'],
    ]]);

    expect($location->matches(new LocationContext('post', '', [], 'author')))->toBeTrue()
        ->and($location->matches(new LocationContext('post', '', [], 'contributor')))->toBeFalse();
});

it('treats parts the context does not know as matching', function () {
    $location = Location::fromArray([[
        ['param' => 'content_type', 'operator' => '==', 'value' => 'post'],
        ['param' => 'taxonomy_term', 'operator' => '==', 'value' => '12'],
        ['param' => 'page_template', 'operator' => '==', 'value' => 'wide'],
        ['param' => 'user_role', 'operator' => '==', 'value' => 'editor'],
    ]]);

    expect($location->matches(new LocationContext('post', false, null, null)))->toBeTrue()
        ->and($location->matches(new LocationContext('page', false, null, null)))->toBeFalse();
});

it('skips empty groups and keeps the JSON shape', function () {
    $location = Location::fromArray([[], 'broken', [['param' => 'content_type', 'operator' => '==', 'value' => 'post']]]);

    expect($location->groups)->toHaveCount(1)
        ->and($location->toArray())->toBe([[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]]);
});

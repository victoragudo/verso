<?php

use App\Verso\Site\Menus\MenuLocations;

it('starts with the locations from the config', function () {
    $locations = app(MenuLocations::class);

    expect($locations->all())->toBe(['primary' => 'Primary menu', 'footer' => 'Footer menu'])
        ->and($locations->has('primary'))->toBeTrue()
        ->and($locations->label('footer'))->toBe('Footer menu')
        ->and($locations->label('sidebar'))->toBeNull();
});

it('registers new locations', function () {
    $locations = new MenuLocations(['primary' => 'Primary menu']);
    $locations->register('social', 'Social links');

    expect($locations->toList())->toBe([
        ['name' => 'primary', 'label' => 'Primary menu'],
        ['name' => 'social', 'label' => 'Social links'],
    ]);
});

it('translates location labels for the admin', function () {
    app('translator')->addLines(['*.Footer menu' => 'Menú del pie'], 'es');
    app()->setLocale('es');
    $locations = new MenuLocations(['primary' => 'Primary menu', 'footer' => 'Footer menu']);

    expect($locations->translated())->toBe(['primary' => 'Primary menu', 'footer' => 'Menú del pie'])
        ->and($locations->toList()[1])->toBe(['name' => 'footer', 'label' => 'Menú del pie'])
        ->and($locations->label('footer'))->toBe('Footer menu');
});

<?php

use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManifest;
use App\Verso\Plugins\Support\PluginTranslations;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->folder = storage_path('framework/testing/plugins/tour-dates');
    File::ensureDirectoryExists($this->folder.'/lang');
    File::put($this->folder.'/lang/es.json', json_encode([
        'Tour Dates' => 'Fechas de gira',
        'Concert dates and ticket links for touring bands.' => 'Fechas de conciertos y enlaces a entradas para bandas de gira.',
    ]));

    $this->plugin = new Plugin('tour-dates', $this->folder, PluginManifest::fromArray([
        'name' => 'Tour Dates',
        'slug' => 'tour-dates',
        'version' => '1.2.0',
        'description' => 'Concert dates and ticket links for touring bands.',
        'provider' => 'Verso\\Plugins\\TourDates\\TourDatesServiceProvider',
    ], $this->folder));
});

afterEach(function () {
    File::deleteDirectory(dirname($this->folder));
});

it('reads the name and description of a disabled plugin from its own lang file', function () {
    app()->setLocale('es');
    $texts = new PluginTranslations;

    expect($this->plugin->enabled)->toBeFalse()
        ->and($texts->name($this->plugin))->toBe('Fechas de gira')
        ->and($texts->description($this->plugin))->toBe('Fechas de conciertos y enlaces a entradas para bandas de gira.');
});

it('keeps the English text when the plugin has no lang file for the language', function () {
    app()->setLocale('de');

    expect((new PluginTranslations)->name($this->plugin))->toBe('Tour Dates');
});

<?php

use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

beforeEach(function () {
    $this->parent = new Theme(slug: 'harbor-light', name: 'Harbor Light', path: '/srv/themes/harbor-light');
    $this->child = new Theme(slug: 'harbor-night', name: 'Harbor Night', path: '/srv/themes/harbor-night', parent: 'harbor-light');

    $themes = Mockery::mock(ThemeManager::class);
    $themes->shouldReceive('lineage')->with($this->child)->andReturn([$this->child, $this->parent]);

    $this->text = new ThemeText($themes);

    app()->setLocale('es');
    app('translator')->addLines([
        '*.harbor-light::Wide page' => 'Página ancha',
        '*.harbor-night::Landing page' => 'Página de aterrizaje',
        '*.Footer' => 'Pie de página',
    ], 'es');
});

it('uses the translation of the child theme first', function () {
    expect($this->text->text($this->child, 'Landing page'))->toBe('Página de aterrizaje');
});

it('uses the translation of the parent theme for labels the child inherits', function () {
    expect($this->text->text($this->child, 'Wide page'))->toBe('Página ancha');
});

it('falls back to the core translation and then to the English label', function () {
    expect($this->text->text($this->child, 'Footer'))->toBe('Pie de página')
        ->and($this->text->text($this->child, 'Harbor Night'))->toBe('Harbor Night')
        ->and($this->text->text($this->child, ''))->toBe('');
});

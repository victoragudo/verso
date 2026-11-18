<?php

use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\FontPairRegistry;
use App\Verso\Easy\Styles\StylePreset;
use App\Verso\Easy\Styles\StylePresetRegistry;

it('registers the four core style presets in order', function () {
    $registry = app(StylePresetRegistry::class);

    expect($registry->keys())->toBe(['calm', 'bold', 'playful', 'elegant'])
        ->and($registry->get('playful')->tokens)->toMatchArray(['radius.base' => '16px', 'spacing.x-large' => '3rem'])
        ->and($registry->get('elegant')->fontPair)->toBe('editorial')
        ->and($registry->find('retro'))->toBeNull()
        ->and($registry->has('bold'))->toBeTrue();
});

it('lets a plugin add and replace presets, sorted by order then label', function () {
    $registry = new StylePresetRegistry;
    $registry->register(new StylePreset('seaside', 'Seaside', 'Sand and sea.', ['radius.base' => '10px'], order: 20));
    $registry->register(new StylePreset('harbor', 'Harbor', 'Ropes and boats.', order: 20));
    $registry->register(new StylePreset('seaside', 'Seaside town', 'Pastel houses.', order: 5));

    expect($registry->keys())->toBe(['seaside', 'harbor'])
        ->and($registry->get('seaside')->label)->toBe('Seaside town');
});

it('refuses unknown presets and bad keys', function () {
    expect(fn () => app(StylePresetRegistry::class)->get('retro'))->toThrow(InvalidArgumentException::class, 'Unknown style preset [retro].')
        ->and(fn () => new StylePreset('Retro Wave', 'Retro', ''))->toThrow(InvalidArgumentException::class);
});

it('builds a preset from theme.json data on top of a core one', function () {
    $calm = app(StylePresetRegistry::class)->get('calm');
    $preset = StylePreset::fromArray('calm', ['tokens' => ['radius.base' => '9px', 'spacing.small' => 4], 'fontPair' => 'studio'], $calm);

    expect($preset->label)->toBe('Calm')
        ->and($preset->tokens['radius.base'])->toBe('9px')
        ->and($preset->tokens['spacing.medium'])->toBe('1rem')
        ->and($preset->tokens['spacing.small'])->toBe('0.5rem')
        ->and($preset->fontPair)->toBe('studio')
        ->and($preset->toArray())->toHaveKeys(['key', 'label', 'description', 'tokens', 'fontPair']);
});

it('registers core font pairs made only of safe fonts', function () {
    $registry = app(FontPairRegistry::class);
    $safe = array_keys((array) config('verso.themes.fonts'));

    expect($registry->keys())->toContain('classic', 'modern', 'friendly', 'editorial')
        ->and(array_key_first($registry->all()))->toBe('classic');

    foreach ($registry->all() as $pair) {
        expect($safe)->toContain($pair->heading, $pair->body)
            ->and($pair->sample)->not->toBe('');
    }
});

it('lets a plugin add a font pair and reads theme.json pairs', function () {
    $registry = new FontPairRegistry;
    $registry->register(new FontPair('bakery', 'Bakery', heading: 'georgia', body: 'humanist', order: 1));

    expect($registry->get('bakery')->toArray())->toMatchArray(['heading' => 'georgia', 'body' => 'humanist'])
        ->and(fn () => $registry->get('cafe'))->toThrow(InvalidArgumentException::class, 'Unknown font pair [cafe].')
        ->and(FontPair::fromArray('newsroom', ['label' => 'Newsroom', 'heading' => 'newsreader', 'body' => 'system'])?->label)->toBe('Newsroom')
        ->and(FontPair::fromArray('broken', ['heading' => 'newsreader']))->toBeNull()
        ->and(FontPair::fromArray('Bad Key', ['heading' => 'system', 'body' => 'system']))->toBeNull();
});

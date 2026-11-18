<?php

use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\Editor\Styles\BlockStyleCompiler;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Styles\StylePreset;
use App\Verso\Editor\Styles\StylePresets;

// The editor canvas makes the same CSS in TypeScript (resources/js/builder/style-css.ts). Both read this file.
function sharedStyleFixture(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/Fixtures/block-styles.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('lists the same style options and value rules as the schema', function () {
    $fixture = array_map(
        fn (array $option): array => [$option[0], $option[1], isset($option[2]) ? CssValueRule::fromArray($option[2])->toArray() : null],
        sharedStyleFixture()['options'],
    );
    $schema = array_map(
        fn (array $option): array => [$option[0], $option[1], $option[2]?->toArray()],
        (new BlockStyleSchema)->options(),
    );

    expect($fixture)->toBe($schema);
});

it('sends the value rule of every size option to the editor', function () {
    $sent = array_column(app(StylePropertyRegistry::class)->toArray(), 'value', 'path');

    expect($sent['typography.lineHeight'])->toMatchArray(['unitless' => true, 'keywords' => ['normal'], 'negative' => false])
        ->and($sent['size.maxWidth']['keywords'])->toBe(['fit-content', 'min-content', 'max-content', 'none'])
        ->and($sent['color.text'])->toBeNull();
});

it('sends the CSS property of every option to the editor', function () {
    $sent = array_column(app(StylePropertyRegistry::class)->toArray(), 'css', 'path');

    expect($sent)->toBe(array_map(fn (array $option): string => $option[0], BlockStyleSchema::OPTIONS));
});

it('compiles every shared case to the expected rules', function (array $case) {
    $fixture = sharedStyleFixture();
    $presets = new StylePresets;

    foreach ($fixture['presets'] as $key => $design) {
        $presets->register(new StylePreset($key, ucfirst($key), $design));
    }

    $compiler = new BlockStyleCompiler(new BlockStyleSchema, $fixture['breakpoints'], $fixture['customCssMax'], $presets);
    $style = $compiler->compile($case['attrs']);

    expect($style === null ? null : str_replace($style->class, 'vs-x', $style->css))->toBe($case['css']);
})->with(fn (): array => collect(sharedStyleFixture()['cases'])->mapWithKeys(fn (array $case): array => [$case['name'] => [$case]])->all());

it('sends the custom CSS limit to the editor', function () {
    config(['verso.editor.custom_css_max' => 1500]);

    expect(app(EditorManifest::class)->for(null)['customCssMax'])->toBe(1500);
});

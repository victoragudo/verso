<?php

use App\Verso\Theme\Customizer\ThemeSettingField;
use App\Verso\Theme\Customizer\ThemeSettingsSchema;
use App\Verso\Theme\Customizer\ThemeSettingType;
use App\Verso\Theme\Theme;
use Illuminate\Support\Facades\Validator;

// Font keys used by these tests.
const SAFE_FONTS = ['charter', 'georgia', 'system'];

it('reads every field type', function () {
    $schema = ThemeSettingsSchema::fromArray([
        'accent_color' => ['type' => 'color', 'label' => 'Accent color', 'default' => '#9A3412', 'css' => '--accent'],
        'body_font' => ['type' => 'font', 'default' => 'charter', 'options' => ['charter', 'georgia'], 'css' => '--font-text'],
        'logo' => ['type' => 'media', 'label' => 'Logo'],
        'layout_width' => ['type' => 'size', 'default' => 62, 'min' => 48, 'max' => 80, 'unit' => 'rem', 'css' => '--wide'],
        'footer_text' => ['type' => 'text', 'default' => '', 'max_length' => 120],
    ], SAFE_FONTS, 500);

    expect($schema->errors)->toBe([])
        ->and(array_keys($schema->fields))->toBe(['accent_color', 'body_font', 'logo', 'layout_width', 'footer_text'])
        ->and($schema->fields['body_font']->label)->toBe('Body font')
        ->and($schema->fields['body_font']->options)->toBe(['charter', 'georgia'])
        ->and($schema->fields['layout_width']->unit)->toBe('rem')
        ->and($schema->fields['footer_text']->maxLength)->toBe(120)
        ->and($schema->fields['logo']->type)->toBe(ThemeSettingType::Media);
});

it('offers every safe font when a font field lists no options', function () {
    $schema = ThemeSettingsSchema::fromArray(['heading_font' => ['type' => 'font', 'default' => 'system']], SAFE_FONTS, 500);

    expect($schema->fields['heading_font']->options)->toBe(SAFE_FONTS);
});

it('leaves out unsafe or broken fields and explains why', function (array $definition, string $message) {
    $schema = ThemeSettingsSchema::fromArray(['field_one' => $definition], SAFE_FONTS, 500);

    expect($schema->fields)->toBe([])
        ->and(implode(' ', $schema->errors))->toContain($message);
})->with([
    'unknown type' => [['type' => 'script'], 'unknown type'],
    'css injection in name' => [['type' => 'color', 'default' => '#ffffff', 'css' => '--accent;}body{display:none'], 'CSS variable name that is not valid'],
    'css name without dashes' => [['type' => 'color', 'default' => '#ffffff', 'css' => 'color'], 'CSS variable name that is not valid'],
    'text as css' => [['type' => 'text', 'css' => '--footer'], 'cannot be a CSS variable'],
    'font outside the safe list' => [['type' => 'font', 'default' => 'comic', 'options' => ['comic']], 'not in the safe font list'],
    'bad unit' => [['type' => 'size', 'default' => 10, 'min' => 1, 'max' => 20, 'unit' => 'vw;'], 'unknown unit'],
    'min above max' => [['type' => 'size', 'default' => 10, 'min' => 30, 'max' => 20], 'min lower than its max'],
    'bad color default' => [['type' => 'color', 'default' => 'red;}'], 'default value'],
    'size default out of range' => [['type' => 'size', 'default' => 99, 'min' => 1, 'max' => 20], 'default value'],
]);

it('refuses bad keys, non object fields and too many fields', function () {
    expect(ThemeSettingsSchema::fromArray(['Accent-Color' => ['type' => 'color']], SAFE_FONTS, 500)->errors[0])->toContain('lowercase letters')
        ->and(ThemeSettingsSchema::fromArray(['accent' => 'red'], SAFE_FONTS, 500)->errors[0])->toContain('must be an object');

    $many = array_fill_keys(array_map(fn (int $i) => "field_{$i}", range(1, 41)), ['type' => 'text']);

    expect(ThemeSettingsSchema::fromArray($many, SAFE_FONTS, 500)->errors[0])->toContain('at most 40');
});

it('checks values against the field type', function () {
    $color = ThemeSettingField::fromArray('accent', ['type' => 'color', 'css' => '--accent'], SAFE_FONTS, 500);
    $size = ThemeSettingField::fromArray('width', ['type' => 'size', 'min' => 40, 'max' => 80, 'unit' => 'rem', 'css' => '--wide'], SAFE_FONTS, 500);
    $text = ThemeSettingField::fromArray('footer', ['type' => 'text', 'max_length' => 10], SAFE_FONTS, 500);

    expect($color->accepts('#1d4ed8'))->toBeTrue()
        ->and($color->accepts('#1d4ed8;} body { display: none'))->toBeFalse()
        ->and($color->accepts('blue'))->toBeFalse()
        ->and($size->accepts(60))->toBeTrue()
        ->and($size->accepts(81))->toBeFalse()
        ->and($size->accepts('60'))->toBeFalse()
        ->and($text->accepts('Since 2019'))->toBeTrue()
        ->and($text->accepts('Since the summer of 2019'))->toBeFalse()
        ->and($size->cast('64'))->toBe(64)
        ->and($color->cast('#ABCDEF'))->toBe('#abcdef')
        ->and($text->cast(''))->toBeNull();
});

it('turns values into safe CSS values', function () {
    $font = ThemeSettingField::fromArray('body_font', ['type' => 'font', 'css' => '--font-text'], SAFE_FONTS, 500);
    $size = ThemeSettingField::fromArray('width', ['type' => 'size', 'min' => 40, 'max' => 80, 'unit' => 'rem', 'css' => '--wide'], SAFE_FONTS, 500);
    $logo = ThemeSettingField::fromArray('logo', ['type' => 'media'], SAFE_FONTS, 500);

    expect($font->cssValue('georgia', ['georgia' => 'Georgia, serif']))->toBe('Georgia, serif')
        ->and($font->cssValue('papyrus', ['georgia' => 'Georgia, serif']))->toBeNull()
        ->and($size->cssValue(70, []))->toBe('70rem')
        ->and($logo->cssValue(12, []))->toBeNull();
});

it('caps text fields at the configured limit', function () {
    expect(ThemeSettingField::fromArray('footer', ['type' => 'text', 'max_length' => 5000], SAFE_FONTS, 300)->maxLength)->toBe(300);
});

it('builds validation rules under the values key', function () {
    $rules = ThemeSettingsSchema::fromArray(['accent' => ['type' => 'color'], 'width' => ['type' => 'size', 'min' => 1, 'max' => 9]], SAFE_FONTS, 500)->rules();

    $passes = fn (mixed $value): bool => Validator::make(['values' => ['width' => $value]], ['values.width' => $rules['values.width']])->passes();

    expect(array_keys($rules))->toBe(['values', 'values.accent', 'values.width'])
        ->and($passes('9'))->toBeTrue()
        ->and($passes(12))->toBeFalse()
        ->and($passes('0.5rem'))->toBeTrue()
        ->and($passes('url(x)'))->toBeFalse();
});

it('reads size fields in any CSS unit and keeps old whole numbers', function () {
    $size = ThemeSettingField::fromArray('layout_width', ['type' => 'size', 'default' => 62, 'min' => 48, 'max' => 80, 'unit' => 'rem', 'css' => '--wide'], SAFE_FONTS, 500);

    expect($size->cast('70'))->toBe(70)
        ->and($size->cast('70rem'))->toBe(70)
        ->and($size->cast('72.5'))->toBe('72.5rem')
        ->and($size->cast('1100PX'))->toBe('1100px')
        ->and($size->cast('min(90vw, 72rem)'))->toBe('min(90vw, 72rem)')
        ->and($size->accepts(70))->toBeTrue()
        ->and($size->accepts(90))->toBeFalse()
        ->and($size->accepts('90rem'))->toBeFalse()
        ->and($size->accepts('72.5rem'))->toBeTrue()
        ->and($size->accepts('1100px'))->toBeTrue()
        ->and($size->accepts('-2px'))->toBeFalse()
        ->and($size->accepts('calc(1px); color: red'))->toBeFalse()
        ->and($size->cssValue('min(90vw, 72rem)', []))->toBe('min(90vw, 72rem)')
        ->and($size->toArray()['value']['units'])->toContain('px', 'rem', 'dvw', 'cqi');
});

it('reads length fields with their own units and keywords', function () {
    $gap = ThemeSettingField::fromArray('card_gap', ['type' => 'length', 'default' => 'clamp(1rem, 3vw, 2rem)', 'units' => ['px', 'rem', 'vw'], 'keywords' => ['normal'], 'css' => '--card-gap'], SAFE_FONTS, 500);

    expect($gap->type)->toBe(ThemeSettingType::Length)
        ->and($gap->unit)->toBe('px')
        ->and($gap->range())->toBeNull()
        ->and($gap->accepts('clamp(1rem, 3vw, 2rem)'))->toBeTrue()
        ->and($gap->accepts('normal'))->toBeTrue()
        ->and($gap->accepts('2em'))->toBeFalse()
        ->and($gap->cast('24'))->toBe(24)
        ->and($gap->cssValue(24, []))->toBe('24px')
        ->and($gap->toArray()['value'])->toMatchArray(['units' => ['px', 'rem', 'vw'], 'keywords' => ['normal']]);
});

it('refuses length fields with units that are not CSS units', function (array $data, string $message) {
    ThemeSettingField::fromArray('card_gap', ['type' => 'length', ...$data], SAFE_FONTS, 500);
})->with([
    'unknown unit' => [['unit' => 'furlong'], 'unknown unit'],
    'unknown units list' => [['units' => ['px', 'furlong']], 'not CSS length units'],
    'own unit not listed' => [['unit' => 'rem', 'units' => ['px']], 'own unit'],
    'unknown keyword' => [['keywords' => ['inherit']], 'keywords'],
])->throws(InvalidArgumentException::class);

it('ships a valid schema in the default theme', function () {
    $schema = ThemeSettingsSchema::fromConfig(Theme::fromDirectory(base_path('themes/default'))->settings);

    expect($schema->errors)->toBe([])
        ->and(array_keys($schema->fields))->toContain('accent_color', 'body_font', 'logo', 'layout_width', 'footer_text');
});

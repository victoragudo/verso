<?php

use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Support\Facades\Validator;

// True when the value passes the rules of the field.
function fieldAccepts(BlockField $field, mixed $value): bool
{
    return Validator::make([$field->name => $value], [$field->name => $field->rules()])->passes();
}

it('limits text fields to the configured length', function () {
    config(['verso.editor.plugin_text_max' => 20]);
    $field = new BlockField('caption', 'Caption');

    expect($field->rules())->toBe(['nullable', 'string', 'max:20'])
        ->and(fieldAccepts($field, 'Sunset over Alfama'))->toBeTrue()
        ->and(fieldAccepts($field, 'A very long caption about the river'))->toBeFalse()
        ->and(fieldAccepts($field, null))->toBeTrue();
});

it('uses the field max for text when it is given', function () {
    expect((new BlockField('headline', 'Headline', BlockFieldType::Textarea, max: 140))->rules())
        ->toBe(['nullable', 'string', 'max:140']);
});

it('checks numbers against min and max', function () {
    $field = new BlockField('minutes', 'Minutes', BlockFieldType::Number, min: 1, max: 600);

    expect(fieldAccepts($field, 12))->toBeTrue()
        ->and(fieldAccepts($field, 0))->toBeFalse()
        ->and(fieldAccepts($field, 601))->toBeFalse()
        ->and(fieldAccepts($field, 'twelve'))->toBeFalse();
});

it('only accepts safe links in url fields', function () {
    $field = new BlockField('ticketsUrl', 'Tickets link', BlockFieldType::Url);

    expect($field->rules()[3])->toBeInstanceOf(SafeUrl::class)
        ->and(fieldAccepts($field, 'https://teatro.example.pt/bilhetes'))->toBeTrue()
        ->and(fieldAccepts($field, 'javascript:alert(1)'))->toBeFalse();
});

it('accepts only known options in select fields', function () {
    $field = new BlockField('size', 'Size', BlockFieldType::Select, options: ['small' => 'Small', 'large' => 'Large'], required: true);

    expect(fieldAccepts($field, 'large'))->toBeTrue()
        ->and(fieldAccepts($field, 'huge'))->toBeFalse()
        ->and(fieldAccepts($field, null))->toBeFalse();
});

it('accepts booleans in toggle fields', function () {
    $field = new BlockField('showMap', 'Show map', BlockFieldType::Toggle);

    expect(fieldAccepts($field, true))->toBeTrue()
        ->and(fieldAccepts($field, 'maybe'))->toBeFalse();
});

it('checks length fields as CSS sizes', function () {
    $field = new BlockField('gap', 'Gap', BlockFieldType::Length, '1rem', value: new CssValueRule(units: ['px', 'rem'], negative: false));

    expect(fieldAccepts($field, '1.5rem'))->toBeTrue()
        ->and(fieldAccepts($field, 24))->toBeTrue()
        ->and(fieldAccepts($field, 'clamp(1rem, 4px + 1rem, 2rem)'))->toBeTrue()
        ->and(fieldAccepts($field, '2em'))->toBeFalse()
        ->and(fieldAccepts($field, '-1rem'))->toBeFalse()
        ->and(fieldAccepts($field, '1rem; color: red'))->toBeFalse()
        ->and($field->css(24))->toBe('24px')
        ->and($field->toArray()['value']['units'])->toBe(['px', 'rem'])
        ->and((new BlockField('note', 'Note'))->css('1rem'))->toBeNull();
});

it('turns the field into the shape the editor reads', function () {
    $field = new BlockField('size', 'Size', BlockFieldType::Select, default: 'small', options: ['small' => 'Small', 'large' => 'Large'], help: 'How big the map is.');

    expect($field->toArray())->toBe([
        'name' => 'size',
        'label' => 'Size',
        'type' => 'select',
        'default' => 'small',
        'options' => [['value' => 'small', 'label' => 'Small'], ['value' => 'large', 'label' => 'Large']],
        'help' => 'How big the map is.',
        'required' => false,
        'min' => null,
        'max' => null,
        'searchable' => false,
        'value' => null,
    ]);
});

it('marks select fields that always show a search box', function () {
    $field = new BlockField('form', 'Form', BlockFieldType::Select, options: ['contact' => 'Contact', 'catering' => 'Catering request'], searchable: true);

    expect($field->toArray()['searchable'])->toBeTrue();
});

it('rejects names that are not simple keys', function () {
    new BlockField('opening hours', 'Opening hours');
})->throws(InvalidArgumentException::class);

it('rejects select fields without options', function () {
    new BlockField('size', 'Size', BlockFieldType::Select);
})->throws(InvalidArgumentException::class);

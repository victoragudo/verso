<?php

use App\Verso\Fields\Definitions\Condition;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Enums\FieldPosition;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;

// A small but complete group used by several tests.
function recipeGroup(array $overrides = []): array
{
    return [
        'key' => 'recipe_card',
        'title' => 'Recipe card',
        'description' => 'Cooking facts shown above the recipe.',
        'position' => 'sidebar',
        'order' => 3,
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]],
        'fields' => [
            ['name' => 'servings', 'label' => 'Servings', 'type' => 'number', 'min' => 1, 'max' => 24, 'required' => true],
            ['name' => 'difficulty', 'label' => 'Difficulty', 'type' => 'select', 'options' => ['easy' => 'Easy', 'hard' => 'Hard']],
            ['name' => 'steps', 'label' => 'Steps', 'type' => 'repeater', 'max_rows' => 30, 'fields' => [
                ['name' => 'instruction', 'label' => 'Instruction', 'type' => 'textarea', 'required' => true],
                ['name' => 'minutes', 'label' => 'Minutes', 'type' => 'number'],
            ]],
        ],
        ...$overrides,
    ];
}

it('builds a field group from its JSON shape', function () {
    $group = FieldGroup::fromArray(recipeGroup());

    expect($group->key)->toBe('recipe_card')
        ->and($group->title)->toBe('Recipe card')
        ->and($group->position)->toBe(FieldPosition::Sidebar)
        ->and($group->order)->toBe(3)
        ->and($group->active)->toBeTrue()
        ->and($group->source)->toBe(FieldGroup::SOURCE_CODE)
        ->and($group->fields)->toHaveCount(3)
        ->and($group->field('steps')?->type)->toBe(FieldType::Repeater)
        ->and($group->field('steps')?->fields[0]->name)->toBe('instruction')
        ->and($group->field('missing'))->toBeNull();
});

it('keeps the same shape after a round trip', function () {
    $first = FieldGroup::fromArray(recipeGroup())->toArray();
    $second = FieldGroup::fromArray($first)->toArray();

    expect($second)->toBe($first)
        ->and($first['fields'][1]['options'])->toBe([['value' => 'easy', 'label' => 'Easy'], ['value' => 'hard', 'label' => 'Hard']])
        ->and($first['fields'][0])->not->toHaveKey('placeholder');
});

it('reads options from lists, maps and plain strings', function (mixed $options, array $expected) {
    $field = Field::fromArray(['name' => 'region', 'label' => 'Region', 'type' => 'radio', 'options' => $options]);

    expect($field->options)->toBe($expected);
})->with([
    'list of objects' => [[['value' => 'north', 'label' => 'North coast'], ['value' => 'south', 'label' => 'South coast']], [['value' => 'north', 'label' => 'North coast'], ['value' => 'south', 'label' => 'South coast']]],
    'map' => [['north' => 'North coast'], [['value' => 'north', 'label' => 'North coast']]],
    'strings' => [['Lisbon', 'Porto'], [['value' => 'Lisbon', 'label' => 'Lisbon'], ['value' => 'Porto', 'label' => 'Porto']]],
    'duplicates and blanks dropped' => [['Lisbon', 'Lisbon', ''], [['value' => 'Lisbon', 'label' => 'Lisbon']]],
]);

it('refuses broken fields', function (array $data, string $message) {
    expect(fn () => Field::fromArray($data))->toThrow(InvalidFieldDefinition::class, $message);
})->with([
    'bad name' => [['name' => 'Book Title', 'label' => 'Book title', 'type' => 'text'], 'must start with a letter'],
    'no label' => [['name' => 'isbn', 'label' => ' ', 'type' => 'text'], 'needs a label'],
    'unknown type' => [['name' => 'isbn', 'label' => 'ISBN', 'type' => 'barcode'], 'unknown type'],
    'select without options' => [['name' => 'genre', 'label' => 'Genre', 'type' => 'select'], 'at least one option'],
    'term relation without taxonomy' => [['name' => 'topic', 'label' => 'Topic', 'type' => 'term_relation'], 'needs a taxonomy'],
    'repeater without fields' => [['name' => 'chapters', 'label' => 'Chapters', 'type' => 'repeater'], 'at least one nested field'],
    'min over max' => [['name' => 'pages', 'label' => 'Pages', 'type' => 'number', 'min' => 900, 'max' => 10], 'min bigger than its max'],
    'min rows over max rows' => [['name' => 'quotes', 'label' => 'Quotes', 'type' => 'repeater', 'min_rows' => 5, 'max_rows' => 2, 'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'text']]], 'more min rows'],
    'nested names repeat' => [['name' => 'venue', 'label' => 'Venue', 'type' => 'group', 'fields' => [['name' => 'city', 'label' => 'City', 'type' => 'text'], ['name' => 'city', 'label' => 'Town', 'type' => 'text']]], 'used twice'],
]);

it('refuses a condition that watches a missing or the same field', function () {
    expect(fn () => FieldGroup::fromArray(recipeGroup(['fields' => [
        ['name' => 'video_url', 'label' => 'Video', 'type' => 'url', 'condition' => ['field' => 'has_video', 'value' => true]],
    ]])))->toThrow(InvalidFieldDefinition::class, 'must watch another field');

    expect(fn () => FieldGroup::fromArray(recipeGroup(['fields' => [
        ['name' => 'video_url', 'label' => 'Video', 'type' => 'url', 'condition' => ['field' => 'video_url', 'value' => 'x']],
    ]])))->toThrow(InvalidFieldDefinition::class, 'must watch another field');
});

it('refuses groups without key, title, fields or rules', function (array $overrides, string $message) {
    expect(fn () => FieldGroup::fromArray(recipeGroup($overrides)))->toThrow(InvalidFieldDefinition::class, $message);
})->with([
    'bad key' => [['key' => '1st group'], 'must start with a letter'],
    'no title' => [['title' => ''], 'needs a title'],
    'no fields' => [['fields' => []], 'at least one field'],
    'no location' => [['location' => []], 'at least one location rule'],
    'top level names repeat' => [['fields' => [['name' => 'isbn', 'label' => 'ISBN', 'type' => 'text'], ['name' => 'isbn', 'label' => 'ISBN 13', 'type' => 'text']]], 'used twice'],
    'unknown location param' => [['location' => [[['param' => 'weather', 'operator' => '==', 'value' => 'sunny']]]], 'unknown parameter'],
    'bad location operator' => [['location' => [[['param' => 'content_type', 'operator' => '>=', 'value' => 'post']]]], 'operator'],
    'empty location value' => [['location' => [[['param' => 'content_type', 'operator' => '==', 'value' => '']]]], 'needs a value'],
]);

it('limits nesting depth and field count', function () {
    $deep = ['name' => 'level_one', 'label' => 'Level one', 'type' => 'group', 'fields' => [
        ['name' => 'level_two', 'label' => 'Level two', 'type' => 'group', 'fields' => [
            ['name' => 'level_three', 'label' => 'Level three', 'type' => 'text'],
        ]],
    ]];

    expect(fn () => FieldGroup::fromArray(recipeGroup(['fields' => [$deep]]), maxDepth: 2))->toThrow(InvalidFieldDefinition::class, 'nested too deep')
        ->and(FieldGroup::fromArray(recipeGroup(['fields' => [$deep]]), maxDepth: 3)->fields[0]->count())->toBe(3)
        ->and(fn () => FieldGroup::fromArray(recipeGroup(), maxFields: 4))->toThrow(InvalidFieldDefinition::class, 'at most 4 fields');
});

it('gives typed defaults for new entries', function () {
    expect(Field::fromArray(['name' => 'featured', 'label' => 'Featured', 'type' => 'toggle'])->defaultValue())->toBeFalse()
        ->and(Field::fromArray(['name' => 'tags', 'label' => 'Tags', 'type' => 'checkbox', 'options' => ['a']])->defaultValue())->toBe([])
        ->and(Field::fromArray(['name' => 'related', 'label' => 'Related', 'type' => 'entry_relation', 'multiple' => true])->defaultValue())->toBe([])
        ->and(Field::fromArray(['name' => 'related', 'label' => 'Related', 'type' => 'entry_relation'])->defaultValue())->toBeNull()
        ->and(Field::fromArray(['name' => 'tone', 'label' => 'Tone', 'type' => 'color', 'default' => '#0f766e'])->defaultValue())->toBe('#0f766e');
});

it('only lets relation fields pick many', function () {
    expect(Field::fromArray(['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'multiple' => true])->multiple)->toBeFalse()
        ->and(Field::fromArray(['name' => 'authors', 'label' => 'Authors', 'type' => 'user_relation', 'multiple' => true])->multiple)->toBeTrue();
});

it('compares condition values as text', function (string $operator, mixed $expected, mixed $watched, bool $passes) {
    expect((new Condition('watched', $operator, $expected))->passes($watched))->toBe($passes);
})->with([
    'true equals true' => ['==', true, true, true],
    'true equals "1"' => ['==', true, '1', true],
    'false equals false' => ['==', false, false, true],
    'text equals text' => ['==', 'video', 'video', true],
    'text differs' => ['==', 'video', 'gallery', false],
    'number equals string number' => ['==', 3, '3', true],
    'not equal' => ['!=', 'video', 'gallery', true],
    'list contains' => ['==', 'vegan', ['vegan', 'quick'], true],
    'list misses' => ['==', 'vegan', ['quick'], false],
    'empty list equals empty' => ['==', '', [], true],
    'null equals empty' => ['==', null, null, true],
]);

it('lists every type with a label for the builder', function () {
    expect(FieldType::options())->toHaveCount(22)
        ->and(FieldType::options()[0])->toBe(['value' => 'text', 'label' => 'Text'])
        ->and(FieldType::Repeater->hasSubFields())->toBeTrue()
        ->and(FieldType::Checkbox->picksMany())->toBeTrue()
        ->and(FieldType::Url->hasMaxLength())->toBeTrue()
        ->and(FieldType::UserRelation->isRelation())->toBeTrue();
});

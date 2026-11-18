<?php

use App\Verso\Fields\Actions\DeleteFieldGroup;
use App\Verso\Fields\Actions\ImportFieldGroups;
use App\Verso\Fields\Actions\SaveFieldGroup;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Definitions\LocationContext;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Json\FieldGroupJson;
use App\Verso\Fields\Models\FieldGroupRecord;
use Illuminate\Support\Facades\File;

// A group for a travel site, placed on posts.
function travelGroup(string $key = 'trip_facts', array $overrides = []): array
{
    return [
        'key' => $key,
        'title' => 'Trip facts',
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]],
        'fields' => [['name' => 'country', 'label' => 'Country', 'type' => 'text']],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->folder = storage_path('framework/testing/field-json-'.uniqid());
    File::ensureDirectoryExists($this->folder);
});

afterEach(function () {
    File::deleteDirectory($this->folder);
});

it('merges groups from code, JSON files and the database', function () {
    $registry = app(FieldGroupRegistry::class);
    $registry->register(travelGroup('from_code', ['title' => 'Code group', 'order' => 2]));
    File::put($this->folder.'/json-group.json', json_encode(travelGroup('from_json', ['title' => 'JSON group', 'order' => 1])));
    $registry->addFolder($this->folder);
    FieldGroupRecord::factory()->create(['key' => 'from_admin', 'title' => 'Admin group', 'menu_order' => 0]);

    $groups = $registry->all();

    expect(array_keys($groups))->toBe(['from_admin', 'from_json', 'from_code'])
        ->and($groups['from_admin']->source)->toBe(FieldGroup::SOURCE_DATABASE)
        ->and($groups['from_json']->source)->toBe(FieldGroup::SOURCE_JSON)
        ->and($groups['from_code']->source)->toBe(FieldGroup::SOURCE_CODE)
        ->and($groups['from_admin']->isEditable())->toBeTrue()
        ->and($groups['from_code']->isEditable())->toBeFalse();
});

it('lets a database group replace a group from a file with the same key', function () {
    $registry = app(FieldGroupRegistry::class);
    File::put($this->folder.'/trip.json', json_encode(travelGroup()));
    $registry->addFolder(fn () => $this->folder);
    FieldGroupRecord::factory()->create(['key' => 'trip_facts', 'title' => 'Trip facts (edited)']);

    expect($registry->find('trip_facts')?->title)->toBe('Trip facts (edited)')
        ->and($registry->find('trip_facts')?->source)->toBe(FieldGroup::SOURCE_DATABASE);
});

it('reports broken JSON files and rows instead of failing', function () {
    $registry = app(FieldGroupRegistry::class);
    File::put($this->folder.'/broken.json', '{"key": ');
    File::put($this->folder.'/no-fields.json', json_encode(travelGroup('empty', ['fields' => []])));
    $registry->addFolder($this->folder);
    FieldGroupRecord::factory()->create(['key' => 'bad_row', 'fields' => [['name' => 'Bad Name', 'label' => 'Bad', 'type' => 'text']]]);

    expect($registry->all())->toBe([])
        ->and($registry->problems())->toHaveKeys(['broken.json', 'no-fields.json', 'bad_row'])
        ->and($registry->problems()['broken.json'])->toBe('The file is not valid JSON.');
});

it('ignores folders that do not exist', function () {
    $registry = app(FieldGroupRegistry::class);
    $registry->addFolder($this->folder.'/missing');
    $registry->addFolder(fn () => null);

    expect($registry->all())->toBe([]);
});

it('finds active groups and fields for a context', function () {
    $registry = app(FieldGroupRegistry::class);
    $registry->register(travelGroup('posts', ['fields' => [['name' => 'country', 'label' => 'Country', 'type' => 'text']]]));
    $registry->register(travelGroup('pages', [
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
        'fields' => [['name' => 'hero_title', 'label' => 'Hero title', 'type' => 'text']],
    ]));
    $registry->register(travelGroup('switched_off', ['active' => false, 'fields' => [['name' => 'hidden', 'label' => 'Hidden', 'type' => 'text']]]));
    $registry->register(travelGroup('second', ['order' => 5, 'fields' => [['name' => 'country', 'label' => 'Country again', 'type' => 'textarea']]]));

    $context = new LocationContext('post', '', [], 'editor');

    expect(array_map(fn (FieldGroup $group) => $group->key, $registry->matching($context)))->toBe(['posts', 'second'])
        ->and(array_keys($registry->fields($context)))->toBe(['country'])
        ->and($registry->fields($context)['country']->label)->toBe('Country');
});

it('saves, updates and deletes database groups and forgets the cache', function () {
    $registry = app(FieldGroupRegistry::class);
    expect($registry->all())->toBe([]);

    $record = app(SaveFieldGroup::class)->handle(FieldGroup::fromArray(travelGroup(), FieldGroup::SOURCE_DATABASE));
    expect($registry->find('trip_facts')?->title)->toBe('Trip facts')
        ->and($record->fields)->toBeJsonData([['name' => 'country', 'label' => 'Country', 'type' => 'text', 'required' => false]]);

    app(SaveFieldGroup::class)->handle(FieldGroup::fromArray(travelGroup(overrides: ['title' => 'Trip notes', 'description' => 'Facts for travellers.'])), $record);
    expect($registry->find('trip_facts')?->title)->toBe('Trip notes')
        ->and($record->fresh()?->description)->toBe('Facts for travellers.');

    app(DeleteFieldGroup::class)->handle($record);
    expect($registry->find('trip_facts'))->toBeNull()
        ->and(FieldGroupRecord::query()->count())->toBe(0);
});

it('imports new groups and replaces known keys', function () {
    FieldGroupRecord::factory()->create(['key' => 'trip_facts', 'title' => 'Old trip facts']);

    $result = app(ImportFieldGroups::class)->handle(json_encode([
        'version' => 1,
        'field_groups' => [travelGroup(), travelGroup('hotel_facts', ['title' => 'Hotel facts'])],
    ]));

    expect($result)->toBe(['created' => ['hotel_facts'], 'updated' => ['trip_facts']])
        ->and(FieldGroupRecord::query()->where('key', 'trip_facts')->value('title'))->toBe('Trip facts')
        ->and(FieldGroupRecord::query()->count())->toBe(2);
});

it('imports nothing when one group is broken', function () {
    expect(fn () => app(ImportFieldGroups::class)->handle(json_encode([travelGroup(), travelGroup('broken', ['title' => ''])])))
        ->toThrow(InvalidFieldDefinition::class, 'needs a title');

    expect(FieldGroupRecord::query()->count())->toBe(0);
});

it('reads one group, a list or the export wrapper', function (mixed $payload, int $count) {
    expect(app(FieldGroupJson::class)->decode(json_encode($payload)))->toHaveCount($count);
})->with([
    'one group' => [travelGroup(), 1],
    'a list' => [[travelGroup('first'), travelGroup('second')], 2],
    'export wrapper' => [['version' => 1, 'field_groups' => [travelGroup()]], 1],
]);

it('refuses JSON without field groups', function (string $json, string $message) {
    expect(fn () => app(FieldGroupJson::class)->decode($json))->toThrow(InvalidFieldDefinition::class, $message);
})->with([
    'not json' => ['<xml/>', 'not valid JSON'],
    'a number' => ['42', 'holds no field groups'],
    'empty list' => ['[]', 'holds no field groups'],
    'other object' => ['{"name": "Trip"}', 'holds no field groups'],
    'list of strings' => ['["trip"]', 'must be an object'],
]);

it('exports pretty JSON that imports again', function () {
    $json = app(FieldGroupJson::class)->encode([FieldGroup::fromArray(travelGroup())]);

    expect($json)->toContain('"version": 1')
        ->and($json)->toContain('"key": "trip_facts"')
        ->and(app(FieldGroupJson::class)->decode($json)[0]->toArray())->toBe(FieldGroup::fromArray(travelGroup())->toArray());
});

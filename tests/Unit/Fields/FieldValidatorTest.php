<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Role;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Values\FieldValidation;
use App\Verso\Fields\Values\FieldValidator;
use App\Verso\Media\Models\Media;

// Check one field with one value.
function checkField(array $definition, mixed $value): FieldValidation
{
    $field = Field::fromArray(['label' => 'Value', 'name' => 'value', ...$definition]);

    return app(FieldValidator::class)->validate([$field], ['value' => $value]);
}

it('accepts and cleans valid values', function (array $definition, mixed $input, mixed $clean) {
    $result = checkField($definition, $input);

    expect($result->errors)->toBe([])
        ->and($result->values['value'])->toBe($clean);
})->with([
    'text is trimmed' => [['type' => 'text'], '  The Lisbon Diaries  ', 'The Lisbon Diaries'],
    'textarea keeps inner lines' => [['type' => 'textarea'], "First line\nSecond line", "First line\nSecond line"],
    'rich text keeps paragraphs and marks' => [['type' => 'rich_text'], '<p>Fresh <strong>sardines</strong></p><ul><li>Salt</li></ul>', '<p>Fresh <strong>sardines</strong></p><ul><li>Salt</li></ul>'],
    'rich text drops scripts' => [['type' => 'rich_text'], '<p>Grilled fish</p><script>alert(1)</script>', '<p>Grilled fish</p>'],
    'integer from text' => [['type' => 'number'], '42', 42],
    'decimal from text' => [['type' => 'number'], '4.5', 4.5],
    'negative integer' => [['type' => 'number'], -3, -3],
    'email is lower cased' => [['type' => 'email'], 'Ana.Silva@Example.org', 'ana.silva@example.org'],
    'https url' => [['type' => 'url'], 'https://visitporto.travel/en', 'https://visitporto.travel/en'],
    'select option' => [['type' => 'select', 'options' => ['spring', 'summer']], 'summer', 'summer'],
    'radio option' => [['type' => 'radio', 'options' => ['spring', 'summer']], 'spring', 'spring'],
    'multi select keeps order and drops repeats' => [['type' => 'multi_select', 'options' => ['wifi', 'pool', 'parking']], ['pool', 'wifi', 'pool'], ['pool', 'wifi']],
    'checkboxes' => [['type' => 'checkbox', 'options' => ['vegan', 'gluten_free']], ['gluten_free'], ['gluten_free']],
    'toggle from text' => [['type' => 'toggle'], 'true', true],
    'toggle off' => [['type' => 'toggle'], '0', false],
    'toggle missing uses default' => [['type' => 'toggle', 'default' => true], null, true],
    'date' => [['type' => 'date'], '2026-03-14', '2026-03-14'],
    'date time with T' => [['type' => 'date_time'], '2026-03-14T09:30', '2026-03-14T09:30'],
    'date time with seconds and space' => [['type' => 'date_time'], '2026-03-14 09:30:00', '2026-03-14T09:30'],
    'color is lower cased' => [['type' => 'color'], '#1D4ED8', '#1d4ed8'],
    'empty optional text is null' => [['type' => 'text'], '   ', null],
    'empty optional list is empty' => [['type' => 'multi_select', 'options' => ['wifi']], [], []],
]);

it('refuses wrong values with a clear message', function (array $definition, mixed $input, string $message) {
    $result = checkField($definition, $input);

    expect($result->errors)->toHaveKey('meta.fields.value')
        ->and($result->errors['meta.fields.value'])->toContain($message);
})->with([
    'required text' => [['type' => 'text', 'required' => true], '', 'The Value field is required.'],
    'required list' => [['type' => 'checkbox', 'options' => ['a'], 'required' => true], [], 'is required'],
    'text too long' => [['type' => 'text', 'max_length' => 10], 'A very long subtitle', 'not be longer than 10'],
    'text global max' => [['type' => 'text'], str_repeat('a', 256), 'not be longer than 255'],
    'text not a string' => [['type' => 'text'], ['Lisbon'], 'must be text'],
    'number not numeric' => [['type' => 'number'], 'twelve', 'must be a number'],
    'number with exponent' => [['type' => 'number'], '1e5', 'must be a number'],
    'number under min' => [['type' => 'number', 'min' => 1], 0, 'at least 1'],
    'number over max' => [['type' => 'number', 'max' => 24], 30, 'not be more than 24'],
    'bad email' => [['type' => 'email'], 'ana.silva.example.org', 'valid email'],
    'url without http' => [['type' => 'url'], 'javascript:alert(1)', 'http:// or https://'],
    'url ftp' => [['type' => 'url'], 'ftp://files.example.org/menu.pdf', 'http:// or https://'],
    'select unknown option' => [['type' => 'select', 'options' => ['spring']], 'winter', 'Pick one of the options'],
    'multi select unknown option' => [['type' => 'multi_select', 'options' => ['wifi']], ['wifi', 'sauna'], 'Pick options from the list'],
    'multi select not a list' => [['type' => 'multi_select', 'options' => ['wifi']], 'wifi', 'Pick options from the list'],
    'toggle nonsense' => [['type' => 'toggle'], 'maybe', 'on or off'],
    'impossible date' => [['type' => 'date'], '2026-02-30', 'date like'],
    'date with time' => [['type' => 'date'], '2026-02-03 10:00', 'date like'],
    'bad date time' => [['type' => 'date_time'], 'tomorrow at nine', 'date and time like'],
    'short color' => [['type' => 'color'], '#fff', 'color like'],
    'color name' => [['type' => 'color'], 'teal', 'color like'],
    'missing image' => [['type' => 'image'], 999, 'Pick an image'],
    'missing file' => [['type' => 'file'], 999, 'Pick a file'],
    'gallery not a list' => [['type' => 'gallery'], 'all of them', 'Pick images'],
    'missing entry' => [['type' => 'entry_relation'], 999, 'Pick a valid item'],
    'repeater not a list' => [['type' => 'repeater', 'fields' => [['name' => 'line', 'label' => 'Line', 'type' => 'text']]], 'rows', 'list of rows'],
    'group not an object' => [['type' => 'group', 'fields' => [['name' => 'city', 'label' => 'City', 'type' => 'text']]], ['Porto'], 'must be an object'],
]);

it('checks image, file and gallery media', function () {
    $photo = Media::factory()->create();
    $other = Media::factory()->create();
    $pdf = Media::factory()->document()->create();

    expect(checkField(['type' => 'image'], (string) $photo->id)->values['value'])->toBe($photo->id)
        ->and(checkField(['type' => 'image'], $pdf->id)->errors)->toHaveKey('meta.fields.value')
        ->and(checkField(['type' => 'file'], $pdf->id)->values['value'])->toBe($pdf->id)
        ->and(checkField(['type' => 'gallery'], [$other->id, $photo->id, $other->id])->values['value'])->toBe([$other->id, $photo->id])
        ->and(checkField(['type' => 'gallery'], [$photo->id, $pdf->id])->errors)->toHaveKey('meta.fields.value')
        ->and(checkField(['type' => 'gallery', 'max_rows' => 1], [$photo->id, $other->id])->errors['meta.fields.value'])->toContain('at most 1 images');
});

it('checks entry relations against the allowed content types', function () {
    $post = Entry::factory()->create(['title' => 'Walking the Douro valley']);
    $page = Entry::factory()->page()->create(['title' => 'About us']);

    expect(checkField(['type' => 'entry_relation', 'content_types' => ['post']], $post->id)->values['value'])->toBe($post->id)
        ->and(checkField(['type' => 'entry_relation', 'content_types' => ['post']], $page->id)->errors)->toHaveKey('meta.fields.value')
        ->and(checkField(['type' => 'entry_relation', 'multiple' => true], [$page->id, $post->id])->values['value'])->toBe([$page->id, $post->id])
        ->and(checkField(['type' => 'entry_relation', 'multiple' => true], $post->id)->errors)->toHaveKey('meta.fields.value');
});

it('checks term relations against the taxonomy', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    $tag = Term::factory()->tag()->create(['name' => 'slow food']);

    expect(checkField(['type' => 'term_relation', 'taxonomy' => 'category'], $travel->id)->values['value'])->toBe($travel->id)
        ->and(checkField(['type' => 'term_relation', 'taxonomy' => 'category'], $tag->id)->errors)->toHaveKey('meta.fields.value');
});

it('checks user relations against the allowed roles', function () {
    $editor = User::factory()->editor()->create(['name' => 'Marta Ribeiro']);
    $reader = User::factory()->role(Role::Subscriber)->create(['name' => 'Luis Costa']);

    expect(checkField(['type' => 'user_relation', 'roles' => ['editor', 'admin']], $editor->id)->values['value'])->toBe($editor->id)
        ->and(checkField(['type' => 'user_relation', 'roles' => ['editor', 'admin']], $reader->id)->errors)->toHaveKey('meta.fields.value')
        ->and(checkField(['type' => 'user_relation', 'multiple' => true, 'max_rows' => 1], [$editor->id, $reader->id])->errors['meta.fields.value'])->toContain('at most 1 items');
});

it('validates repeater rows and nested repeaters with full paths', function () {
    $field = Field::fromArray(['name' => 'days', 'label' => 'Days', 'type' => 'repeater', 'min_rows' => 1, 'fields' => [
        ['name' => 'title', 'label' => 'Day title', 'type' => 'text', 'required' => true],
        ['name' => 'stops', 'label' => 'Stops', 'type' => 'repeater', 'fields' => [
            ['name' => 'place', 'label' => 'Place', 'type' => 'text', 'required' => true],
            ['name' => 'hours', 'label' => 'Hours', 'type' => 'number', 'min' => 0],
        ]],
    ]]);

    $result = app(FieldValidator::class)->validate([$field], ['days' => [
        ['title' => 'Arrival in Porto', 'stops' => [['place' => 'Ribeira', 'hours' => '2'], ['place' => '', 'hours' => -1]], 'unknown' => 'dropped'],
        ['title' => '', 'stops' => []],
    ]]);

    expect($result->errors)->toBe([
        'meta.fields.days.0.stops.1.place' => 'The Place field is required.',
        'meta.fields.days.0.stops.1.hours' => 'The Hours field must be at least 0.',
        'meta.fields.days.1.title' => 'The Day title field is required.',
    ])->and($result->values['days'][0])->toBe([
        'title' => 'Arrival in Porto',
        'stops' => [['place' => 'Ribeira', 'hours' => 2], ['place' => null, 'hours' => null]],
    ]);
});

it('enforces repeater row limits', function () {
    $definition = ['type' => 'repeater', 'min_rows' => 2, 'max_rows' => 3, 'fields' => [['name' => 'quote', 'label' => 'Quote', 'type' => 'text']]];

    expect(checkField($definition, [['quote' => 'Saudade']])->errors['meta.fields.value'])->toContain('at least 2 rows')
        ->and(checkField($definition, array_fill(0, 4, ['quote' => 'Fado']))->errors['meta.fields.value'])->toContain('at most 3 rows')
        ->and(checkField([...$definition, 'max_rows' => null, 'min_rows' => null], array_fill(0, 101, ['quote' => 'Fado']))->errors['meta.fields.value'])->toContain('at most 100 rows')
        ->and(checkField([...$definition, 'required' => true, 'min_rows' => null], [])->errors['meta.fields.value'])->toContain('required');
});

it('validates groups as nested objects', function () {
    $field = Field::fromArray(['name' => 'venue', 'label' => 'Venue', 'type' => 'group', 'fields' => [
        ['name' => 'name', 'label' => 'Venue name', 'type' => 'text', 'required' => true],
        ['name' => 'website', 'label' => 'Website', 'type' => 'url'],
    ]]);

    $result = app(FieldValidator::class)->validate([$field], ['venue' => ['name' => 'Coliseu do Porto', 'website' => 'not a link']]);

    expect($result->errors)->toBe(['meta.fields.venue.website' => 'The Website field must be a link that starts with http:// or https://.'])
        ->and($result->values['venue']['name'])->toBe('Coliseu do Porto');
});

it('skips and drops fields hidden by their condition', function () {
    $fields = [
        Field::fromArray(['name' => 'media_kind', 'label' => 'Media kind', 'type' => 'radio', 'options' => ['none', 'video', 'audio']]),
        Field::fromArray(['name' => 'video_url', 'label' => 'Video link', 'type' => 'url', 'required' => true, 'condition' => ['field' => 'media_kind', 'value' => 'video']]),
        Field::fromArray(['name' => 'captions', 'label' => 'Captions', 'type' => 'toggle', 'condition' => ['field' => 'video_url', 'operator' => '!=', 'value' => '']]),
        Field::fromArray(['name' => 'note', 'label' => 'Note', 'type' => 'text', 'condition' => ['field' => 'media_kind', 'operator' => '!=', 'value' => 'none']]),
    ];

    $hidden = app(FieldValidator::class)->validate($fields, ['media_kind' => 'none', 'video_url' => 'https://vimeo.com/1', 'captions' => true, 'note' => 'Shot in Sintra']);
    $shown = app(FieldValidator::class)->validate($fields, ['media_kind' => 'video', 'video_url' => '']);

    expect($hidden->errors)->toBe([])
        ->and($hidden->values)->toBe(['media_kind' => 'none'])
        ->and($shown->errors)->toBe(['meta.fields.video_url' => 'The Video link field is required.'])
        ->and($shown->values)->toHaveKeys(['media_kind', 'video_url', 'note'])
        ->and($shown->values)->not->toHaveKey('captions');
});

it('applies conditions inside each repeater row', function () {
    $field = Field::fromArray(['name' => 'tickets', 'label' => 'Tickets', 'type' => 'repeater', 'fields' => [
        ['name' => 'free', 'label' => 'Free', 'type' => 'toggle'],
        ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => true, 'condition' => ['field' => 'free', 'value' => false]],
    ]]);

    $result = app(FieldValidator::class)->validate([$field], ['tickets' => [['free' => true], ['free' => false]]]);

    expect($result->errors)->toBe(['meta.fields.tickets.1.price' => 'The Price field is required.'])
        ->and($result->values['tickets'][0])->toBe(['free' => true]);
});

it('drops unknown keys', function () {
    $field = Field::fromArray(['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text']);

    expect(app(FieldValidator::class)->validate([$field], ['subtitle' => 'A slow guide', 'admin' => true])->values)->toBe(['subtitle' => 'A slow guide']);
});

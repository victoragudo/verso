<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Fields\Models\FieldGroupRecord;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create();
    $this->group = FieldGroupRecord::factory()->create([
        'key' => 'book_review',
        'title' => 'Book review',
        'fields' => [
            ['name' => 'book_title', 'label' => 'Book title', 'type' => 'text', 'required' => true],
            ['name' => 'rating', 'label' => 'Rating', 'type' => 'number', 'min' => 1, 'max' => 5],
            ['name' => 'has_audio', 'label' => 'Has an audio book', 'type' => 'toggle'],
            ['name' => 'narrator', 'label' => 'Narrator', 'type' => 'text', 'required' => true, 'condition' => ['field' => 'has_audio', 'value' => true]],
            ['name' => 'quotes', 'label' => 'Quotes', 'type' => 'repeater', 'fields' => [
                ['name' => 'text', 'label' => 'Quote', 'type' => 'textarea', 'required' => true],
                ['name' => 'page', 'label' => 'Page', 'type' => 'number'],
            ]],
        ],
    ]);
});

// The editor form with custom field values.
function reviewPayload(array $fields, array $extra = []): array
{
    return [
        'title' => 'A quiet novel about the sea',
        'status' => 'draft',
        'blocks' => [],
        'meta' => ['fields' => $fields],
        ...$extra,
    ];
}

it('shares the field groups of the content type with the editor', function () {
    FieldGroupRecord::factory()->sidebar()->create(['key' => 'page_hero', 'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]]]);
    FieldGroupRecord::factory()->create(['key' => 'admins_only', 'location' => [[['param' => 'user_role', 'operator' => '==', 'value' => 'admin']]]]);
    FieldGroupRecord::factory()->inactive()->create(['key' => 'switched_off']);

    $this->actingAs($this->editor)
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('fieldGroups', 1)
            ->where('fieldGroups.0.key', 'book_review')
            ->where('fieldGroups.0.position', 'main')
            ->where('fieldGroups.0.fields.3.condition', ['field' => 'has_audio', 'operator' => '==', 'value' => true])
        );
});

it('saves clean field values in the entry meta', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload([
            'book_title' => '  The Book of Disquiet ',
            'rating' => '5',
            'has_audio' => '1',
            'narrator' => 'Tomás Ribeiro',
            'quotes' => [['text' => 'To live is to be other.', 'page' => '12']],
            'injected' => '<script>alert(1)</script>',
        ]))
        ->assertSessionHasNoErrors();

    expect(Entry::query()->sole()->meta['fields'])->toBeJsonData([
        'book_title' => 'The Book of Disquiet',
        'rating' => 5,
        'has_audio' => true,
        'narrator' => 'Tomás Ribeiro',
        'quotes' => [['text' => 'To live is to be other.', 'page' => 12]],
    ]);
});

it('returns errors for each wrong field with its path', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload([
            'book_title' => '',
            'rating' => 9,
            'has_audio' => true,
            'quotes' => [['text' => 'A fine line', 'page' => 3], ['text' => '']],
        ]))
        ->assertSessionHasErrors([
            'meta.fields.book_title' => 'The Book title field is required.',
            'meta.fields.rating' => 'The Rating field must not be more than 5.',
            'meta.fields.narrator' => 'The Narrator field is required.',
            'meta.fields.quotes.1.text' => 'The Quote field is required.',
        ]);

    expect(Entry::query()->count())->toBe(0);
});

it('does not check or keep fields hidden by a condition', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload(['book_title' => 'Blindness', 'has_audio' => false, 'narrator' => 'Someone']))
        ->assertSessionHasNoErrors();

    expect(Entry::query()->sole()->meta['fields'])->not->toHaveKey('narrator');
});

it('keeps stored values of groups that do not show for this request', function () {
    FieldGroupRecord::factory()->create([
        'key' => 'travel_extras',
        'location' => [[['param' => 'taxonomy_term', 'operator' => '==', 'value' => '999']]],
        'fields' => [['name' => 'best_season', 'label' => 'Best season', 'type' => 'text']],
    ]);
    $entry = Entry::factory()->create(['meta' => ['fields' => ['book_title' => 'Old title', 'best_season' => 'Autumn'], 'seo' => ['title' => 'Kept']]]);

    $this->actingAs($this->editor)
        ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $entry->id]), reviewPayload(
            [],
            ['meta' => ['fields' => ['book_title' => 'Journey to Portugal', 'best_season' => 'tampered'], 'seo' => ['title' => 'Kept']]],
        ))
        ->assertSessionHasNoErrors();

    expect($entry->fresh()->meta['fields'])->toBeJsonData([
        'best_season' => 'Autumn',
        'book_title' => 'Journey to Portugal',
        'rating' => null,
        'has_audio' => false,
        'quotes' => [],
    ])->and($entry->fresh()->meta['seo']['title'])->toBe('Kept');
});

it('uses the picked terms to decide which groups apply', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    FieldGroupRecord::factory()->create([
        'key' => 'travel_extras',
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'post'], ['param' => 'taxonomy_term', 'operator' => '==', 'value' => (string) $travel->id]]],
        'fields' => [['name' => 'best_season', 'label' => 'Best season', 'type' => 'select', 'options' => ['spring', 'autumn'], 'required' => true]],
    ]);

    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload(['book_title' => 'Walking the coast']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload(['book_title' => 'Walking the coast'], ['terms' => ['category' => [$travel->id]]]))
        ->assertSessionHasErrors(['meta.fields.best_season']);
});

it('uses the template in the meta for template rules', function () {
    FieldGroupRecord::factory()->create([
        'key' => 'landing',
        'location' => [[['param' => 'page_template', 'operator' => '==', 'value' => 'landing']]],
        'fields' => [['name' => 'call_to_action', 'label' => 'Call to action', 'type' => 'text', 'required' => true]],
    ]);

    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), reviewPayload([], ['meta' => ['template' => 'landing', 'fields' => ['book_title' => 'Spring sale']]]))
        ->assertSessionHasErrors(['meta.fields.call_to_action']);
});

it('leaves the meta alone when no group applies and no fields are sent', function () {
    $this->group->delete();

    $this->actingAs($this->editor)
        ->post(route('admin.entries.store', 'post'), ['title' => 'Plain post', 'status' => 'draft', 'blocks' => [], 'meta' => ['seo' => ['title' => 'Plain']]])
        ->assertSessionHasNoErrors();

    expect(Entry::query()->sole()->meta)->not->toHaveKey('fields');
});

<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Models\FieldGroupRecord;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\PluginServiceProvider;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A fixed name: a random one like "Rita Dickinson" would show up in the user search below.
    $this->admin = User::factory()->admin()->create(['name' => 'Inês Duarte']);
});

// What the builder form sends for a new group.
function builderPayload(array $overrides = []): array
{
    return [
        'key' => 'event_details',
        'title' => 'Event details',
        'description' => 'Date and place of the event.',
        'position' => 'sidebar',
        'order' => 2,
        'active' => true,
        'location' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]],
        'fields' => [
            ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'date_time', 'required' => true],
            ['name' => 'venue', 'label' => 'Venue', 'type' => 'group', 'fields' => [
                ['name' => 'name', 'label' => 'Venue name', 'type' => 'text'],
            ]],
        ],
        ...$overrides,
    ];
}

it('lists groups from every source with a location summary', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    FieldGroupRecord::factory()->create(['key' => 'admin_group', 'title' => 'Hotel facts', 'location' => [
        [['param' => 'content_type', 'operator' => '==', 'value' => 'post'], ['param' => 'taxonomy_term', 'operator' => '==', 'value' => (string) $travel->id]],
        [['param' => 'user_role', 'operator' => '!=', 'value' => 'author']],
    ]]);
    app(FieldGroupRegistry::class)->register(builderPayload(['key' => 'plugin_group', 'title' => 'Plugin event', 'order' => 9]));

    $this->actingAs($this->admin)
        ->get(route('admin.fields.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fields/index')
            ->has('groups', 2)
            ->where('groups.0.key', 'admin_group')
            ->where('groups.0.source', 'database')
            ->where('groups.0.fields', 2)
            ->where('groups.0.location', 'Content type is Post and Taxonomy term is Travel, or User role is not Author')
            ->where('groups.1.key', 'plugin_group')
            ->where('groups.1.source', 'code')
            ->where('groups.1.id', null)
            ->where('groups.1.fields', 3)
            ->where('importMaxKb', 512)
        );
});

it('shows the builder with every choice it needs', function () {
    Term::factory()->create(['name' => 'Travel']);

    $this->actingAs($this->admin)
        ->get(route('admin.fields.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fields/edit')
            ->where('group', null)
            ->has('fieldTypes', 22)
            ->has('positions', 2)
            ->has('locationParams', 4)
            ->where('contentTypes.0', ['value' => 'post', 'label' => 'Post'])
            ->where('terms.0.label', 'Travel')
            ->where('terms.0.group', 'Category')
            ->has('roles', 5)
        );
});

it('offers the shop manager role in location rules only while it is turned on', function () {
    $roleValues = fn (): array => array_column(
        $this->actingAs($this->admin)->get(route('admin.fields.create'))->viewData('page')['props']['roles'],
        'value',
    );

    expect($roleValues())->not->toContain('shop_manager');

    app(OptionalRoles::class)->enable(Role::ShopManager);

    expect($roleValues())->toContain('shop_manager');
});

it('creates a field group', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.fields.store'), builderPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.fields.edit', FieldGroupRecord::query()->sole()));

    $record = FieldGroupRecord::query()->sole();
    expect($record->key)->toBe('event_details')
        ->and($record->position)->toBe('sidebar')
        ->and($record->menu_order)->toBe(2)
        ->and($record->fields[1]['fields'][0]['name'])->toBe('name');
});

it('refuses a broken builder form', function (array $overrides, string $field, string $message) {
    $this->actingAs($this->admin)
        ->post(route('admin.fields.store'), builderPayload($overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(FieldGroupRecord::query()->count())->toBe(0);
})->with([
    'bad key' => [['key' => 'Event Details'], 'key', 'The key must start with a letter and use only a to z, 0 to 9, - and _.'],
    'no title' => [['title' => ''], 'title', 'The title field is required.'],
    'no fields' => [['fields' => []], 'fields', 'Add at least one field.'],
    'no location' => [['location' => []], 'location', 'Add at least one location rule.'],
    'bad position' => [['position' => 'footer'], 'position', 'The selected position is invalid.'],
    'repeated names' => [['fields' => [['name' => 'venue', 'label' => 'Venue', 'type' => 'text'], ['name' => 'venue', 'label' => 'Place', 'type' => 'text']]], 'fields', 'The name "venue" is used twice in "event_details".'],
    'select without options' => [['fields' => [['name' => 'kind', 'label' => 'Kind', 'type' => 'select']]], 'fields', 'The field "kind" needs at least one option.'],
]);

it('refuses a key that another group already uses', function () {
    FieldGroupRecord::factory()->create(['key' => 'event_details']);

    $this->actingAs($this->admin)
        ->post(route('admin.fields.store'), builderPayload())
        ->assertSessionHasErrors('key');
});

it('edits and updates a field group', function () {
    $record = FieldGroupRecord::factory()->create(['key' => 'event_details']);

    $this->actingAs($this->admin)
        ->get(route('admin.fields.edit', $record))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.id', $record->id)
            ->where('group.key', 'event_details')
            ->where('group.source', 'database')
            ->has('group.fields', 2)
        );

    $this->actingAs($this->admin)
        ->put(route('admin.fields.update', $record), builderPayload(['title' => 'Concert details']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.fields.edit', $record));

    expect($record->fresh()?->title)->toBe('Concert details');
});

it('opens a stored group that no longer passes the checks so it can be fixed', function () {
    $record = FieldGroupRecord::factory()->create(['fields' => [['name' => 'Bad Name', 'label' => 'Bad', 'type' => 'text']]]);

    $this->actingAs($this->admin)
        ->get(route('admin.fields.edit', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.fields.0.name', 'Bad Name'));
});

it('deletes a field group but keeps entry values', function () {
    $record = FieldGroupRecord::factory()->create();
    $entry = Entry::factory()->create(['meta' => ['fields' => ['book_author' => 'José Saramago']]]);

    $this->actingAs($this->admin)
        ->delete(route('admin.fields.destroy', $record))
        ->assertRedirect(route('admin.fields.index'));

    expect(FieldGroupRecord::query()->count())->toBe(0)
        ->and($entry->fresh()?->meta['fields']['book_author'])->toBe('José Saramago');
});

it('copies a code group into the database so it can be edited', function () {
    app(FieldGroupRegistry::class)->register(builderPayload(['key' => 'plugin_event']));

    $this->actingAs($this->admin)
        ->post(route('admin.fields.copy', 'plugin_event'))
        ->assertRedirect();

    expect(FieldGroupRecord::query()->sole()->key)->toBe('plugin_event');

    $this->actingAs($this->admin)->post(route('admin.fields.copy', 'plugin_event'))->assertStatus(409);
    $this->actingAs($this->admin)->post(route('admin.fields.copy', 'missing_group'))->assertNotFound();
});

it('exports one group or every group as JSON', function () {
    FieldGroupRecord::factory()->create(['key' => 'book_review']);
    FieldGroupRecord::factory()->create(['key' => 'recipe_card']);

    $one = $this->actingAs($this->admin)->get(route('admin.fields.export', ['key' => 'book_review']))->assertOk();
    $all = $this->actingAs($this->admin)->get(route('admin.fields.export'))->assertOk();

    expect($one->headers->get('Content-Disposition'))->toBe('attachment; filename="book_review.json"')
        ->and(json_decode((string) $one->getContent(), true)['field_groups'])->toHaveCount(1)
        ->and($all->headers->get('Content-Disposition'))->toBe('attachment; filename="field-groups.json"')
        ->and(json_decode((string) $all->getContent(), true)['field_groups'])->toHaveCount(2);

    $this->actingAs($this->admin)->get(route('admin.fields.export', ['key' => 'missing']))->assertNotFound();
});

it('imports groups from an uploaded JSON file', function () {
    $file = UploadedFile::fake()->createWithContent('groups.json', json_encode(['version' => 1, 'field_groups' => [builderPayload()]]));

    $this->actingAs($this->admin)
        ->post(route('admin.fields.import'), ['file' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.fields.index'));

    expect(FieldGroupRecord::query()->sole()->title)->toBe('Event details');
});

it('explains why an import failed', function () {
    $file = UploadedFile::fake()->createWithContent('groups.json', '{"field_groups": [{"key": "x"}]}');

    $this->actingAs($this->admin)
        ->post(route('admin.fields.import'), ['file' => $file])
        ->assertSessionHasErrors(['file' => 'The field group "x" needs a title.']);
});

it('refuses import files over the size limit', function () {
    config(['verso.fields.import_max_kb' => 1]);
    $file = UploadedFile::fake()->create('huge.json', 5);

    $this->actingAs($this->admin)
        ->post(route('admin.fields.import'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('keeps the builder for admins', function (string $method, string $route) {
    $editor = User::factory()->editor()->create();
    $record = FieldGroupRecord::factory()->create();
    $url = str_contains($route, 'edit') || in_array($route, ['admin.fields.update', 'admin.fields.destroy'], true)
        ? route($route, $record)
        : route($route);

    $this->actingAs($editor)->call($method, $url, builderPayload())->assertForbidden();
})->with([
    ['GET', 'admin.fields.index'],
    ['GET', 'admin.fields.create'],
    ['POST', 'admin.fields.store'],
    ['GET', 'admin.fields.edit'],
    ['PUT', 'admin.fields.update'],
    ['DELETE', 'admin.fields.destroy'],
    ['GET', 'admin.fields.export'],
    ['POST', 'admin.fields.import'],
]);

it('adds a Custom fields item to the admin nav for admins only', function () {
    $this->actingAs($this->admin)->get(route('admin.fields.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'fields')));

    $this->actingAs(User::factory()->editor()->create())->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => ! collect($nav)->contains('key', 'fields')));
});

it('searches entries, terms and users for relation fields', function () {
    $editor = User::factory()->editor()->create(['name' => 'Rita Lopes']);
    Entry::factory()->create(['title' => 'Cork forests of Alentejo']);
    Entry::factory()->page()->create(['title' => 'Cork workshop']);
    Term::factory()->create(['name' => 'Cork crafts']);
    Term::factory()->tag()->create(['name' => 'Cork tag']);
    User::factory()->author()->create(['name' => 'Rita Antunes']);

    $lookup = fn (array $query) => $this->actingAs($editor)->getJson(route('admin.fields.lookup', $query))->assertOk()->json('items');

    expect(array_column($lookup(['kind' => 'entry', 'q' => 'cork']), 'label'))->toBe(['Cork forests of Alentejo', 'Cork workshop'])
        ->and(array_column($lookup(['kind' => 'entry', 'q' => 'cork', 'types' => ['page']]), 'label'))->toBe(['Cork workshop'])
        ->and($lookup(['kind' => 'entry', 'q' => 'cork'])[0]['hint'])->toBe('Draft')
        ->and(array_column($lookup(['kind' => 'term', 'q' => 'cork', 'taxonomy' => 'category']), 'label'))->toBe(['Cork crafts'])
        ->and(array_column($lookup(['kind' => 'user', 'q' => 'rita']), 'label'))->toBe(['Rita Antunes', 'Rita Lopes'])
        ->and(array_column($lookup(['kind' => 'user', 'q' => 'rita', 'roles' => ['author']]), 'label'))->toBe(['Rita Antunes'])
        ->and($lookup(['kind' => 'user', 'q' => 'rita'])[0])->not->toHaveKey('email');
});

it('looks up labels and media by id', function () {
    $editor = User::factory()->editor()->create();
    $entry = Entry::factory()->create(['title' => 'Azulejo stories']);
    $photo = Media::factory()->create(['alt' => 'Blue tiles']);

    $entries = $this->actingAs($editor)->getJson(route('admin.fields.lookup', ['kind' => 'entry', 'ids' => [$entry->id]]))->json('items');
    $media = $this->actingAs($editor)->getJson(route('admin.fields.lookup', ['kind' => 'media', 'ids' => [$photo->id]]))->json('items');

    expect($entries)->toBe([['value' => (string) $entry->id, 'label' => 'Azulejo stories', 'hint' => 'Draft']])
        ->and($media[0]['id'])->toBe($photo->id)
        ->and($media[0]['alt'])->toBe('Blue tiles')
        ->and($media[0])->toHaveKey('thumbnail_url');
});

it('limits the lookup to people who edit entries and to known kinds', function () {
    $this->actingAs(User::factory()->create())->getJson(route('admin.fields.lookup', ['kind' => 'entry']))->assertForbidden();
    $this->actingAs($this->admin)->getJson(route('admin.fields.lookup', ['kind' => 'settings']))->assertUnprocessable();
});

it('lets plugins register field groups', function () {
    $provider = new class(app()) extends PluginServiceProvider
    {
        public function addGroup(array $group): void
        {
            $this->registerFieldGroup($group);
        }
    };

    $provider->addGroup(builderPayload(['key' => 'from_plugin']));

    expect(app(FieldGroupRegistry::class)->find('from_plugin')?->source)->toBe('code');
});

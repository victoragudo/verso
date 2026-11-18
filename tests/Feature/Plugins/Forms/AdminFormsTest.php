<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Models\Submission;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    $this->editor = User::factory()->editor()->create(['name' => 'Teresa Gomes']);
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Saraiva']);
});

// Builder data for a small catering request form.
function cateringFormData(array $overrides = []): array
{
    if (isset($overrides['settings'])) {
        $overrides['settings'] = [...Form::DEFAULT_SETTINGS, ...$overrides['settings']];
    }

    return [
        'title' => 'Catering request',
        'slug' => 'catering-request',
        'fields' => [
            ['id' => 'f_name', 'type' => 'text', 'name' => 'name', 'label' => 'Your name', 'required' => true, 'width' => 'half'],
            ['id' => 'f_email', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'half'],
            ['id' => 'f_guests', 'type' => 'radio', 'name' => 'guests', 'label' => 'Guests', 'required' => true, 'options' => ['Up to 20', '20 to 50', 'More than 50']],
            ['id' => 'f_date', 'type' => 'date', 'name' => 'event_date', 'label' => 'Event date'],
            ['id' => 'f_menu', 'type' => 'file', 'name' => 'menu', 'label' => 'Menu ideas', 'accept' => ['pdf'], 'max_kb' => 1024],
        ],
        'settings' => [
            ...Form::DEFAULT_SETTINGS,
            'submit_label' => 'Ask for a quote',
            'notify_emails' => ['Events@Padaria-Lumiar.pt'],
        ],
        ...$overrides,
    ];
}

it('lists forms with their submission counts', function () {
    $form = bakeryContactForm();
    Submission::query()->create(['form_id' => $form->id, 'data' => ['name' => 'Rui'], 'status' => 'new']);
    Submission::query()->create(['form_id' => $form->id, 'data' => ['name' => 'Bot'], 'status' => 'spam']);

    $this->actingAs($this->editor)
        ->get(route('admin.forms.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/forms/index', false)
            ->has('forms', 1)
            ->where('forms.0.title', 'Contact the bakery')
            ->where('forms.0.fields_count', 5)
            ->where('forms.0.submissions_count', 1)
            ->where('forms.0.new_count', 1)
            ->where('urls.settings', null)
        );
});

it('shows the settings link only to people who manage settings', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.forms.index'))
        ->assertInertia(fn (Assert $page) => $page->where('urls.settings', route('admin.forms.settings.edit')));
});

it('opens an empty builder with the field types and limits', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.forms.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/forms/edit', false)
            ->where('form', null)
            ->has('fieldTypes', 11)
            ->where('fieldTypes.0', ['value' => 'text', 'label' => 'Text'])
            ->where('limits.max_fields', 40)
            ->where('urls.save', route('admin.forms.store'))
        );
});

it('creates a form from the builder', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.forms.store'), cateringFormData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.forms.edit', Form::query()->sole()));

    $form = Form::query()->sole();

    expect($form->title)->toBe('Catering request')
        ->and($form->formFields())->toHaveCount(5)
        ->and($form->field('guests')->options)->toBe(['Up to 20', '20 to 50', 'More than 50'])
        ->and($form->field('menu')->maxKb)->toBe(1024)
        ->and($form->notifyEmails())->toBe(['events@padaria-lumiar.pt'])
        ->and($form->setting('submit_label'))->toBe('Ask for a quote');
});

it('opens a saved form in the builder with the embed code', function () {
    $form = bakeryContactForm();

    $this->actingAs($this->editor)
        ->get(route('admin.forms.edit', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->where('form.slug', 'bakery-contact')
            ->has('form.fields', 5)
            ->where('form.fields.3.min_length', 10)
            ->where('form.settings.notify_emails', ['orders@padaria-lumiar.pt', 'marta@padaria-lumiar.pt'])
            ->where('embed', '<x-forms-form slug="bakery-contact" />')
        );
});

it('updates a form and keeps its slug unique', function () {
    $form = bakeryContactForm();
    $this->actingAs($this->editor)->post(route('admin.forms.store'), cateringFormData())->assertSessionHasNoErrors();

    $this->actingAs($this->editor)
        ->put(route('admin.forms.update', $form), cateringFormData(['title' => 'Bakery contact']))
        ->assertSessionHasErrors(['slug']);

    $this->actingAs($this->editor)
        ->put(route('admin.forms.update', $form), cateringFormData(['title' => 'Bakery contact', 'slug' => 'bakery-contact']))
        ->assertSessionHasNoErrors();

    expect($form->fresh()->title)->toBe('Bakery contact');
});

it('refuses broken builder data', function (array $overrides, string $error) {
    $this->actingAs($this->editor)
        ->post(route('admin.forms.store'), cateringFormData($overrides))
        ->assertSessionHasErrors([$error]);

    expect(Form::query()->count())->toBe(0);
})->with(fn (): array => [
    'no fields' => [['fields' => []], 'fields'],
    'bad slug' => [['slug' => 'Catering Request!'], 'slug'],
    'bad field name' => [['fields' => [['id' => 'a', 'type' => 'text', 'name' => 'Full Name', 'label' => 'Name']]], 'fields.0.name'],
    'unknown type' => [['fields' => [['id' => 'a', 'type' => 'signature', 'name' => 'sign', 'label' => 'Sign']]], 'fields.0.type'],
    'same name twice' => [['fields' => [
        ['id' => 'a', 'type' => 'text', 'name' => 'name', 'label' => 'Name'],
        ['id' => 'b', 'type' => 'email', 'name' => 'name', 'label' => 'Email'],
    ]], 'fields'],
    'select without options' => [['fields' => [['id' => 'a', 'type' => 'select', 'name' => 'topic', 'label' => 'Topic', 'options' => []]]], 'fields.0.options'],
    'file type not allowed' => [['fields' => [['id' => 'a', 'type' => 'file', 'name' => 'cv', 'label' => 'CV', 'accept' => ['exe']]]], 'fields.0.accept.0'],
    'upload too big' => [['fields' => [['id' => 'a', 'type' => 'file', 'name' => 'cv', 'label' => 'CV', 'max_kb' => 999999]]], 'fields.0.max_kb'],
    'bad recipient' => [['settings' => ['notify_emails' => ['not-an-address']]], 'settings.notify_emails.0'],
    'unsafe redirect' => [['settings' => ['redirect_url' => 'javascript:alert(1)']], 'settings.redirect_url'],
]);

it('deletes a form with its submissions and their files', function () {
    Storage::fake('local');
    $form = bakeryContactForm();
    Storage::disk('local')->put('forms/'.$form->id.'/sketch.pdf', '%PDF-1.4');
    Submission::query()->create(['form_id' => $form->id, 'data' => [], 'files' => ['sketch' => ['path' => 'forms/'.$form->id.'/sketch.pdf', 'name' => 'sketch.pdf', 'size' => 8, 'mime' => 'application/pdf']]]);

    $this->actingAs($this->editor)
        ->delete(route('admin.forms.destroy', $form))
        ->assertRedirect(route('admin.forms.index'));

    expect(Form::query()->count())->toBe(0)->and(Submission::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('forms/'.$form->id.'/sketch.pdf');
});

it('keeps forms away from people without the forms capability', function () {
    $author = User::factory()->role(Role::Author)->create();
    $form = bakeryContactForm();

    $this->actingAs($author)->get(route('admin.forms.index'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.forms.store'), cateringFormData())->assertForbidden();
    $this->actingAs($author)->delete(route('admin.forms.destroy', $form))->assertForbidden();
    expect($this->get(route('admin.forms.index'))->status())->toBeIn([302, 403]);
});

it('adds Forms to the admin sidebar', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.forms.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains(
            fn ($item) => $item['key'] === 'plugin.forms' && $item['icon'] === 'forms' && $item['group'] === 'tools',
        ))->where('versoNav', fn ($nav) => collect($nav)->where('parent', 'plugin.forms')->pluck('label')->values()->all() === ['Forms', 'Messages']));
});

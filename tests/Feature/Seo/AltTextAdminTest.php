<?php

use App\Models\User;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\AltTextSuggesterFilter;
use App\Verso\Seo\AltText\AltTextSuggester;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists images without alt text first', function () {
    Media::factory()->create(['alt' => 'Rye loaves on a wooden board']);
    Media::factory()->create(['alt' => null, 'filename' => 'bakery-window.jpg']);
    Media::factory()->create(['alt' => '']);
    Media::factory()->document()->create(['alt' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.seo.alt-text.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/seo/alt-text')
            ->has('images.data', 2)
            ->where('missing', 2)
            ->where('filters', ['show' => 'missing', 'search' => ''])
            ->where('suggester', null));

    $this->actingAs($this->admin)
        ->get(route('admin.seo.alt-text.index', ['show' => 'all', 'search' => 'bakery-window']))
        ->assertInertia(fn (Assert $page) => $page->has('images.data', 1));
});

it('offers suggestions from a plugin', function () {
    app(FilterRegistry::class)->add(AltTextSuggesterFilter::class, fn (?AltTextSuggester $suggester): AltTextSuggester => new AltTextSuggester('Vision helper', '/admin/vision/alt', true));

    $this->actingAs($this->admin)
        ->get(route('admin.seo.alt-text.index'))
        ->assertInertia(fn (Assert $page) => $page->where('suggester', ['label' => 'Vision helper', 'endpoint' => '/admin/vision/alt', 'ready' => true, 'settingsUrl' => null]));
});

it('saves many alt texts at once', function () {
    $window = Media::factory()->create(['alt' => null]);
    $oven = Media::factory()->create(['alt' => null]);

    $this->actingAs($this->admin)
        ->put(route('admin.seo.alt-text.update'), ['items' => [
            ['id' => $window->id, 'alt' => '  Morning light on the <b>bakery</b> window  '],
            ['id' => $oven->id, 'alt' => 'El horno de leña encendido'],
        ]])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.message', 'Saved alt text for 2 images.');

    expect($window->fresh()->alt)->toBe('Morning light on the bakery window')
        ->and($oven->fresh()->alt)->toBe('El horno de leña encendido');
});

it('skips images the user may not change', function () {
    $editor = User::factory()->editor()->create();
    $own = Media::factory()->create(['alt' => null, 'uploaded_by' => $editor->id]);
    $other = Media::factory()->create(['alt' => null]);
    $canEditOthers = $editor->can('update', $other);

    $this->actingAs($editor)->put(route('admin.seo.alt-text.update'), ['items' => [
        ['id' => $own->id, 'alt' => 'Sourdough starter in a jar'],
        ['id' => $other->id, 'alt' => 'Flour sacks'],
    ]])->assertRedirect();

    expect($own->fresh()->alt)->toBe('Sourdough starter in a jar')
        ->and($other->fresh()->alt)->toBe($canEditOthers ? 'Flour sacks' : null);
});

it('validates the alt texts', function () {
    $image = Media::factory()->create(['alt' => null]);

    $this->actingAs($this->admin)->put(route('admin.seo.alt-text.update'), ['items' => []])->assertSessionHasErrors('items');
    $this->actingAs($this->admin)->put(route('admin.seo.alt-text.update'), ['items' => [['id' => $image->id, 'alt' => str_repeat('a', 300)]]])->assertSessionHasErrors('items.0.alt');
    $this->actingAs($this->admin)->put(route('admin.seo.alt-text.update'), ['items' => [['id' => 999999, 'alt' => 'Missing']]])->assertSessionHasErrors('items.0.id');
    $this->actingAs(User::factory()->author()->create())->get(route('admin.seo.alt-text.index'))->assertForbidden();
});

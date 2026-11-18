<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Editor\Styles\StylePreset;
use App\Verso\Editor\Styles\StylePresetChanged;
use App\Verso\Editor\Styles\StylePresets;
use App\Verso\Editor\Styles\StyleValues;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('starts with the core presets', function () {
    $presets = app(StylePresets::class);

    expect(array_keys($presets->all()))->toContain('card', 'hero', 'pill')
        ->and($presets->find('card')->source)->toBe(StylePreset::CORE)
        ->and($presets->design('hero')['background']['size'])->toBe('cover');
});

it('saves a preset with a key made from its name', function () {
    $presets = app(StylePresets::class);
    $first = $presets->save(null, 'Soft card', ['border' => ['radius' => '18px']]);
    $second = $presets->save(null, 'Soft card', ['border' => ['radius' => '24px']]);

    expect($first->key)->toBe('soft-card')
        ->and($second->key)->toBe('soft-card-2')
        ->and($presets->find('soft-card')->source)->toBe(StylePreset::SAVED);
});

it('changes a core preset and goes back to the original on delete', function () {
    $presets = app(StylePresets::class);
    $presets->save('card', 'Card', ['border' => ['radius' => '2px']]);

    expect($presets->find('card'))->changed->toBeTrue()
        ->and($presets->find('card')->source)->toBe(StylePreset::CORE)
        ->and($presets->design('card'))->toBe(['border' => ['radius' => '2px']]);

    $presets->delete('card');

    expect($presets->find('card')->changed)->toBeFalse()
        ->and($presets->design('card')['border']['radius'])->toBe('var:radius.base');
});

it('reads presets from the theme editor settings', function () {
    $presets = new StylePresets(themePresets: fn (): array => [
        'quiet-band' => ['label' => 'Quiet band', 'design' => ['color' => ['background' => '#eceee8']]],
        'Bad Key' => ['label' => 'Broken', 'design' => []],
    ]);

    expect(array_keys($presets->all()))->toBe(['quiet-band'])
        ->and($presets->find('quiet-band')->source)->toBe(StylePreset::THEME);
});

it('refuses more saved presets than the limit', function () {
    $presets = new StylePresets(settings: app(Settings::class), maxSaved: 1);
    $presets->save(null, 'Harbor card', ['border' => ['radius' => '8px']]);

    expect(fn () => $presets->save(null, 'Market card', ['border' => ['radius' => '4px']]))
        ->toThrow(InvalidArgumentException::class);
});

it('prints the preset styles under the block styles on the site', function () {
    app(StylePresets::class)->save('bakery-card', 'Bakery card', ['color' => ['background' => '#F7F3EC'], 'border' => ['radius' => '14px']]);

    $html = app(BlockRenderer::class)->render([[
        'id' => 'b_menu1', 'type' => 'paragraph', 'content' => 'Sourdough every morning.',
        'attrs' => ['preset' => 'bakery-card', 'design' => ['border' => ['radius' => '4px']]],
    ]]);

    expect($html)->toContain('background-color:#f7f3ec')
        ->toContain('border-radius:4px')
        ->not->toContain('border-radius:14px');
});

it('accepts a preset name on any block and refuses odd names', function (mixed $preset, bool $passes) {
    $blocks = [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'Fresh bread.', 'attrs' => ['preset' => $preset]]];
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($this->admin)]]);

    expect($validator->passes())->toBe($passes);
})->with([
    'core preset' => ['card', true],
    'missing preset still saves' => ['old-ribbon', true],
    'upper case' => ['Card', false],
    'css text' => ['card;color:red', false],
    'number' => [12, false],
]);

it('sends the presets and the edit right to the editor', function () {
    $manifest = app(EditorManifest::class)->for($this->admin);
    $author = app(EditorManifest::class)->for(User::factory()->author()->create());

    expect(array_column($manifest['stylePresets'], 'key'))->toContain('card', 'hero')
        ->and($manifest['canEditPresets'])->toBeTrue()
        ->and($author['canEditPresets'])->toBeFalse();
});

it('saves, changes and deletes presets through the admin routes', function () {
    Event::fake([StylePresetChanged::class]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.editor.presets.store'), ['label' => 'Menu board', 'design' => ['typography' => ['fontSize' => '1.25rem']]])
        ->assertOk()
        ->assertJsonPath('preset.key', 'menu-board');

    $this->putJson(route('admin.editor.presets.update', 'menu-board'), ['label' => 'Menu board', 'design' => ['typography' => ['fontSize' => '1.5rem']]])
        ->assertOk()
        ->assertJsonPath('preset.design.typography.fontSize', '1.5rem');

    $this->deleteJson(route('admin.editor.presets.destroy', 'menu-board'))->assertOk();

    expect(app(StylePresets::class)->find('menu-board'))->toBeNull();
    Event::assertDispatchedTimes(StylePresetChanged::class, 3);
});

it('refuses styles the schema does not allow', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.editor.presets.store'), ['label' => 'Broken', 'design' => ['color' => ['background' => 'red; display:none']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('design');
});

it('keeps presets for people who may edit styles', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->getJson(route('admin.editor.presets.index'))->assertForbidden();
    $this->actingAs($editor)->postJson(route('admin.editor.presets.store'), ['label' => 'Sneaky', 'design' => []])->assertForbidden();
});

it('empties the caches when a preset changes', function () {
    $entry = Entry::factory()->published()->create([
        'type' => 'page',
        'slug' => 'opening-hours',
        'title' => 'Opening hours',
        'blocks' => [['id' => 'b_hours', 'type' => 'paragraph', 'content' => 'Open from 7am.', 'attrs' => ['preset' => 'card']]],
    ]);

    $before = $this->get($entry->url())->assertOk()->getContent();
    app(StylePresets::class)->save('card', 'Card', ['color' => ['background' => '#123456']]);
    StylePresetChanged::dispatch('card');

    expect($before)->not->toContain('#123456')
        ->and($this->get($entry->url())->getContent())->toContain('background-color:#123456');
});

it('checks gradients, pictures, focal points and stack order', function (string $method, mixed $value, ?string $css) {
    expect(StyleValues::{$method}($value))->toBe($css);
})->with([
    'linear gradient' => ['gradient', 'linear-gradient(90deg,#1F6F5C 0%,#f4efe6 100%)', 'linear-gradient(90deg, #1f6f5c 0%, #f4efe6 100%)'],
    'gradient without places' => ['gradient', 'radial-gradient(ellipse, #fff, #000)', 'radial-gradient(ellipse, #fff, #000)'],
    'angle above a full turn' => ['gradient', 'linear-gradient(400deg, #fff, #000)', null],
    'radial with an angle' => ['gradient', 'radial-gradient(90deg, #fff, #000)', null],
    'one color only' => ['gradient', 'linear-gradient(90deg, #fff)', null],
    'six colors' => ['gradient', 'linear-gradient(90deg, #111, #222, #333, #444, #555, #666)', null],
    'url inside a gradient' => ['gradient', 'linear-gradient(90deg, url(x), #000)', null],
    'site path' => ['image', '/storage/media/2026/11/rye-loaf.webp', 'url("/storage/media/2026/11/rye-loaf.webp")'],
    'javascript address' => ['image', 'javascript:alert(1)', null],
    'quote in a path' => ['image', '/storage/a".jpg', null],
    'space in a path' => ['image', '/storage/rye loaf.jpg', null],
    'focal point' => ['focal', '25%   80%', '25% 80%'],
    'focal point with words' => ['focal', 'left top', null],
    'stack order' => ['zIndex', 20, '20'],
    'stack order too high' => ['zIndex', 20000, null],
]);

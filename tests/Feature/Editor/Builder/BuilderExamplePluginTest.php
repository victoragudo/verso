<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Builder\BlockCategoryRegistry;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\PluginsServiceProvider;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    PluginFixtures::use(base_path('plugins'));
    app(Settings::class)->set('plugins.enabled', ['builder-example']);
    app()->forgetInstance(PluginBlockRegistry::class);
    (new PluginsServiceProvider(app()))->registerEnabledPlugins();
});

function notice(string $message = 'Closed on public holidays', string $tone = 'warning'): array
{
    return ['id' => 'b_notice', 'type' => 'example-notice', 'attrs' => ['message' => $message, 'tone' => $tone]];
}

it('adds the notice block in its own category', function () {
    $definition = collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'example-notice');

    expect($definition['category'])->toBe('examples')
        ->and(app(BlockCategoryRegistry::class)->find('examples')?->label)->toBe('Examples')
        ->and(app(BlockRenderer::class)->render([notice()]))
        ->toBe('<p class="example-notice example-notice--warning" role="note">Closed on public holidays</p>');
});

it('adds an opacity style that renders on the site', function () {
    $html = app(BlockRenderer::class)->render([[...notice(), 'attrs' => [...notice()['attrs'], 'design' => ['effects' => ['opacity' => 0.8]]]]]);

    expect(app(StylePropertyRegistry::class)->find('effects.opacity')?->label)->toBe('Opacity')
        ->and($html)->toContain('opacity:0.8');
});

it('adds a rotate style that takes any angle unit', function () {
    $html = app(BlockRenderer::class)->render([[...notice(), 'attrs' => [...notice()['attrs'], 'design' => ['effects' => ['rotate' => '-2deg']]]]]);

    expect(app(StylePropertyRegistry::class)->find('effects.rotate')?->value?->units)->toBe(['deg', 'rad', 'grad', 'turn'])
        ->and($html)->toContain('rotate:-2deg');
});

it('keeps notices out of columns', function () {
    $blocks = [['id' => 'b_cols', 'type' => 'columns', 'attrs' => [], 'children' => [
        ['id' => 'b_col', 'type' => 'column', 'attrs' => [], 'children' => [notice()]],
    ]]];
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [ValidBlocks::trusted()]]);

    expect($validator->errors()->first('blocks'))
        ->toBe('Block 1.1.1: a "example-notice" block cannot be placed inside a "column" block.');
});

it('lets the client module of the enabled plugin run', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', 'page'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('editor.modules', fn ($modules) => collect($modules)->contains('plugins/builder-example'))
            ->where('editor.styleProperties', fn ($properties) => collect($properties)->contains('path', 'effects.opacity')));
});

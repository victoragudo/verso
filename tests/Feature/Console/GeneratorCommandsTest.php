<?php

use App\Blocks\PullQuoteBlock;
use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Filters\BodyClassFilter;
use App\Verso\Plugins\PluginManifest;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Theme\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/generators-'.Str::lower(Str::random(8)));
    config([
        'verso.plugins.paths' => [$this->sandbox.'/plugins'],
        'verso.generators.themes_path' => $this->sandbox.'/themes',
        'verso.generators.blocks_path' => $this->sandbox.'/app/Blocks',
        'verso.generators.blocks_namespace' => 'App\\Blocks',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('creates a plugin that Verso can discover and enable', function () {
    $this->artisan('verso:make-plugin', ['name' => 'Harbor Weather', '--description' => 'Shows the sea forecast.', '--author' => 'Inês Duarte'])
        ->expectsOutputToContain('plugins/harbor-weather/verso.json')
        ->assertSuccessful();

    $folder = $this->sandbox.'/plugins/harbor-weather';
    $manifest = PluginManifest::fromFile($folder.'/verso.json');

    expect($manifest->name)->toBe('Harbor Weather')
        ->and($manifest->description)->toBe('Shows the sea forecast.')
        ->and($manifest->provider)->toBe('Verso\\Plugins\\HarborWeather\\HarborWeatherServiceProvider')
        ->and($manifest->requires)->toBe('^0.1')
        ->and($manifest->supports(config('verso.version')))->toBeTrue()
        ->and(File::get($folder.'/README.md'))->toContain('# Harbor Weather')
        ->and(File::exists($folder.'/src/HarborWeatherServiceProvider.php'))->toBeTrue();

    $plugins = PluginFixtures::use($this->sandbox.'/plugins');

    expect($plugins->enable('harbor-weather')->enabled)->toBeTrue();
});

it('creates a plugin that shows one filter and one admin slot', function () {
    $this->artisan('verso:make-plugin', ['name' => 'Harbor Weather'])->assertSuccessful();

    $folder = $this->sandbox.'/plugins/harbor-weather';
    $plugins = PluginFixtures::use($this->sandbox.'/plugins');
    $plugins->enable('harbor-weather');
    $plugins->register($plugins->get('harbor-weather'));

    $widget = app(AdminSlotRegistry::class)->all(AdminSlot::DashboardWidgets)[0] ?? null;

    expect(BodyClassFilter::apply(['home'], Request::create('/')))->toBe(['home', 'with-harbor-weather'])
        ->and($widget?->component)->toBe('harbor-weather.widget')
        ->and($widget?->label)->toBe('Harbor Weather')
        ->and(File::get($folder.'/resources/js/index.tsx'))
        ->toContain("registry.registerSlotComponent('harbor-weather.widget', Widget);")
        ->and(File::get($folder.'/README.md'))->toContain('npm run build');
});

it('creates a plugin with an admin page under Tools', function () {
    $this->artisan('verso:make-plugin', ['name' => 'Harbor Weather'])->assertSuccessful();

    $folder = $this->sandbox.'/plugins/harbor-weather';
    $plugins = PluginFixtures::use($this->sandbox.'/plugins');
    $plugins->enable('harbor-weather');
    $plugins->register($plugins->get('harbor-weather'));
    app('router')->getRoutes()->refreshNameLookups();

    $manifest = PluginManifest::fromFile($folder.'/verso.json');
    $row = collect(AdminNav::forUser(User::factory()->admin()->create()))->firstWhere('key', 'harbor-weather.overview');

    expect($manifest->admin?->group->value)->toBe('tools')
        ->and(File::get($folder.'/routes/admin.php'))->toContain("->name('harbor-weather.index')")
        ->and($row)->toMatchArray(['parent' => 'plugin.harbor-weather', 'href' => '/admin/tools/harbor-weather'])
        ->and(AdminNav::path('harbor-weather.overview'))->toBe(['Tools', 'Harbor Weather', 'Overview']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/tools/harbor-weather')
        ->assertRedirect('/admin/plugins/harbor-weather');
});

it('refuses to overwrite a plugin without --force', function () {
    $this->artisan('verso:make-plugin', ['name' => 'Tide Tables'])->assertSuccessful();
    File::put($this->sandbox.'/plugins/tide-tables/verso.json', '{"edited": true}');

    $this->artisan('verso:make-plugin', ['name' => 'Tide Tables'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(File::get($this->sandbox.'/plugins/tide-tables/verso.json'))->toBe('{"edited": true}');

    $this->artisan('verso:make-plugin', ['name' => 'Tide Tables', '--force' => true])->assertSuccessful();

    expect(File::get($this->sandbox.'/plugins/tide-tables/verso.json'))->toContain('"name": "Tide Tables"');
});

it('rejects plugin names without letters or numbers', function () {
    $this->artisan('verso:make-plugin', ['name' => '!!!'])->assertFailed();

    expect(File::exists($this->sandbox.'/plugins'))->toBeFalse();
});

it('creates a block class in the app', function () {
    $this->artisan('verso:make-block', ['name' => 'Pull Quote'])->assertSuccessful();

    $file = $this->sandbox.'/app/Blocks/PullQuoteBlock.php';
    $source = File::get($file);

    expect($source)->toContain('namespace App\\Blocks;')
        ->toContain('class PullQuoteBlock implements PluginBlock')
        ->toContain("return 'pull-quote';")
        ->toContain("return 'Pull Quote';");

    require_once $file;
    $block = new PullQuoteBlock;

    expect($block)->toBeInstanceOf(PluginBlock::class)
        ->and($block->render(['type' => 'pull-quote', 'content' => 'Less, but better <3']))
        ->toBe('<div class="block-pull-quote">Less, but better &lt;3</div>');
});

it('creates a block inside a plugin', function () {
    $this->artisan('verso:make-plugin', ['name' => 'Recipe Cards'])->assertSuccessful();
    PluginFixtures::use($this->sandbox.'/plugins');

    $this->artisan('verso:make-block', ['name' => 'Ingredient List', '--plugin' => 'recipe-cards'])->assertSuccessful();

    expect(File::get($this->sandbox.'/plugins/recipe-cards/src/Blocks/IngredientListBlock.php'))
        ->toContain('namespace Verso\\Plugins\\RecipeCards\\Blocks;');
});

it('fails for an unknown plugin', function () {
    $this->artisan('verso:make-block', ['name' => 'Ingredient List', '--plugin' => 'cookbook'])
        ->expectsOutputToContain('was not found')
        ->assertFailed();
});

it('rejects block names without letters or numbers', function () {
    $this->artisan('verso:make-block', ['name' => '---'])->assertFailed();
});

it('creates a theme with every required view', function () {
    $this->artisan('verso:make-theme', ['name' => 'Harbor', '--description' => 'A calm theme for travel writers.'])->assertSuccessful();

    $folder = $this->sandbox.'/themes/harbor';
    $manifest = json_decode(File::get($folder.'/theme.json'), true);

    expect($manifest)->toMatchArray(['name' => 'Harbor', 'slug' => 'harbor', 'description' => 'A calm theme for travel writers.']);

    foreach (['layouts/app', 'home', 'entry', 'archive', 'taxonomy', 'search', '404'] as $view) {
        expect(File::exists($folder."/views/{$view}.blade.php"))->toBeTrue();
    }

    expect(File::get($folder.'/views/layouts/app.blade.php'))->toContain('theme-harbor')->toContain('<x-verso-head />')->toContain("@yield('content')");
});

it('renders the generated theme views', function () {
    $this->artisan('verso:make-theme', ['name' => 'Lighthouse'])->assertSuccessful();
    view()->replaceNamespace('theme', [$this->sandbox.'/themes/lighthouse/views']);

    $html = view('theme::404')->render();

    expect($html)->toContain('<h1>Page not found</h1>')->toContain('class="theme-lighthouse"');
});

it('refuses to overwrite a theme without --force', function () {
    $this->artisan('verso:make-theme', ['name' => 'Harbor'])->assertSuccessful();

    $this->artisan('verso:make-theme', ['name' => 'Harbor'])->assertFailed();
    $this->artisan('verso:make-theme', ['name' => 'Harbor', '--force' => true])->assertSuccessful();
});

it('rejects theme names without letters or numbers', function () {
    $this->artisan('verso:make-theme', ['name' => '???'])->assertFailed();
});

describe('child themes', function () {
    it('creates a child theme of a theme in the same folder', function () {
        $this->artisan('verso:make-theme', ['name' => 'Harbor'])->assertSuccessful();

        $this->artisan('verso:make-theme', ['name' => 'Harbor Dusk', '--parent' => 'harbor', '--author' => 'Linnea Berg'])
            ->expectsOutputToContain('Child theme Harbor Dusk of harbor created')
            ->assertSuccessful();

        $folder = $this->sandbox.'/themes/harbor-dusk';
        $manifest = json_decode(File::get($folder.'/theme.json'), true);

        expect($manifest)->toMatchArray([
            'name' => 'Harbor Dusk',
            'slug' => 'harbor-dusk',
            'parent' => 'harbor',
            'description' => 'A child theme of harbor.',
            'author' => 'Linnea Berg',
            'templates' => [],
            'settings' => [],
        ])
            ->and(File::get($folder.'/assets/theme.css'))->toContain('a child theme of harbor')
            ->and(File::get($folder.'/README.md'))->toContain('A child theme of `harbor`')
            ->and(File::exists($folder.'/views/home.blade.php'))->toBeFalse();
    });

    it('creates a child theme of an installed theme', function () {
        $this->artisan('verso:make-theme', ['name' => 'Quiet Reading', '--parent' => 'default', '--description' => 'Default with larger type.'])
            ->assertSuccessful();

        $manifest = json_decode(File::get($this->sandbox.'/themes/quiet-reading/theme.json'), true);

        expect($manifest['parent'])->toBe('default')
            ->and($manifest['description'])->toBe('Default with larger type.');
    });

    it('makes a child theme that Verso reads with its parent', function () {
        $this->artisan('verso:make-theme', ['name' => 'Harbor'])->assertSuccessful();
        $this->artisan('verso:make-theme', ['name' => 'Harbor Dusk', '--parent' => 'harbor'])->assertSuccessful();

        $theme = Theme::fromDirectory($this->sandbox.'/themes/harbor-dusk');

        expect($theme->parent)->toBe('harbor')->and($theme->isChild())->toBeTrue();
    });

    it('fails for a parent that is not installed', function () {
        $this->artisan('verso:make-theme', ['name' => 'Harbor Dusk', '--parent' => 'harbor'])
            ->expectsOutputToContain('The parent theme [harbor] was not found.')
            ->assertFailed();

        expect(File::exists($this->sandbox.'/themes/harbor-dusk'))->toBeFalse();
    });

    it('refuses an unsafe parent or the theme itself as parent', function (string $parent) {
        $this->artisan('verso:make-theme', ['name' => 'Harbor', '--parent' => $parent])
            ->expectsOutputToContain('Give --parent the slug of another theme')
            ->assertFailed();
    })->with(['../default', 'Default Theme', 'harbor', '']);

    it('refuses to overwrite a child theme without --force', function () {
        $this->artisan('verso:make-theme', ['name' => 'Night Mode', '--parent' => 'default'])->assertSuccessful();

        $this->artisan('verso:make-theme', ['name' => 'Night Mode', '--parent' => 'default'])->assertFailed();
        $this->artisan('verso:make-theme', ['name' => 'Night Mode', '--parent' => 'default', '--force' => true])->assertSuccessful();
    });
});

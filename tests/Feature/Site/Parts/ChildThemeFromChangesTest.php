<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Parts\ChildThemeFromChanges;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    config(['verso.extensions.verification.sandbox.enabled' => false]);
    $this->sandbox = ThemeSandbox::create();
    File::copyDirectory(base_path('themes/ashgrove'), $this->sandbox->root.'/ashgrove');
    $this->sandbox->activate('ashgrove');
    $this->admin = User::factory()->admin()->create(['name' => 'Maja Lindqvist']);
    $this->ashgrove = app(ThemeManager::class)->find('ashgrove');

    app(SitePartStore::class)->publish('ashgrove', 'header', [[
        'id' => 'row', 'type' => 'section', 'attrs' => [],
        'children' => [['id' => 'logo', 'type' => 'site-logo'], ['id' => 'scheme', 'type' => 'color-scheme-toggle']],
    ]]);
    app(SitePartStore::class)->saveDraft('ashgrove', 'footer', [['id' => 'c', 'type' => 'copyright']]);
    app(ThemeCustomizer::class)->save($this->ashgrove, ['accent_color' => '#7a3b2e']);
    app(GlobalStyles::class)->save($this->ashgrove, ['color.surface' => '#efe8f7']);
    app(PartVariants::class)->choose($this->ashgrove, 'footer', 'four-columns');
});

afterEach(function () {
    $this->sandbox->destroy();
});

function childRoot(string $slug): string
{
    return test()->sandbox->root.'/'.$slug;
}

it('writes a child theme with the published parts and the changed styles', function () {
    $child = app(ChildThemeFromChanges::class)->handle($this->ashgrove, 'Ashgrove Bakery', 'ashgrove-bakery', 'Maja Lindqvist');
    $manifest = Theme::readManifest(childRoot('ashgrove-bakery'));

    expect($child)->toBeInstanceOf(Theme::class)
        ->and($child->parent)->toBe('ashgrove')
        ->and($manifest)->toMatchArray(['name' => 'Ashgrove Bakery', 'slug' => 'ashgrove-bakery', 'version' => '1.0.0', 'author' => 'Maja Lindqvist', 'requires' => '^0.1', 'php' => '^8.4', 'parent' => 'ashgrove'])
        ->and($manifest['settings']['accent_color']['default'])->toBe('#7a3b2e')
        ->and($manifest['settings']['accent_color']['type'])->toBe('color')
        ->and($manifest['styles'])->toBe(['color' => ['surface' => ['default' => '#efe8f7']]])
        ->and(json_decode(File::get(childRoot('ashgrove-bakery').'/parts/header.json'), true)['blocks'][0]['children'][1]['type'])->toBe('color-scheme-toggle')
        ->and(File::get(childRoot('ashgrove-bakery').'/views/parts/header.blade.php'))->toContain('<x-verso-part-blocks part="header" key="header" />')
        ->and(File::exists(childRoot('ashgrove-bakery').'/parts/footer.json'))->toBeFalse()
        ->and(File::exists(childRoot('ashgrove-bakery').'/screenshot.png'))->toBeTrue()
        ->and(File::get(childRoot('ashgrove-bakery').'/assets/theme.css'))->toContain('--accent-light: #7a3b2e;')->toContain('--surface-light: #efe8f7;')
        ->and(File::get(childRoot('ashgrove-bakery').'/README.md'))->toContain('- `header`')
        ->and(app(PartVariants::class)->choices($child))->toBe(['header' => 'classic', 'footer' => 'four-columns']);
});

it('passes the theme check without findings that block it', function () {
    app(ChildThemeFromChanges::class)->handle($this->ashgrove, 'Ashgrove Bakery', 'ashgrove-bakery');

    $this->artisan('verso:verify-theme', ['path' => childRoot('ashgrove-bakery'), '--no-sandbox' => true])
        ->assertSuccessful();
});

it('looks the same once it is active', function () {
    $before = $this->get('/')->assertOk()->getContent();
    app(ChildThemeFromChanges::class)->handle($this->ashgrove, 'Ashgrove Bakery', 'ashgrove-bakery');
    $this->sandbox->activate('ashgrove-bakery');
    app()->forgetScopedInstances();

    $after = $this->get('/')->assertOk()->getContent();

    expect($before)->toContain('data-verso-scheme-toggle')
        ->and($after)->toContain('<header class="site-part site-part-header" data-site-part="header">')
        ->and($after)->toContain('data-verso-scheme-toggle')
        ->and($after)->toContain('--accent-light: #7a3b2e')
        ->and($after)->toContain('--surface-light: #efe8f7');
});

it('refuses folder names that are taken or not allowed', function () {
    $builder = app(ChildThemeFromChanges::class);

    expect($builder->slugProblem('default'))->toBe('A theme with this folder name already exists.')
        ->and($builder->slugProblem('Bad Name'))->toBe('Use lowercase letters, numbers and dashes for the folder name.')
        ->and($builder->slugProblem('ashgrove-bakery'))->toBeNull();

    expect(fn () => $builder->handle($this->ashgrove, 'Default copy', 'default'))->toThrow(InvalidArgumentException::class);
});

it('creates, checks and can switch to the theme from the site editor', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.site-editor.child-theme'), ['name' => 'Ashgrove Bakery', 'activate' => true])
        ->assertRedirect(route('admin.themes.show', 'ashgrove-bakery'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(app(Settings::class)->get('theme.active'))->toBe('ashgrove-bakery')
        ->and(File::exists(childRoot('ashgrove-bakery').'/theme.json'))->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('admin.themes.show', 'ashgrove-bakery'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('verification.risk', 'low'));
});

it('reports a taken folder and keeps people without theme rights out', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.site-editor'))
        ->post(route('admin.site-editor.child-theme'), ['name' => 'Default'])
        ->assertRedirect(route('admin.site-editor'))
        ->assertSessionHasErrors('slug');

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.site-editor.child-theme'), ['name' => 'Sneaky theme'])
        ->assertForbidden();

    expect(File::exists(childRoot('sneaky-theme')))->toBeFalse();
});

it('renders nothing for a block part without its file', function () {
    expect(Blade::render('<x-verso-part-blocks part="header" key="header-missing" />'))->toBe('');
});

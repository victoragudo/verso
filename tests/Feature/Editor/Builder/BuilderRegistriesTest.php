<?php

use App\Models\User;
use App\Verso\Core\Css\CssUnits;
use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Builder\BlockCategory;
use App\Verso\Editor\Builder\BlockCategoryRegistry;
use App\Verso\Editor\Builder\DropRule;
use App\Verso\Editor\Builder\DropRuleRegistry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Builder\EditorFeature;
use App\Verso\Editor\Builder\EditorFeatureRegistry;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Builder\Registry;
use App\Verso\Editor\Builder\StyleProperty;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\Editor\Builder\ThemeEditorLimits;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Editor\Styles\BlockStyleCompiler;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\Validator;

// A drop rule that keeps buttons out of sections, like a theme could add.
function noButtonsInSections(): DropRule
{
    return new class implements DropRule
    {
        public function allows(string $parent, string $child): ?bool
        {
            return $parent === 'section' && $child === 'button' ? false : null;
        }
    };
}

// Theme limits read from a theme.json "editor" object.
function themeLimits(array $editor): ThemeEditorLimits
{
    $themes = Mockery::mock(ThemeManager::class);
    $themes->shouldReceive('active')->andReturn(new Theme(slug: 'harbor', name: 'Harbor', path: '/themes/harbor', editor: $editor));

    return new ThemeEditorLimits($themes, new CssValueParser);
}

function validationError(array $blocks, ValidBlocks $rule): ?string
{
    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [$rule]]);

    return $validator->fails() ? $validator->errors()->first('blocks') : null;
}

it('registers the core block categories in order', function () {
    expect(array_column(app(BlockCategoryRegistry::class)->toArray(), 'key'))
        ->toBe(['text', 'media', 'interactive', 'layout', 'site', 'dynamic', 'advanced', 'plugins']);
});

it('lets a plugin add a category that sorts by order and label', function () {
    $categories = app(BlockCategoryRegistry::class);
    $categories->register(new BlockCategory('commerce', 'Shop', 25));
    $categories->register(new BlockCategory('booking', 'Bookings', 25));

    expect(array_column($categories->toArray(), 'label'))
        ->toBe(['Text', 'Media', 'Bookings', 'Interactive', 'Shop', 'Layout', 'Site', 'Page data', 'Advanced', 'From plugins']);
});

it('registers every core style option with a control', function () {
    $properties = app(StylePropertyRegistry::class);

    expect($properties->keys())->toBe(array_keys(BlockStyleSchema::OPTIONS))
        ->and($properties->find('spacing.padding.top')?->toArray())->toBe([
            'path' => 'spacing.padding.top',
            'kind' => 'length',
            'css' => 'padding-top',
            'label' => 'Padding top',
            'group' => 'spacing',
            'control' => 'length',
            'options' => [],
            'value' => ['type' => 'length', 'units' => CssUnits::lengths(), 'keywords' => [], 'unitless' => false, 'negative' => false, 'custom' => true],
        ])
        ->and($properties->find('layout.justify')?->control)->toBe('select')
        ->and($properties->find('layout.justify')?->options())->toBe(['start', 'center', 'end', 'between', 'around', 'evenly'])
        ->and($properties->find('color.background')?->control)->toBe('color')
        ->and($properties->find('visibility.hidden')?->control)->toBe('toggle')
        ->and($properties->find('typography.fontWeight')?->label)->toBe('Font weight');
});

it('validates and renders a style property added by a plugin', function () {
    app(StylePropertyRegistry::class)->register(new StyleProperty(
        path: 'effects.opacity',
        cssProperty: 'opacity',
        kind: 'number',
        label: 'Opacity',
        group: 'effects',
        control: 'number',
    ));
    $schema = app(BlockStyleSchema::class);

    expect($schema->errors(['effects' => ['opacity' => 0.8]]))->toBe([])
        ->and($schema->errors(['effects' => ['opacity' => 9]]))->toBe(['effects.opacity: the value is not allowed.'])
        ->and(app(BlockStyleCompiler::class)->compile(['design' => ['effects' => ['opacity' => 0.8]]])?->css)->toContain('opacity:0.8');
});

it('gives a plugin size property its own units and keywords', function () {
    app(StylePropertyRegistry::class)->register(new StyleProperty(
        path: 'effects.rotate',
        cssProperty: 'rotate',
        kind: 'angle',
        label: 'Rotate',
        group: 'effects',
        control: 'length',
    ));
    app(StylePropertyRegistry::class)->register(new StyleProperty(
        path: 'effects.outlineOffset',
        cssProperty: 'outline-offset',
        kind: 'length',
        label: 'Outline offset',
        group: 'effects',
        control: 'length',
        value: new CssValueRule(units: ['px', 'rem'], custom: false),
    ));
    $schema = app(BlockStyleSchema::class);
    $sent = array_column(app(StylePropertyRegistry::class)->toArray(), 'value', 'path');

    expect($sent['effects.rotate']['units'])->toBe(['deg', 'rad', 'grad', 'turn'])
        ->and($sent['effects.outlineOffset']['units'])->toBe(['px', 'rem'])
        ->and($schema->errors(['effects' => ['rotate' => '-0.25turn', 'outlineOffset' => '0.5rem']]))->toBe([])
        ->and($schema->errors(['effects' => ['rotate' => '12px']]))->toBe(['effects.rotate: the value is not allowed.'])
        ->and($schema->errors(['effects' => ['outlineOffset' => 'calc(1px + 1rem)']]))->toBe(['effects.outlineOffset: the value is not allowed.'])
        ->and(app(BlockStyleCompiler::class)->compile(['design' => ['effects' => ['rotate' => '45DEG']]])?->css)->toContain('rotate:45deg');
});

it('refuses unsafe style properties', function (string $path, string $css, string $kind) {
    expect(fn () => new StyleProperty($path, $css, $kind, 'Bad', 'effects', 'text'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'path with a script' => ['effects.<script>', 'opacity', 'number'],
    'css with a semicolon' => ['effects.opacity', 'opacity;color', 'number'],
    'unknown kind' => ['effects.opacity', 'opacity', 'raw'],
]);

it('asks drop rules before the parent block', function () {
    app(DropRuleRegistry::class)->register('harbor/no-buttons-in-sections', noButtonsInSections());
    $blocks = [['id' => 'b_s1', 'type' => 'section', 'attrs' => [], 'children' => [
        ['id' => 'b_b1', 'type' => 'button', 'attrs' => ['text' => 'Book a table', 'url' => '/book']],
    ]]];

    expect(validationError($blocks, ValidBlocks::trusted()))
        ->toBe('Block 1.1: a "button" block cannot be placed inside a "section" block.')
        ->and(app(BlockRenderer::class)->render($blocks))->not->toContain('Book a table');
});

it('lets a later drop rule allow what the parent refuses', function () {
    app(DropRuleRegistry::class)->register('columns-in-columns', new class implements DropRule
    {
        public function allows(string $parent, string $child): ?bool
        {
            return $parent === 'column' && $child === 'columns' ? true : null;
        }
    });
    $blocks = [['id' => 'b_c1', 'type' => 'columns', 'attrs' => [], 'children' => [
        ['id' => 'b_c1a', 'type' => 'column', 'attrs' => [], 'children' => [
            ['id' => 'b_c2', 'type' => 'columns', 'attrs' => [], 'children' => []],
        ]],
    ]]];

    expect(validationError($blocks, ValidBlocks::trusted()))->toBeNull();
});

it('refuses blocks that are turned off for the content', function () {
    $blocks = [
        ['id' => 'b_h1', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Opening hours'],
        ['id' => 'b_v1', 'type' => 'video', 'attrs' => ['src' => 'https://cdn.example.org/tour.mp4']],
    ];

    expect(validationError($blocks, new ValidBlocks(trusted: true, allowedBlocks: ['heading', 'paragraph'])))
        ->toBe('Block 2: "video" blocks are turned off here.')
        ->and(validationError($blocks, new ValidBlocks(trusted: true, allowedBlocks: null)))->toBeNull();
});

it('keeps a turned off block that was already saved unchanged', function () {
    $video = ['id' => 'b_v1', 'type' => 'video', 'attrs' => ['src' => 'https://cdn.example.org/tour.mp4'], 'content' => ''];

    expect(validationError([$video], new ValidBlocks(trusted: true, stored: [$video], allowedBlocks: ['paragraph'])))->toBeNull();
});

it('reads the theme editor limits and drops unsafe values', function () {
    $limits = themeLimits([
        'blocks' => ['paragraph', 'heading', '<b>'],
        'colors' => ['#1f6f5c', 'red;', '#fff'],
        'fonts' => ['body', 'heading'],
        'spacing' => ['1rem', '2rem', 'clamp(1rem, 4cqi, 3rem)', 'url(x)', '-1rem', 'red'],
        'units' => ['px', 'REM', '%', 'furlong', 'px'],
    ]);

    expect($limits->toArray())->toBe([
        'blocks' => ['paragraph', 'heading'],
        'colors' => ['#1f6f5c', '#fff'],
        'fonts' => ['body', 'heading'],
        'spacing' => ['1rem', '2rem', 'clamp(1rem, 4cqi, 3rem)'],
        'units' => ['px', 'rem', '%'],
    ])
        ->and(themeLimits([])->blocks())->toBeNull();
});

it('gives features to the roles from config until settings are saved', function () {
    $access = app(EditorAccess::class);
    $author = User::factory()->author()->create();
    $editor = User::factory()->editor()->create();

    expect($access->enabled('code_editor', $editor))->toBeTrue()
        ->and($access->enabled('code_editor', $author))->toBeFalse()
        ->and($access->enabled('add_panel', $author, 'post'))->toBeTrue()
        ->and($access->enabled('custom_js', $editor))->toBeFalse()
        ->and($access->enabled('unknown_feature', $editor))->toBeFalse()
        ->and($access->enabled('add_panel', null))->toBeFalse();
});

it('uses saved feature choices per role and content type', function () {
    $access = app(EditorAccess::class);
    $author = User::factory()->author()->create();
    $access->save(['code_editor' => ['roles' => ['admin', 'author'], 'types' => ['page']]], []);

    expect($access->enabled('code_editor', $author, 'page'))->toBeTrue()
        ->and($access->enabled('code_editor', $author, 'post'))->toBeFalse()
        ->and($access->enabled('code_editor', User::factory()->editor()->create(), 'page'))->toBeFalse();
});

it('combines the saved block list with the theme block list', function () {
    app()->instance(ThemeEditorLimits::class, themeLimits(['blocks' => ['paragraph', 'heading', 'image']]));
    $access = app(EditorAccess::class);
    $access->save([], ['post' => ['paragraph', 'image', 'video']]);

    expect($access->allowedBlocks('post'))->toBe(['paragraph', 'image'])
        ->and($access->allowedBlocks('page'))->toBe(['paragraph', 'heading', 'image']);
});

it('builds the editor manifest for a user and content type', function () {
    app(EditorAccess::class)->save([], ['page' => ['paragraph', 'heading']]);
    $manifest = app(EditorManifest::class)->for(User::factory()->author()->create(), 'page');

    expect($manifest['version'])->toBe(Registry::CONTRACT_VERSION)
        ->and($manifest['allowedBlocks'])->toBe(['paragraph', 'heading'])
        ->and($manifest['features'])->toBe(['add_panel', 'list_view', 'styles_panel'])
        ->and($manifest['breakpoints'])->toBe([
            ['key' => 'base', 'maxWidth' => null],
            ['key' => 'tablet', 'maxWidth' => 1024],
            ['key' => 'mobile', 'maxWidth' => 640],
        ])
        ->and(array_column($manifest['categories'], 'key'))->toContain('layout')
        ->and($manifest['limits'])->toHaveKeys(['colors', 'fonts', 'spacing', 'units']);
});

it('lets a plugin add an editor feature', function () {
    app(EditorFeatureRegistry::class)->register(new EditorFeature('ai_writer', 'Writing help', 'Suggest texts.', ['admin']));
    $admin = User::factory()->admin()->create();

    expect(app(EditorAccess::class)->enabled('ai_writer', $admin))->toBeTrue()
        ->and(app(EditorManifest::class)->for($admin)['features'])->toContain('ai_writer');
});

it('sends category, style and element tag labels in the admin language', function () {
    app('translator')->addLines(['*.Media' => 'Medios', '*.Font weight' => 'Grosor de letra', '*.Box (div)' => 'Caja (div)'], 'es');
    app()->setLocale('es');

    $manifest = app(EditorManifest::class)->for(User::factory()->admin()->create());

    expect(collect($manifest['categories'])->firstWhere('key', 'media')['label'])->toBe('Medios')
        ->and(collect($manifest['styleProperties'])->firstWhere('path', 'typography.fontWeight')['label'])->toBe('Grosor de letra')
        ->and(collect($manifest['elementTags'])->firstWhere('value', 'div')['label'])->toBe('Caja (div)')
        ->and(app(StylePropertyRegistry::class)->find('typography.fontWeight')?->label)->toBe('Font weight');
});

it('stores feature choices in the settings', function () {
    app(EditorAccess::class)->save(['custom_css' => ['roles' => [], 'types' => null]], []);

    expect(app(Settings::class)->get(EditorAccess::FEATURES_KEY))->toBe(['custom_css' => ['roles' => [], 'types' => null]])
        ->and(app(EditorAccess::class)->enabled('custom_css', User::factory()->admin()->create()))->toBeFalse();
});

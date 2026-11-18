<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Site\Models\SitePart;
use App\Verso\Site\Parts\SiteCustomization;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->admin = User::factory()->admin()->create(['name' => 'Ingrid Solberg']);
    $this->theme = app(ThemeManager::class)->active();
});

function customizationFile(array $data): UploadedFile
{
    return UploadedFile::fake()->createWithContent('ashgrove-customization.json', (string) json_encode($data));
}

function bakeryCustomization(): array
{
    app(SitePartStore::class)->publish('ashgrove', 'header', [['id' => 'bar', 'type' => 'announcement-bar', 'attrs' => ['text' => 'Lucia buns on 13 December']]]);
    app(SitePartStore::class)->saveDraft('ashgrove', 'footer', [['id' => 'c', 'type' => 'copyright']]);
    app(PartVariants::class)->choose(app(ThemeManager::class)->active(), 'footer', 'four-columns');
    app(GlobalStyles::class)->save(app(ThemeManager::class)->active(), ['color.accent' => '#8a3b12', 'radius.base' => '8px']);

    $about = Entry::factory()->page()->published()->create(['title' => 'Our bakery', 'slug' => 'our-bakery']);
    $menu = Menu::factory()->at('primary')->create(['name' => 'Main navigation']);
    $item = MenuItem::factory()->forEntry($about)->create(['menu_id' => $menu->id, 'mega' => true]);
    MenuItem::factory()->under($item)->create(['label' => 'Partner mill', 'url' => 'https://mill.example.se', 'rel' => 'sponsored']);

    return app(SiteCustomization::class)->export(app(ThemeManager::class)->active());
}

it('exports parts, styles, colors and menus', function () {
    $export = bakeryCustomization();

    expect($export['format'])->toBe('verso-site-customization')
        ->and($export['theme'])->toBe('ashgrove')
        ->and($export['parts']['header']['published'][0]['attrs']['text'])->toBe('Lucia buns on 13 December')
        ->and($export['parts']['footer']['published'])->toBeNull()
        ->and($export['part_styles'])->toBe(['header' => 'classic', 'footer' => 'four-columns'])
        ->and($export['styles']['color.accent'])->toBe('#8a3b12')
        ->and($export['menus'][0]['name'])->toBe('Main navigation')
        ->and($export['menus'][0]['items'][0])->toMatchArray(['type' => 'entry', 'label' => 'Our bakery', 'url' => url('/our-bakery'), 'mega' => true])
        ->and($export['menus'][0]['items'][0]['children'][0]['rel'])->toBe(['sponsored']);
});

it('downloads the customization as a JSON file', function () {
    bakeryCustomization();

    $response = $this->actingAs($this->admin)->get(route('admin.site-editor.export'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="ashgrove-customization.json"');

    expect(json_decode((string) $response->getContent(), true)['parts'])->toHaveKeys(['header', 'footer']);
});

it('imports a customization on a fresh site and turns missing pages into links', function () {
    $export = bakeryCustomization();
    SitePart::query()->delete();
    Menu::query()->delete();
    Entry::query()->forceDelete();
    app(Settings::class)->forget('theme.part_variants.ashgrove');
    app(Settings::class)->forget('theme.styles.ashgrove');
    app(Settings::class)->forget('theme.ashgrove.accent_color');
    app()->forgetScopedInstances();

    $this->actingAs($this->admin)
        ->post(route('admin.site-editor.import'), ['file' => customizationFile($export)])
        ->assertRedirect(route('admin.site-editor'))
        ->assertInertiaFlash('toast.message', 'Imported 2 parts and 1 menu.');

    app()->forgetScopedInstances();
    $header = app(SitePartStore::class)->find('ashgrove', 'header');
    $menu = Menu::query()->where('name', 'Main navigation')->firstOrFail();
    $first = $menu->tree()->first();

    expect($header?->published_blocks[0]['attrs']['text'])->toBe('Lucia buns on 13 December')
        ->and(app(SitePartStore::class)->find('ashgrove', 'footer')?->isLive())->toBeFalse()
        ->and(app(PartVariants::class)->chosen($this->theme, 'footer'))->toBe('four-columns')
        ->and(app(GlobalStyles::class)->values($this->theme)['radius.base'])->toBe('8px')
        ->and(app(Settings::class)->get('theme.ashgrove.accent_color'))->toBe('#8a3b12')
        ->and($menu->location)->toBe('primary')
        ->and($first?->type->value)->toBe('custom')
        ->and($first?->url)->toBe(url('/our-bakery'))
        ->and($first?->mega)->toBeTrue()
        ->and($first?->children->first()?->rel)->toBe('sponsored');
});

it('refuses files that are not a customization', function (array $data, string $message) {
    $this->actingAs($this->admin)
        ->from(route('admin.site-editor'))
        ->post(route('admin.site-editor.import'), ['file' => customizationFile($data)])
        ->assertSessionHasErrors(['file' => $message]);

    expect(SitePart::query()->count())->toBe(0);
})->with([
    'other json' => [['name' => 'composer/package'], 'This file is not a Verso site customization.'],
    'newer version' => [['format' => 'verso-site-customization', 'version' => 99, 'parts' => []], 'This file is not a Verso site customization.'],
    'unsafe blocks' => [['format' => 'verso-site-customization', 'version' => 1, 'parts' => ['header' => ['blocks' => [['id' => 'x', 'type' => 'marquee']]]]], 'The "header" part has blocks that cannot be used: Block 1: the block type is unknown.'],
    'bad part name' => [['format' => 'verso-site-customization', 'version' => 1, 'parts' => ['../header' => ['blocks' => []]]], 'The parts of the file are not valid.'],
]);

it('lets only theme managers export and import', function () {
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.site-editor.export'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.site-editor.import'), ['file' => customizationFile([])])->assertForbidden();
    $this->actingAs($author)->get(route('admin.site-editor.templates'))->assertForbidden();
});

it('shows which template each kind of page uses', function () {
    app(PartVariants::class)->choose($this->theme, 'header', 'centered');

    $this->actingAs($this->admin)
        ->get(route('admin.site-editor.templates'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-editor/templates')
            ->where('rows.0', ['label' => 'Home page with the latest posts', 'kind' => 'home', 'template' => 'home', 'tried' => ['home']])
            ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['label'] === 'One Post' && $row['template'] === 'entry')
                && collect($rows)->contains(fn ($row) => $row['label'] === 'Page template: Wide page' && $row['template'] === 'templates.wide')
                && collect($rows)->contains(fn ($row) => $row['label'] === 'One Page' && $row['template'] === 'entry-page'))
            ->where('parts', [
                ['name' => 'header', 'label' => 'Header', 'style' => 'Centered'],
                ['name' => 'footer', 'label' => 'Footer', 'style' => 'Simple'],
            ])
        );
});

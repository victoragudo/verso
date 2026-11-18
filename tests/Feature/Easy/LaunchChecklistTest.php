<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Launch\LaunchCheck;
use App\Verso\Easy\Launch\LaunchChecklist;
use App\Verso\Easy\Launch\LaunchChecklistCard;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Site\Actions\CreateMenu;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;
use Tests\Support\SharedSlots;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Vidal', 'email' => 'marta@harborlane.example']);
    $this->check = fn (string $key): bool => app(LaunchChecklist::class)->get($key)->isDone();
    $this->theme = fn () => app(ThemeManager::class)->active();
});

function publishPage(User $author, string $title, string $slug, string $status = 'published'): Entry
{
    return app(CreateEntry::class)->handle($author, 'page', ['title' => $title, 'slug' => $slug, 'status' => $status]);
}

describe('core steps', function () {
    it('checks the logo of the active theme', function () {
        expect(($this->check)('logo'))->toBeFalse();

        app(ThemeCustomizer::class)->save(($this->theme)(), ['logo' => Media::factory()->create(['mime_type' => 'image/png'])->id]);

        expect(($this->check)('logo'))->toBeTrue()
            ->and(app(LaunchChecklist::class)->get('logo')->toArray()['href'])->toBe('/admin/easy/style');
    });

    it('checks the colors, saved with "Make it yours" or in the global styles', function () {
        expect(($this->check)('colors'))->toBeFalse();

        app(GlobalStyles::class)->save(($this->theme)(), ['color.accent' => '#1f4e79']);
        expect(($this->check)('colors'))->toBeTrue();

        app(GlobalStyles::class)->save(($this->theme)(), ['color.accent' => app(GlobalStyles::class)->tokens(($this->theme)())['color.accent']['default']]);
        expect(($this->check)('colors'))->toBeFalse();

        app(EasyState::class)->rememberStyle('calm', 'classic');
        expect(($this->check)('colors'))->toBeTrue();
    });

    it('checks that the saved colors are easy to read', function () {
        $readable = app(LaunchChecklist::class)->get('readable-colors');

        expect($readable->applies())->toBeTrue()
            ->and($readable->isDone())->toBeTrue()
            ->and($readable->toArray()['href'])->toBe('/admin/easy/style#colors');

        app(GlobalStyles::class)->save(($this->theme)(), ['color.text' => '#d6d2c8']);
        expect(($this->check)('readable-colors'))->toBeFalse()
            ->and(($this->check)('colors'))->toBeTrue();

        app(GlobalStyles::class)->save(($this->theme)(), ['color.text' => '#23211d', 'color.accent' => '#fde68a']);
        expect(($this->check)('readable-colors'))->toBeFalse();

        app(GlobalStyles::class)->save(($this->theme)(), ['color.accent' => '#9a3412']);
        expect(($this->check)('readable-colors'))->toBeTrue();
    });

    it('checks the pages: a starter site or enough published pages', function () {
        config(['verso.easy.launch.min_pages' => 2]);
        $pages = app(LaunchChecklist::class)->get('pages');

        expect($pages->isDone())->toBeFalse()
            ->and($pages->toArray()['href'])->toBe('/admin/easy/start');

        publishPage($this->admin, 'Home', 'home');
        publishPage($this->admin, 'About us', 'about', 'draft');

        expect($pages->isDone())->toBeFalse()
            ->and($pages->toArray()['href'])->toBe('/admin/entries/page');

        publishPage($this->admin, 'Contact', 'contact');
        expect($pages->isDone())->toBeTrue();

        Entry::query()->delete();
        app(EasyState::class)->rememberStarter('restaurant');
        expect($pages->isDone())->toBeTrue();
    });

    it('checks the contact details', function () {
        expect(($this->check)('contact'))->toBeFalse();

        app(Settings::class)->set('site.contact.phone', '+34 912 345 678');

        expect(($this->check)('contact'))->toBeTrue()
            ->and(app(LaunchChecklist::class)->get('contact')->toArray()['href'])->toBe('/admin/easy/details#contact');
    });

    it('checks the social links', function () {
        expect(($this->check)('social'))->toBeFalse();

        app(Settings::class)->set(SeoSettings::SCHEMA_SAME_AS, ['https://www.instagram.com/harborlanebakery']);

        expect(($this->check)('social'))->toBeTrue();
    });

    it('checks the tagline and the search engine description together', function () {
        app(Settings::class)->set('site.tagline', 'Fresh bread every morning');
        expect(($this->check)('search'))->toBeFalse();

        app(Settings::class)->set(SeoSettings::DEFAULT_DESCRIPTION, 'Sourdough, pastries and coffee by the harbor in Vigo.');
        expect(($this->check)('search'))->toBeTrue();

        app(Settings::class)->set('site.tagline', '  ');
        expect(($this->check)('search'))->toBeFalse();
    });

    it('checks for a published privacy page', function () {
        expect(($this->check)('privacy'))->toBeFalse();

        $page = publishPage($this->admin, 'Privacy policy', 'privacy-policy', 'draft');
        expect(($this->check)('privacy'))->toBeFalse();

        app(UpdateEntry::class)->handle($page, ['status' => 'published'], $this->admin);
        expect(($this->check)('privacy'))->toBeTrue();
    });

    it('counts the page made with "Create a privacy page" under any address', function () {
        $page = publishPage($this->admin, 'Your data', 'your-data');
        app(EasyState::class)->rememberPrivacyPage($page->id);

        expect(($this->check)('privacy'))->toBeTrue();
    });

    it('checks that the site went live and search engines are welcome', function () {
        expect(($this->check)('go-live'))->toBeFalse();

        app(EasyState::class)->rememberLaunch();
        expect(($this->check)('go-live'))->toBeTrue();

        app(Settings::class)->set(SeoSettings::DISCOURAGE, true);
        expect(($this->check)('go-live'))->toBeFalse();
    });

    it('leaves the logo out on a theme without one', function () {
        $sandbox = ThemeSandbox::create()->theme('plain-harbor', ['version' => '1.0.0'])->activate('plain-harbor');

        try {
            expect(array_column(app(LaunchChecklist::class)->toArray()['items'], 'key'))->not->toContain('logo')
                ->and(app(LaunchChecklist::class)->toArray()['total'])->toBe(7);
        } finally {
            $sandbox->destroy();
        }
    });
});

describe('the dashboard card', function () {
    $card = fn ($test) => SharedSlots::item($test->actingAs($test->admin->fresh())->get(route('admin.dashboard')), AdminSlot::DashboardTop, 'easy.launch-checklist');

    it('is hidden on sites without simple mode or a starter site', function () use ($card) {
        expect($card($this))->toBeNull();
    });

    it('shows the steps and the progress in simple mode', function () use ($card) {
        AdminPreferences::of($this->admin)->setSimpleMode(true);
        app(Settings::class)->set('site.contact.email', 'hola@harborlane.example');

        $item = $card($this);

        expect($item['component'])->toBe('easy.launch-checklist')
            ->and($item['props']['total'])->toBe(9)
            ->and($item['props']['done'])->toBe(2)
            ->and($item['props']['dismissUrl'])->toBe('/admin/easy/launch/dismiss')
            ->and($item['props']['items'][4])->toMatchArray(['key' => 'contact', 'label' => 'Add your contact details', 'done' => true, 'method' => 'get'])
            ->and($item['props']['items'][7])->toMatchArray(['key' => 'privacy', 'action' => 'Create a privacy page', 'method' => 'post', 'href' => '/admin/easy/launch/privacy']);
    });

    it('shows after a starter site even without simple mode', function () use ($card) {
        app(EasyState::class)->rememberStarter('business');

        expect($card($this)['props']['items'][2]['done'])->toBeTrue();
    });

    it('is only for people who manage the settings', function () {
        app(SimpleMode::class)->setSiteDefault(true);
        $editor = User::factory()->editor()->create();

        expect(SharedSlots::item($this->actingAs($editor)->get(route('admin.dashboard')), AdminSlot::DashboardTop, 'easy.launch-checklist'))->toBeNull();
    });

    it('includes the steps plugins add', function () use ($card) {
        AdminPreferences::of($this->admin)->setSimpleMode(true);
        app(LaunchChecklist::class)->register(new LaunchCheck('bread-menu', 'Add your bread menu', 'List what you bake each day.', '/admin/bakery/menu', fn (): bool => true, 75));

        $items = $card($this)['props']['items'];

        expect(array_column($items, 'key'))->toBe(['logo', 'colors', 'readable-colors', 'pages', 'contact', 'social', 'search', 'privacy', 'bread-menu', 'go-live']);
    });

    it('can only be hidden once every step is done', function () use ($card) {
        AdminPreferences::of($this->admin)->setSimpleMode(true);

        $this->actingAs($this->admin)->post(route('admin.easy.launch.dismiss'))->assertInertiaFlash('toast.type', 'error');
        expect($card($this))->not->toBeNull();

        $checklist = new LaunchChecklist;
        $checklist->register(new LaunchCheck('menu', 'Menu', 'Done.', '/', fn (): bool => true));
        app()->instance(LaunchChecklist::class, $checklist);

        $this->actingAs($this->admin->fresh())->post(route('admin.easy.launch.dismiss'))->assertInertiaFlash('toast.type', 'success');

        expect(AdminPreferences::of($this->admin->fresh())->hasDismissed(LaunchChecklistCard::KEY))->toBeTrue()
            ->and($card($this))->toBeNull();
    });
});

describe('the buttons', function () {
    it('need a signed in person', function () {
        $this->post(route('admin.easy.launch.go-live'))->assertRedirect(route('login'));
        $this->post(route('admin.easy.launch.privacy'))->assertRedirect(route('login'));
    });

    it('are only for people who manage the settings', function () {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('admin.easy.launch.privacy'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.easy.launch.go-live'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.easy.launch.dismiss'))->assertForbidden();
    });

    it('creates a privacy page from the template and opens it in the page editor', function () {
        app(Settings::class)->setMany(['site.name' => 'Harbor Lane Bakery', 'site.contact.email' => 'hola@harborlane.example']);

        $response = $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'));
        $page = Entry::query()->where('slug', 'privacy-policy')->sole();

        $response->assertRedirect(route('admin.easy.pages.edit', $page))->assertInertiaFlash('toast.type', 'success');

        $text = json_encode($page->blocks);

        expect($page->title)->toBe('Privacy policy')
            ->and($page->status->value)->toBe('published')
            ->and($text)->toContain('Harbor Lane Bakery', 'hola@harborlane.example', 'Your rights')
            ->and(app(EasyState::class)->privacyPageId())->toBe($page->id)
            ->and(app(LaunchChecklist::class)->get('privacy')->isDone())->toBeTrue();
    });

    it('links a new privacy page from the end of the footer menu', function () {
        $menu = app(CreateMenu::class)->handle('Bakery footer', 'footer');
        $menu->items()->create(['type' => MenuItemType::Custom, 'label' => 'Opening hours', 'url' => '/visit-us', 'position' => 0, 'visibility' => MenuItemVisibility::Everyone]);

        $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'));
        $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'));
        $page = Entry::query()->where('slug', 'privacy-policy')->sole();

        expect($menu->items()->pluck('entry_id', 'position')->all())->toBe([0 => null, 1 => $page->id]);

        auth()->logout();

        $this->get('/')->assertOk()->assertSee('href="'.$page->url().'"', false);
    });

    it('leaves the menus alone when no menu location is set for the privacy page', function () {
        config(['verso.easy.privacy.menu_location' => '']);
        $menu = app(CreateMenu::class)->handle('Bakery footer', 'footer');

        $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'));

        expect($menu->items()->count())->toBe(0);
    });

    it('never makes a second privacy page', function () {
        $draft = publishPage($this->admin, 'Privacy', 'privacy-policy', 'draft');

        $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'))->assertRedirect(route('admin.easy.pages.edit', $draft));
        $this->actingAs($this->admin)->post(route('admin.easy.launch.privacy'));

        expect(Entry::query()->ofType('page')->count())->toBe(1)
            ->and(app(EasyState::class)->privacyPageId())->toBe($draft->id);
    });

    it('puts the site live and records when', function () {
        app(Settings::class)->set(SeoSettings::DISCOURAGE, true);

        $this->actingAs($this->admin)->from(route('admin.dashboard'))
            ->post(route('admin.easy.launch.go-live'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertInertiaFlash('toast.message', 'Your site is live. Search engines may now find it.');

        expect(app(SeoSettings::class)->discourage())->toBeFalse()
            ->and(app(EasyState::class)->hasLaunched())->toBeTrue()
            ->and(app(Settings::class)->get(EasyState::LAUNCHED_AT))->toMatch('/^\d{4}-\d{2}-\d{2}T/');
    });
});

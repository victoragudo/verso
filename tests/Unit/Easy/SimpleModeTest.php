<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Simple\SimpleModeNav;

describe('AdminPreferences', function () {
    it('has no simple mode choice until the person picks one', function () {
        $user = User::factory()->admin()->create(['name' => 'Marta Vidal']);
        $preferences = AdminPreferences::of($user);

        expect($preferences->simpleMode())->toBeNull();

        $preferences->setSimpleMode(false);

        expect(AdminPreferences::of($user->fresh())->simpleMode())->toBeFalse();
    });

    it('keeps the other preferences when simple mode changes', function () {
        $user = User::factory()->admin()->create();
        AdminPreferences::of($user)->markSeen('easy-start');
        AdminPreferences::of($user)->setSimpleMode(true);

        $fresh = AdminPreferences::of($user->fresh());

        expect($fresh->hasSeen('easy-start'))->toBeTrue()->and($fresh->simpleMode())->toBeTrue();
    });

    it('remembers closed cards once', function () {
        $user = User::factory()->admin()->create();
        $preferences = AdminPreferences::of($user);

        $preferences->dismiss('launch-checklist');
        $preferences->dismiss('launch-checklist');

        expect(AdminPreferences::of($user->fresh())->dismissed())->toBe(['launch-checklist'])
            ->and($preferences->hasDismissed('welcome-tour'))->toBeFalse();
    });
});

describe('SimpleMode', function () {
    it('is off for people who never picked it on a site without a default', function () {
        $user = User::factory()->admin()->create();

        expect(app(SimpleMode::class)->isOn($user))->toBeFalse()
            ->and(app(SimpleMode::class)->hasSiteDefault())->toBeFalse()
            ->and(app(SimpleMode::class)->isOn(null))->toBeFalse();
    });

    it('falls back to the config default when the site never saved one', function () {
        config(['verso.easy.simple_mode.default' => true]);

        expect(app(SimpleMode::class)->isOn(User::factory()->admin()->create()))->toBeTrue();
    });

    it('follows the site default until the person switches', function () {
        $simple = app(SimpleMode::class);
        $user = User::factory()->editor()->create();
        $simple->setSiteDefault(true);

        expect($simple->isOn($user))->toBeTrue();

        $simple->set($user, false);

        expect($simple->isOn($user->fresh()))->toBeFalse()
            ->and(app(Settings::class)->get(SimpleMode::DEFAULT_SETTING))->toBeTrue();
    });

    it('saves the new site default once and never changes a saved one', function () {
        $simple = app(SimpleMode::class);

        $simple->rememberNewSiteDefault();
        expect($simple->siteDefault())->toBeTrue();

        $simple->setSiteDefault(false);
        $simple->rememberNewSiteDefault();
        expect($simple->siteDefault())->toBeFalse();
    });

    it('uses the config value for new sites', function () {
        config(['verso.easy.simple_mode.new_sites' => false]);
        app(SimpleMode::class)->rememberNewSiteDefault();

        expect(app(Settings::class)->get(SimpleMode::DEFAULT_SETTING))->toBeFalse();
    });
});

describe('SimpleModeNav', function () {
    $item = fn (string $key, string $label): NavItem => new NavItem($key, $label, 'admin.dashboard', keywords: ['start'], group: NavGroup::Content);

    it('keeps the core items and gives some of them plain names', function () {
        $nav = app(SimpleModeNav::class);

        expect($nav->keys())->toContain('dashboard', 'entries.page', 'entries.post', 'media', 'site.menus', 'easy-start', 'easy-style', 'easy-details', 'site.settings')
            ->not->toContain('themes', 'plugins', 'users', 'editor.code', 'fields', 'site.permalinks', 'terms.category')
            ->and($nav->label('site.editor'))->toBe('Header and footer')
            ->and($nav->label('media'))->toBe('Photos and files')
            ->and($nav->label('dashboard'))->toBeNull();
    });

    it('filters items and keeps the old name as a search word', function () use ($item) {
        $nav = new SimpleModeNav;
        $nav->allow('bakery.orders');
        $nav->rename('bakery.ovens', 'Baking schedule');

        $kept = $nav->filter([$item('bakery.orders', 'Orders'), $item('bakery.ovens', 'Oven planner'), $item('bakery.stock', 'Flour stock'), 'not an item']);

        expect(array_map(fn (NavItem $navItem): string => $navItem->label, $kept))->toBe(['Orders', 'Baking schedule'])
            ->and($kept[1]->keywords)->toBe(['start', 'Oven planner'])
            ->and($kept[0])->toBe($nav->filter([$kept[0]])[0]);
    });

    it('lets a plugin take a key back out', function () {
        $nav = new SimpleModeNav;
        $nav->rename('newsletter.subscribers', 'People who signed up');
        $nav->disallow('newsletter.subscribers');

        expect($nav->allows('newsletter.subscribers'))->toBeFalse()
            ->and($nav->label('newsletter.subscribers'))->toBeNull();
    });
});

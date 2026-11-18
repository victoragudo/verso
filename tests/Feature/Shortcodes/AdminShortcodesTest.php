<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Shop\Models\Product;

it('shares the shortcode of each newsletter list on the settings page', function () {
    PluginFixtures::boot('newsletter');
    MailingList::query()->create(['name' => 'Weekly bread news', 'slug' => 'weekly-bread']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.newsletter.settings.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('signups.0.shortcode', '[newsletter-signup]')
            ->where('signups.1.label', 'Weekly bread news')
            ->where('signups.1.shortcode', '[newsletter-signup list="weekly-bread"]'));
});

it('shares the shortcode and usage of a product on its edit page', function () {
    PluginFixtures::boot('shop');
    $entry = Entry::factory()->published()->create(['type' => Product::TYPE, 'title' => 'Linen apron', 'slug' => 'linen-apron']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.shop.products.edit', $entry))
        ->assertInertia(fn ($page) => $page
            ->where('embed.shortcode', '[shop-product product="linen-apron"]')
            ->where('embed.usedIn', []));
});

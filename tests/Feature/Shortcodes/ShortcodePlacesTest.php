<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/../Plugins/Forms/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    bakeryContactForm();
});

it('expands shortcodes in the footer text of the default theme', function () {
    app(Settings::class)->set('theme.default.footer_text', 'Padaria Lumiar <since 1952> [form slug="bakery-contact"]');
    $entry = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'about']);

    $html = $this->get($entry->path())->assertOk()->getContent();

    expect($html)->toContain('<div class="footer-text">Padaria Lumiar &lt;since 1952&gt; ')
        ->toContain('id="form-bakery-contact"');
});

it('leaves the footer text alone when the footer place is turned off', function () {
    config(['verso.shortcodes.places' => ['excerpt']]);
    app(Settings::class)->set('theme.default.footer_text', 'Visit us [form slug="bakery-contact"]');
    $entry = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'about']);

    expect($this->get($entry->path())->getContent())->toContain('Visit us [form slug="bakery-contact"]')
        ->not->toContain('id="form-bakery-contact"');
});

it('expands shortcodes in excerpts on archive cards', function () {
    Entry::factory()->published()->create(['type' => 'post', 'title' => 'Cakes for Easter', 'excerpt' => 'Order with [form slug="bakery-contact" title="Easter"]']);

    expect($this->get('/')->assertOk()->getContent())->toContain('>Easter</h2>');
});

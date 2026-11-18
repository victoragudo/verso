<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Vidal']);
    $this->details = [
        'email' => 'hola@harborlane.example',
        'phone' => '+34 986 123 456',
        'address' => "Rúa do Porto 12\n36202 Vigo",
        'hours' => 'Tuesday to Sunday, 7:30 to 14:00',
        'tagline' => 'Fresh bread every morning',
        'description' => 'Sourdough, pastries and coffee by the harbor in Vigo.',
        'social' => ['https://www.instagram.com/harborlanebakery', ' ', 'https://www.facebook.com/harborlanebakery'],
    ];
});

it('is only for people who manage the settings', function () {
    $this->get(route('admin.easy.details'))->assertRedirect(route('login'));

    $editor = User::factory()->editor()->create();
    $this->actingAs($editor)->get(route('admin.easy.details'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.easy.details.update'), $this->details)->assertForbidden();
});

it('shows the saved details and the limits', function () {
    app(Settings::class)->setMany(['site.contact.email' => 'hola@harborlane.example', 'site.tagline' => 'Fresh bread every morning']);

    $this->actingAs($this->admin)->get(route('admin.easy.details'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/easy/details')
            ->where('details.email', 'hola@harborlane.example')
            ->where('details.phone', '')
            ->where('details.tagline', 'Fresh bread every morning')
            ->where('details.social', [])
            ->where('limits.phone', 40)
            ->where('limits.address', 500)
        );
});

it('saves contact details, social profiles and the search basics', function () {
    Event::fake([SiteSettingsUpdated::class]);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.details.update'), $this->details)
        ->assertRedirect(route('admin.easy.details'))
        ->assertInertiaFlash('toast.message', 'Your site details are saved.');

    $details = app(SiteDetails::class);

    expect($details->contact())->toBe([
        'email' => 'hola@harborlane.example',
        'phone' => '+34 986 123 456',
        'address' => "Rúa do Porto 12\n36202 Vigo",
        'hours' => 'Tuesday to Sunday, 7:30 to 14:00',
    ])
        ->and($details->socialLinks())->toBe(['https://www.instagram.com/harborlanebakery', 'https://www.facebook.com/harborlanebakery'])
        ->and(app(SeoSettings::class)->defaultDescription())->toBe('Sourdough, pastries and coffee by the harbor in Vigo.')
        ->and(app(Settings::class)->get('site.tagline'))->toBe('Fresh bread every morning')
        ->and($details->hasSearchBasics())->toBeTrue();

    Event::assertDispatched(SiteSettingsUpdated::class, fn (SiteSettingsUpdated $event): bool => in_array('site.contact.email', $event->keys, true));
});

it('empties details that are left blank', function () {
    app(Settings::class)->set('site.contact.phone', '+34 986 000 000');

    $this->actingAs($this->admin)->put(route('admin.easy.details.update'), ['email' => 'hola@harborlane.example']);

    expect(app(SiteDetails::class)->contact()['phone'])->toBe('')
        ->and(app(SiteDetails::class)->socialLinks())->toBe([]);
});

it('explains wrong values in plain words', function () {
    config(['verso.easy.details.phone_max' => 12]);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.details.update'), [...$this->details, 'email' => 'hola at harborlane', 'phone' => '+34 986 123 456 789', 'social' => ['instagram harborlane']])
        ->assertSessionHasErrors([
            'email' => 'This does not look like an email address. Check it for typos.',
            'phone',
            'social.0' => 'Each line needs a full address that starts with https://, like https://instagram.com/yourname.',
        ]);
});

it('puts the contact details in a new contact section', function () {
    app(Settings::class)->setMany(['site.contact.phone' => '+34 986 123 456', 'site.contact.email' => 'hola@harborlane.example']);

    $blocks = $this->actingAs($this->admin)->getJson(route('admin.easy.sections.build', 'contact'))->assertOk()->json('blocks');

    expect(json_encode($blocks))->toContain('Phone: +34 986 123 456', 'Email: hola@harborlane.example');
});

it('shows the social profiles in a social links block without its own addresses', function () {
    app(Settings::class)->set(SeoSettings::SCHEMA_SAME_AS, ['https://www.instagram.com/harborlanebakery']);
    $render = fn (array $attrs): string => app(BlockRenderer::class)->render([['id' => 'b1', 'type' => 'social-links', 'attrs' => $attrs]]);

    expect($render(['links' => '']))->toContain('https://www.instagram.com/harborlanebakery', '>Instagram<')
        ->and($render(['links' => 'https://mastodon.social/@harborlane']))->toContain('Mastodon')->not->toContain('instagram');
});

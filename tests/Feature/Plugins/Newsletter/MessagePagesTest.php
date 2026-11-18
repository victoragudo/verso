<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Models\Subscriber;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
    app(Settings::class)->set('site.name', 'Douro Valley Journal');
});

function pendingReader(): Subscriber
{
    return Subscriber::query()->create(['email' => 'rui.almeida@example.pt', 'name' => 'Rui Almeida', 'status' => 'pending']);
}

it('shows the confirm page inside the active theme layout', function () {
    $subscriber = pendingReader();

    $this->get($subscriber->confirmUrl())
        ->assertOk()
        ->assertSee('class="site-header"', false)
        ->assertSee('class="site-footer"', false)
        ->assertSee('Subscription confirmed')
        ->assertSee('<title>Subscription confirmed - Douro Valley Journal</title>', false)
        ->assertSee('<meta name="robots" content="noindex">', false);

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed);
});

it('shows the unsubscribe page inside the theme layout', function () {
    $subscriber = subscribedReader();

    $this->get($subscriber->unsubscribeUrl())
        ->assertOk()
        ->assertSee('class="site-header"', false)
        ->assertSee('You are unsubscribed')
        ->assertSee('Back to Douro Valley Journal');
});

it('never lets the confirm or unsubscribe pages be cached', function () {
    $response = $this->get(pendingReader()->confirmUrl());

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('keeps the one-click unsubscribe post working', function () {
    $subscriber = subscribedReader();

    $this->post($subscriber->unsubscribeUrl())->assertOk();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

it('serves the message page from the theme namespace, so themes can override it', function () {
    expect(view()->exists(ThemeManager::NAMESPACE.'::newsletter.message'))->toBeTrue()
        ->and(view()->exists('newsletter::message'))->toBeFalse();
});

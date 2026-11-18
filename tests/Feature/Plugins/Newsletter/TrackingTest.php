<?php

use Illuminate\Support\Facades\Mail;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Actions\SendCampaign;
use Verso\Plugins\Newsletter\Models\CampaignLink;
use Verso\Plugins\Newsletter\Models\Delivery;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
    subscribedReader();
    $this->campaign = autumnIssue(['track_opens' => true, 'track_clicks' => true]);
    app(SendCampaign::class)->handle($this->campaign);
    $this->delivery = Delivery::query()->firstOrFail();
});

it('counts the first open and returns a gif', function () {
    $this->get(route('newsletter.open', $this->delivery->token))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $first = $this->delivery->fresh()->opened_at;
    $this->travel(2)->hours();
    $this->get(route('newsletter.open', $this->delivery->token))->assertOk();

    expect($first)->not->toBeNull()
        ->and($this->delivery->fresh()->opened_at->equalTo($first))->toBeTrue()
        ->and($this->campaign->fresh()->stats()['opens'])->toBe(1);
});

it('answers unknown open tokens with the gif too', function () {
    $this->get(route('newsletter.open', 'missing-token'))->assertOk()->assertHeader('Content-Type', 'image/gif');
});

it('counts clicks and redirects to the saved link', function () {
    $link = CampaignLink::query()->firstOrFail();

    $this->get(route('newsletter.click', ['token' => $this->delivery->token, 'link' => $link->id]))
        ->assertRedirect('https://douro.example.pt/walks');
    $this->get(route('newsletter.click', ['token' => $this->delivery->token, 'link' => $link->id]));

    expect($link->fresh()->clicks)->toBe(2)
        ->and($this->delivery->fresh()->clicked_at)->not->toBeNull()
        ->and($this->delivery->fresh()->opened_at)->not->toBeNull()
        ->and($this->campaign->fresh()->stats()['clicks'])->toBe(1);
});

it('never redirects to links of another campaign', function () {
    $other = autumnIssue();
    $foreign = CampaignLink::query()->create(['campaign_id' => $other->id, 'url' => 'https://phishing.example.com']);

    $this->get(route('newsletter.click', ['token' => $this->delivery->token, 'link' => $foreign->id]))->assertNotFound();
    $this->get(route('newsletter.click', ['token' => 'missing-token', 'link' => $foreign->id]))->assertNotFound();
});

it('stores the campaign links once', function () {
    expect(CampaignLink::query()->where('campaign_id', $this->campaign->id)->pluck('url')->all())->toBe(['https://douro.example.pt/walks']);
});

<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Actions\SendCampaign;
use Verso\Plugins\Newsletter\Actions\SendTestCampaign;
use Verso\Plugins\Newsletter\CampaignRenderer;
use Verso\Plugins\Newsletter\Enums\CampaignStatus;
use Verso\Plugins\Newsletter\Enums\DeliveryStatus;
use Verso\Plugins\Newsletter\Jobs\SendCampaignBatch;
use Verso\Plugins\Newsletter\Mail\CampaignMail;
use Verso\Plugins\Newsletter\Models\Delivery;
use Verso\Plugins\Newsletter\Models\Subscriber;
use Verso\Plugins\Newsletter\NewsletterSettings;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
});

// Five subscribed readers, plus one pending and one who left.
function fiveReaders(): void
{
    foreach (['ana', 'bruno', 'carla', 'diogo', 'elsa'] as $name) {
        subscribedReader("{$name}@example.pt", ucfirst($name));
    }

    Subscriber::query()->create(['email' => 'pending@example.pt', 'status' => 'pending']);
    Subscriber::query()->create(['email' => 'gone@example.pt', 'status' => 'unsubscribed']);
}

it('renders the campaign blocks with the block renderer', function () {
    $html = app(CampaignRenderer::class)->body(autumnIssue());

    expect($html)->toBe('<h2>Harvest season</h2><p>Read <a href="https://douro.example.pt/walks">our new walks</a> today.</p>');
});

it('finds the links of a campaign', function () {
    expect(app(CampaignRenderer::class)->links('<a href="https://a.example.pt/x?y=1&amp;z=2">A</a><a href="/local">B</a><a href="https://a.example.pt/x?y=1&amp;z=2">C</a>'))
        ->toBe(['https://a.example.pt/x?y=1&z=2']);
});

it('queues one batch per batch size with a growing delay', function () {
    Queue::fake();
    fiveReaders();
    app(NewsletterSettings::class)->save(['batch_size' => 2, 'batch_delay' => 90]);
    $campaign = autumnIssue();

    expect(app(SendCampaign::class)->handle($campaign))->toBe(3);

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->recipients_count)->toBe(5)
        ->and(Delivery::query()->count())->toBe(5);

    Queue::assertPushed(SendCampaignBatch::class, 3);
    $delays = collect(Queue::pushed(SendCampaignBatch::class))->map(fn ($job) => $job->delay)->values();
    expect(now()->diffInSeconds($delays[2], true))->toBeGreaterThanOrEqual(179);
});

it('sends to list members only when a list is chosen', function () {
    Queue::fake();
    fiveReaders();
    $list = weeklyDigest();
    $list->subscribers()->attach(Subscriber::query()->whereIn('email', ['ana@example.pt', 'gone@example.pt'])->pluck('id'));

    $campaign = autumnIssue(['list_id' => $list->id]);
    app(SendCampaign::class)->handle($campaign);

    expect($campaign->fresh()->recipients_count)->toBe(1);
});

it('sends every email and closes the campaign', function () {
    fiveReaders();
    $campaign = autumnIssue();

    app(SendCampaign::class)->handle($campaign);

    Mail::assertSent(CampaignMail::class, 5);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo('ana@example.pt')
        && $mail->subjectLine === 'Autumn in the Douro valley'
        && str_contains($mail->body, 'Harvest season')
        && str_contains($mail->body, 'Unsubscribe')
        && ! str_contains($mail->body, '/newsletter/o/'));

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->sent_at)->not->toBeNull()
        ->and($campaign->stats())->toMatchArray(['recipients' => 5, 'sent' => 5, 'queued' => 0, 'opens' => 0]);
});

it('adds the unsubscribe headers', function () {
    subscribedReader();
    app(SendCampaign::class)->handle(autumnIssue());

    Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) {
        $headers = $mail->headers()->text;

        return str_contains($headers['List-Unsubscribe'], '/newsletter/unsubscribe/') && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

it('skips people who left after sending started', function () {
    Queue::fake();
    $reader = subscribedReader();
    $campaign = autumnIssue();
    app(SendCampaign::class)->handle($campaign);
    $reader->unsubscribe();

    (new SendCampaignBatch($campaign->id, Delivery::query()->pluck('id')->all()))->handle(app(CampaignRenderer::class), app(NewsletterSettings::class));

    Mail::assertNothingSent();
    expect(Delivery::query()->first()->status)->toBe(DeliveryStatus::Failed)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sent);
});

it('refuses to send a campaign twice', function () {
    subscribedReader();
    $campaign = autumnIssue();
    app(SendCampaign::class)->handle($campaign);

    app(SendCampaign::class)->handle($campaign->fresh());
})->throws(LogicException::class);

it('adds the pixel and tracked links only when tracking is on', function () {
    subscribedReader();
    $campaign = autumnIssue(['track_opens' => true, 'track_clicks' => true]);

    app(SendCampaign::class)->handle($campaign);

    $delivery = Delivery::query()->first();
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => str_contains($mail->body, route('newsletter.open', $delivery->token))
        && str_contains($mail->body, e(route('newsletter.click', ['token' => $delivery->token, 'link' => $campaign->links()->first()->id])))
        && ! str_contains($mail->body, 'href="https://douro.example.pt/walks"'));
});

it('sends a test copy without tracking or deliveries', function () {
    $campaign = autumnIssue(['track_opens' => true]);

    app(SendTestCampaign::class)->handle($campaign, 'editor@example.pt');

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo('editor@example.pt')
        && $mail->subjectLine === '[Test] Autumn in the Douro valley'
        && ! str_contains($mail->body, '/newsletter/o/'));
    expect(Delivery::query()->count())->toBe(0)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
});

it('uses the sender from the settings', function () {
    app(NewsletterSettings::class)->save(['from_address' => 'letters@douro.example.pt', 'from_name' => 'Douro Letters']);

    app(SendTestCampaign::class)->handle(autumnIssue(), 'editor@example.pt');

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasFrom('letters@douro.example.pt', 'Douro Letters'));
});

it('marks a delivery failed when the mailer throws', function () {
    Queue::fake();
    subscribedReader();
    $campaign = autumnIssue();
    app(SendCampaign::class)->handle($campaign);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused by smtp.example.pt'));

    (new SendCampaignBatch($campaign->id, Delivery::query()->pluck('id')->all()))->handle(app(CampaignRenderer::class), app(NewsletterSettings::class));

    expect(Delivery::query()->first()->status)->toBe(DeliveryStatus::Failed)
        ->and(Delivery::query()->first()->error)->toContain('Connection refused');
});

it('reads batch settings with config fallbacks', function () {
    $settings = app(NewsletterSettings::class);

    expect($settings->batchSize())->toBe(50)
        ->and($settings->batchDelay())->toBe(60)
        ->and($settings->fromAddress())->toBe(config('mail.from.address'));

    $settings->save(['batch_size' => 0, 'batch_delay' => -5, 'unknown' => 'ignored']);

    expect($settings->batchSize())->toBe(1)
        ->and($settings->batchDelay())->toBe(0)
        ->and($settings->toArray())->toHaveKeys(['batch_size', 'track_opens', 'track_clicks']);
});

it('ignores batches of deleted campaigns', function () {
    Bus::fake();

    (new SendCampaignBatch(999, [1, 2]))->handle(app(CampaignRenderer::class), app(NewsletterSettings::class));

    Mail::assertNothingSent();
});

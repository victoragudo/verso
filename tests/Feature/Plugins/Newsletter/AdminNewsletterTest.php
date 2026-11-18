<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Enums\CampaignStatus;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Mail\CampaignMail;
use Verso\Plugins\Newsletter\Mail\ConfirmSubscriptionMail;
use Verso\Plugins\Newsletter\Models\Campaign;
use Verso\Plugins\Newsletter\Models\MailingList;
use Verso\Plugins\Newsletter\Models\Subscriber;
use Verso\Plugins\Newsletter\NewsletterSettings;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
    $this->editor = User::factory()->editor()->create(['name' => 'Teresa Gomes']);
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Saraiva']);
});

it('lists subscribers with counts, lists and filters', function () {
    $list = weeklyDigest();
    subscribedReader()->lists()->attach($list);
    Subscriber::query()->create(['email' => 'filipe.matos@example.pt', 'status' => 'pending']);

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.subscribers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/newsletter/subscribers', false)
            ->has('subscribers.data', 2)
            ->where('counts', ['all' => 2, 'pending' => 1, 'subscribed' => 1, 'unsubscribed' => 0])
            ->where('lists.0.subscribers_count', 1)
        );

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.subscribers.index', ['status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->has('subscribers.data', 1)->where('subscribers.data.0.email', 'filipe.matos@example.pt'));

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.subscribers.index', ['list' => $list->id, 'search' => 'ines']))
        ->assertInertia(fn (Assert $page) => $page->has('subscribers.data', 1)->where('subscribers.data.0.lists', ['Weekly digest']));
});

it('adds a subscriber by hand who still confirms by email', function () {
    $this->actingAs($this->editor)->post(route('admin.newsletter.subscribers.store'), [
        'email' => 'hugo.vieira@example.pt',
        'name' => 'Hugo Vieira',
    ])->assertSessionHasNoErrors();

    expect(Subscriber::query()->first()->status)->toBe(SubscriberStatus::Pending);
    Mail::assertSent(ConfirmSubscriptionMail::class);
});

it('imports a CSV with a header row into a list', function () {
    $list = weeklyDigest();
    subscribedReader('gone@example.pt', 'Gone Reader')->unsubscribe();
    $csv = "\u{FEFF}Name,Email\nLaura Pinto,laura.pinto@example.pt\nBroken row,not-an-email\nGone Again,gone@example.pt\n,MARCO.SILVA@example.pt\n";

    $this->actingAs($this->editor)->post(route('admin.newsletter.subscribers.import'), [
        'file' => UploadedFile::fake()->createWithContent('readers.csv', $csv),
        'list_id' => $list->id,
        'consent' => '1',
    ])->assertSessionHasNoErrors();

    expect(Subscriber::query()->where('email', 'laura.pinto@example.pt')->first()->status)->toBe(SubscriberStatus::Subscribed)
        ->and(Subscriber::query()->where('email', 'marco.silva@example.pt')->exists())->toBeTrue()
        ->and(Subscriber::query()->where('email', 'gone@example.pt')->first()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($list->subscribers()->count())->toBe(3);
    Mail::assertNothingSent();
});

it('imports a CSV without a header row', function () {
    $this->actingAs($this->editor)->post(route('admin.newsletter.subscribers.import'), [
        'file' => UploadedFile::fake()->createWithContent('plain.csv', "sara.lima@example.pt\nvitor.cruz@example.pt\n"),
        'consent' => '1',
    ])->assertSessionHasNoErrors();

    expect(Subscriber::query()->subscribed()->count())->toBe(2);
});

it('asks for consent before importing', function () {
    $this->actingAs($this->editor)->post(route('admin.newsletter.subscribers.import'), [
        'file' => UploadedFile::fake()->createWithContent('readers.csv', "email\nsara.lima@example.pt\n"),
    ])->assertSessionHasErrors('consent');
});

it('exports subscribers as CSV', function () {
    $list = weeklyDigest();
    subscribedReader()->lists()->attach($list);

    $response = $this->actingAs($this->editor)->get(route('admin.newsletter.subscribers.export'))->assertOk();
    $lines = explode("\n", trim($response->streamedContent()));

    expect($lines[0])->toBe('email,name,status,lists,source,confirmed_at,created_at')
        ->and($lines[1])->toStartWith('ines.cardoso@example.pt,"Ines Cardoso",subscribed,"Weekly digest",signup,');
});

it('unsubscribes and deletes subscribers', function () {
    $reader = subscribedReader();

    $this->actingAs($this->editor)->put(route('admin.newsletter.subscribers.unsubscribe', $reader))->assertRedirect();
    expect($reader->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    $this->actingAs($this->editor)->delete(route('admin.newsletter.subscribers.destroy', $reader))->assertRedirect();
    expect(Subscriber::query()->count())->toBe(0);
});

it('creates and deletes lists', function () {
    $this->actingAs($this->editor)->post(route('admin.newsletter.lists.store'), ['name' => 'Event news'])->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->post(route('admin.newsletter.lists.store'), ['name' => 'Event News'])->assertSessionHasErrors('slug');

    $list = MailingList::query()->firstWhere('slug', 'event-news');
    $this->actingAs($this->editor)->delete(route('admin.newsletter.lists.destroy', $list))->assertRedirect();

    expect(MailingList::query()->count())->toBe(0);
});

it('creates a campaign with blocks and tracking off by default', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.campaigns.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/newsletter/campaign-edit', false)
            ->where('campaign', null)
            ->where('defaults', ['track_opens' => false, 'track_clicks' => false])
        );

    $this->actingAs($this->editor)->post(route('admin.newsletter.campaigns.store'), [
        'subject' => 'Winter recipes from Tras-os-Montes',
        'blocks' => [['id' => 'b_p1', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Chestnut soup is back.', 'children' => []]],
    ])->assertRedirect();

    $campaign = Campaign::query()->firstOrFail();
    expect($campaign->blocks[0]['content'])->toBe('Chestnut soup is back.')
        ->and($campaign->track_opens)->toBeFalse()
        ->and($campaign->created_by)->toBe($this->editor->id);
});

it('rejects invalid campaign blocks', function () {
    $this->actingAs($this->editor)->post(route('admin.newsletter.campaigns.store'), [
        'subject' => 'Winter recipes',
        'blocks' => [['id' => 'b_x', 'type' => 'hologram', 'attrs' => [], 'content' => '', 'children' => []]],
    ])->assertSessionHasErrors('blocks');
});

it('edits a draft and sends a test', function () {
    $campaign = autumnIssue();

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.campaigns.edit', $campaign))
        ->assertInertia(fn (Assert $page) => $page->where('campaign.subject', 'Autumn in the Douro valley'));

    $this->actingAs($this->editor)->put(route('admin.newsletter.campaigns.update', $campaign), [
        'subject' => 'Autumn in the Minho',
        'blocks' => $campaign->blocks,
        'track_clicks' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->editor)->post(route('admin.newsletter.campaigns.test', $campaign), ['email' => 'teresa@example.pt'])->assertSessionHasNoErrors();

    expect($campaign->fresh()->subject)->toBe('Autumn in the Minho')
        ->and($campaign->fresh()->track_clicks)->toBeTrue();
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo('teresa@example.pt'));
});

it('sends a campaign and shows its stats', function () {
    Queue::fake();
    subscribedReader();
    $campaign = autumnIssue();

    $this->actingAs($this->editor)->post(route('admin.newsletter.campaigns.send', $campaign))
        ->assertRedirect(route('admin.newsletter.campaigns.show', $campaign));

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending);

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.campaigns.show', $campaign))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/newsletter/campaign', false)
            ->where('stats.recipients', 1)
            ->where('stats.queued', 1)
        );

    $this->actingAs($this->editor)->get(route('admin.newsletter.campaigns.edit', $campaign))->assertRedirect(route('admin.newsletter.campaigns.show', $campaign));
    $this->actingAs($this->editor)->put(route('admin.newsletter.campaigns.update', $campaign), ['subject' => 'Too late'])->assertStatus(409);
    $this->actingAs($this->editor)->delete(route('admin.newsletter.campaigns.destroy', $campaign))->assertStatus(409);
});

it('does not send a campaign nobody would get', function () {
    $campaign = autumnIssue();

    $this->actingAs($this->editor)->post(route('admin.newsletter.campaigns.send', $campaign))->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft);
});

it('lists and deletes campaigns', function () {
    $campaign = autumnIssue();

    $this->actingAs($this->editor)
        ->get(route('admin.newsletter.campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/plugins/newsletter/campaigns', false)->where('campaigns.data.0.subject', 'Autumn in the Douro valley'));

    $this->actingAs($this->editor)->delete(route('admin.newsletter.campaigns.destroy', $campaign))->assertRedirect(route('admin.newsletter.campaigns.index'));
    expect(Campaign::query()->count())->toBe(0);
});

it('saves the sending settings for admins only', function () {
    $this->actingAs($this->editor)->get(route('admin.newsletter.settings.edit'))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.newsletter.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/plugins/newsletter/settings', false)->where('settings.batch_size', 50));

    $this->actingAs($this->admin)->put(route('admin.newsletter.settings.update'), [
        'batch_size' => 200,
        'batch_delay' => 30,
        'from_address' => 'letters@douro.example.pt',
        'from_name' => 'Douro Letters',
        'track_opens' => true,
        'track_clicks' => false,
    ])->assertSessionHasNoErrors();

    $settings = app(NewsletterSettings::class);
    expect($settings->batchSize())->toBe(200)
        ->and($settings->batchDelay())->toBe(30)
        ->and((bool) $settings->get('track_opens'))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.newsletter.settings.update'), ['batch_size' => 0, 'batch_delay' => -1, 'from_address' => 'nope'])
        ->assertSessionHasErrors(['batch_size', 'batch_delay', 'from_address']);
});

it('keeps subscribers and campaigns away from authors', function () {
    $author = User::factory()->author()->create();

    $this->actingAs($author)->get(route('admin.newsletter.subscribers.index'))->assertForbidden();
    $this->actingAs($author)->get(route('admin.newsletter.campaigns.index'))->assertForbidden();
});

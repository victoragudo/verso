<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Newsletter\Actions\Subscribe;
use Verso\Plugins\Newsletter\Enums\SubscriberStatus;
use Verso\Plugins\Newsletter\Mail\ConfirmSubscriptionMail;
use Verso\Plugins\Newsletter\Models\Subscriber;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('newsletter');
    Mail::fake();
});

it('ships a valid manifest', function () {
    expect(PluginFixtures::use(base_path('plugins'))->get('newsletter')->canBeEnabled())->toBeTrue();
});

it('stores a signup as pending and sends a confirm email', function () {
    $list = weeklyDigest();

    $this->from('/blog/harvest')->post(route('newsletter.subscribe'), [
        'email' => ' Miguel.Sousa@Example.PT ',
        'name' => 'Miguel Sousa',
        'list' => 'weekly-digest',
    ])->assertRedirect('/blog/harvest')->assertSessionHas('newsletter');

    $subscriber = Subscriber::query()->firstOrFail();

    expect($subscriber->email)->toBe('miguel.sousa@example.pt')
        ->and($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->lists->pluck('id')->all())->toBe([$list->id]);
    Mail::assertSent(ConfirmSubscriptionMail::class, fn ($mail) => $mail->hasTo('miguel.sousa@example.pt'));
});

it('does not email people who are already subscribed', function () {
    subscribedReader();

    app(Subscribe::class)->handle('ines.cardoso@example.pt');

    Mail::assertNothingSent();
    expect(Subscriber::query()->first()->status)->toBe(SubscriberStatus::Subscribed);
});

it('asks people who left to confirm again', function () {
    $subscriber = subscribedReader();
    $subscriber->unsubscribe();

    app(Subscribe::class)->handle('ines.cardoso@example.pt');

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending);
    Mail::assertSent(ConfirmSubscriptionMail::class);
});

it('rejects bots that fill the hidden field and bad emails', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    $this->post(route('newsletter.subscribe'), ['email' => 'bot@example.com', 'website' => 'https://spam.example'])->assertSessionHasErrors('website');

    expect(Subscriber::query()->count())->toBe(0);
});

it('confirms with the signed link', function () {
    $subscriber = app(Subscribe::class)->handle('joana.reis@example.pt');

    $this->get($subscriber->confirmUrl())->assertOk()->assertSee('Subscription confirmed');

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->fresh()->confirmed_at)->not->toBeNull();
});

it('refuses tampered or expired confirm links', function () {
    $subscriber = app(Subscribe::class)->handle('joana.reis@example.pt');

    $this->get(route('newsletter.confirm', $subscriber))->assertForbidden();

    $url = $subscriber->confirmUrl();
    $this->travel((int) config('newsletter.confirm_hours') + 1)->hours();
    $this->get($url)->assertForbidden();

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending);
});

it('unsubscribes with the signed link, also by one click POST', function () {
    $subscriber = subscribedReader();

    $this->get($subscriber->unsubscribeUrl())->assertOk()->assertSee('You are unsubscribed');
    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    $other = subscribedReader('paulo.neves@example.pt', 'Paulo Neves');
    $this->post($other->unsubscribeUrl())->assertOk();
    expect($other->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    $this->get(URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $other->id]).'x')->assertForbidden();
});

it('renders the signup block in an entry', function () {
    weeklyDigest();
    $entry = Entry::factory()->published()->create([
        'title' => 'Harvest stories',
        'blocks' => [['id' => 'b_signup', 'type' => 'newsletter-signup', 'attrs' => ['title' => 'Join 4,000 readers', 'list' => 'weekly-digest', 'askName' => true], 'content' => '', 'children' => []]],
    ]);

    $this->get($entry->path())
        ->assertOk()
        ->assertSee('Join 4,000 readers')
        ->assertSee('action="'.route('newsletter.subscribe').'"', false)
        ->assertSee('name="list" value="weekly-digest"', false)
        ->assertSee('name="name"', false);
});

it('uses an h2 title by default and the level the editor picked', function () {
    $signup = fn (array $attrs) => ['id' => 'b_signup', 'type' => 'newsletter-signup', 'attrs' => ['title' => 'Letters from the orchard', ...$attrs], 'content' => '', 'children' => []];
    $entry = Entry::factory()->published()->create(['title' => 'Apple season', 'blocks' => [$signup([])]]);
    $nested = Entry::factory()->published()->create(['title' => 'Pear season', 'blocks' => [$signup(['headingLevel' => 'h4'])]]);
    $odd = Entry::factory()->published()->create(['title' => 'Plum season', 'blocks' => [$signup(['headingLevel' => 'h1 onclick=x'])]]);

    $this->get($entry->path())->assertOk()->assertSee('<h2 class="verso-newsletter__title">Letters from the orchard</h2>', false);
    $this->get($nested->path())->assertOk()->assertSee('<h4 class="verso-newsletter__title">Letters from the orchard</h4>', false);
    $this->get($odd->path())->assertOk()->assertDontSee('onclick', false);
});

it('refuses a heading level that is not offered', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.entries.store', 'post'), [
            'title' => 'Orchard notes',
            'status' => 'draft',
            'blocks' => [['id' => 'b_signup', 'type' => 'newsletter-signup', 'attrs' => ['headingLevel' => 'h1'], 'content' => '', 'children' => []]],
        ])
        ->assertSessionHasErrors('blocks');

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.entries.store', 'post'), [
            'title' => 'Orchard notes',
            'status' => 'draft',
            'blocks' => [['id' => 'b_signup', 'type' => 'newsletter-signup', 'attrs' => ['headingLevel' => 'h3'], 'content' => '', 'children' => []]],
        ])
        ->assertSessionDoesntHaveErrors();
});

it('describes the signup block for the editor with the lists', function () {
    weeklyDigest();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn ($page) => $page
            ->where('pluginBlocks', fn ($blocks) => collect($blocks)->firstWhere('name', 'newsletter-signup')['fields'][3]['options'][1]['value'] === 'weekly-digest')
        );
});

it('limits signups per minute', function () {
    config(['newsletter.signup_rate_limit' => 2]);

    foreach (['ana@example.pt', 'rui@example.pt'] as $email) {
        $this->post(route('newsletter.subscribe'), ['email' => $email])->assertRedirect();
    }

    $this->post(route('newsletter.subscribe'), ['email' => 'eva@example.pt'])->assertTooManyRequests();
});

it('shows the saved default title and button of the signup block in the site language', function () {
    app(Settings::class)->set('site.locale', 'es');
    $page = Entry::factory()->page()->published()->create([
        'slug' => 'join-the-bakery-list',
        'blocks' => [['id' => 'b_signup', 'type' => 'newsletter-signup', 'attrs' => ['title' => 'Get the newsletter', 'button' => 'Subscribe'], 'content' => '', 'children' => []]],
    ]);

    $this->get($page->path())
        ->assertOk()
        ->assertSee('Recibe el boletín')
        ->assertSee('>Suscribirse</button>', false)
        ->assertDontSee('Get the newsletter');
});

<?php

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\Mail\SubmissionMail;
use Verso\Plugins\Forms\Models\Submission;
use Verso\Plugins\Forms\Support\FormsSettings;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    RateLimiter::clear('forms');
    $this->form = bakeryContactForm();
});

it('stores a message and sends the team a notification with reply-to set to the sender', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers())
        ->assertRedirect(url('/contact').'#form-bakery-contact')
        ->assertSessionHas('forms.sent', 'bakery-contact');

    $submission = Submission::query()->sole();

    expect($submission->status)->toBe(SubmissionStatus::New)
        ->and($submission->email)->toBe('ines.carvalho@example.pt')
        ->and($submission->value('topic'))->toBe('Wedding cakes')
        ->and($submission->value('consent'))->toBeTrue()
        ->and($submission->ip_hash)->toHaveLength(64)
        ->and($submission->ip_hash)->not->toContain('127.0.0.1')
        ->and($submission->page_url)->toBe(url('/contact'))
        ->and($submission->search_text)->toContain('three tier cake');

    Mail::assertQueued(SubmissionMail::class, fn (SubmissionMail $mail): bool => $mail->hasTo('orders@padaria-lumiar.pt')
        && $mail->hasTo('marta@padaria-lumiar.pt')
        && $mail->hasReplyTo('ines.carvalho@example.pt')
        && $mail->envelope()->subject === 'New Wedding cakes request from Inês Carvalho');
    Mail::assertQueuedCount(1);
});

it('sends a confirmation to the sender when the form asks for it', function () {
    $form = bakeryContactForm(['confirmation_enabled' => true, 'confirmation_subject' => 'Thanks {name}, we got your message']);

    $this->post(route('forms.submit', $form), bakeryAnswers())->assertRedirect();

    Mail::assertQueued(SubmissionMail::class, fn (SubmissionMail $mail): bool => $mail->hasTo('ines.carvalho@example.pt')
        && $mail->hasReplyTo('orders@padaria-lumiar.pt')
        && ! $mail->forTeam
        && $mail->envelope()->subject === 'Thanks Inês Carvalho, we got your message');
    Mail::assertQueuedCount(2);
});

it('sends the visitor to the redirect address after a message', function () {
    $form = bakeryContactForm(['redirect_url' => '/thank-you']);

    $this->post(route('forms.submit', $form), bakeryAnswers())->assertRedirect('/thank-you');
});

it('answers JSON clients with the success message', function () {
    $this->postJson(route('forms.submit', $this->form), bakeryAnswers())
        ->assertOk()
        ->assertJson(['message' => 'Thank you! Your message was sent.', 'redirect' => null]);
});

it('sends people back to the form with errors, old input and the form marked as active', function () {
    $response = $this->post(route('forms.submit', $this->form), bakeryAnswers(['email' => 'not an email', 'message' => 'Hi', 'consent' => null]));

    $response->assertRedirect(url('/contact').'#form-bakery-contact')
        ->assertSessionHas('forms.active', 'bakery-contact')
        ->assertSessionHasErrors(['fields.email', 'fields.message', 'fields.consent'], errorBag: 'form_bakery-contact')
        ->assertSessionHasInput('fields.name', 'Inês Carvalho');

    expect(Submission::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('names the field in the error message', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers(['name' => '']))
        ->assertSessionHasErrors(['fields.name' => 'Please fill in Your name.'], errorBag: 'form_bakery-contact');
});

it('returns validation errors as JSON for JSON clients', function () {
    $this->postJson(route('forms.submit', $this->form), bakeryAnswers(['topic' => 'Free bread']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fields.topic']);
});

it('drops messages caught by the honeypot but answers like a success', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['company_website' => 'https://cheap-links.example']))
        ->assertSessionHas('forms.sent', 'bakery-contact');

    expect(Submission::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('drops messages sent faster than a person can type', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: [FormTimer::FIELD => app(FormTimer::class, ['minSeconds' => 3, 'maxSeconds' => 60])->token()]))
        ->assertRedirect();

    expect(Submission::query()->count())->toBe(0);
});

it('drops messages without a time trap token', function () {
    $answers = bakeryAnswers();
    unset($answers[FormTimer::FIELD]);

    $this->post(route('forms.submit', $this->form), $answers)->assertRedirect();

    expect(Submission::query()->count())->toBe(0);
});

it('keeps bot messages in spam when the site asks for it', function () {
    config(['forms.spam.keep_bot_messages' => true]);

    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['company_website' => 'spam']))->assertRedirect();

    expect(Submission::query()->sole())
        ->status->toBe(SubmissionStatus::Spam)
        ->spam_reason->toBe('honeypot');
});

it('accepts a cached page token that is days old', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: [
        FormTimer::FIELD => Crypt::encryptString((string) now()->subDays(9)->getTimestamp()),
    ]))->assertRedirect();

    expect(Submission::query()->count())->toBe(1);
});

it('moves messages with many links to spam and sends no email', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers([
        'message' => 'Great site! Visit https://a.example https://b.example and www.c.example for deals.',
    ]))->assertRedirect();

    expect(Submission::query()->sole())
        ->status->toBe(SubmissionStatus::Spam)
        ->spam_reason->toBe('links');
    Mail::assertNothingQueued();
});

it('moves messages with a blocked word to spam', function () {
    app(FormsSettings::class)->save([
        'retention_days' => 365, 'max_links' => 3, 'min_seconds' => 3,
        'blocked_words' => "cheap watches\nloan offer", 'captcha_provider' => 'none',
    ]);

    $this->post(route('forms.submit', $this->form), bakeryAnswers(['message' => 'Hello, we have a LOAN OFFER for your bakery today.']))->assertRedirect();

    expect(Submission::query()->sole())
        ->status->toBe(SubmissionStatus::Spam)
        ->spam_reason->toBe('blocked_word');
});

it('limits how many messages one visitor can send', function () {
    config(['forms.spam.rate_limit' => 2]);

    $this->post(route('forms.submit', $this->form), bakeryAnswers())->assertSessionHasNoErrors();
    $this->post(route('forms.submit', $this->form), bakeryAnswers())->assertSessionHasNoErrors();
    $this->post(route('forms.submit', $this->form), bakeryAnswers())
        ->assertSessionHasErrors(['form'], errorBag: 'form_bakery-contact');

    expect(Submission::query()->count())->toBe(2);
});

it('does not count messages with typing errors against the limit', function () {
    config(['forms.spam.rate_limit' => 1]);

    $this->post(route('forms.submit', $this->form), bakeryAnswers(['email' => 'ines@']));
    $this->post(route('forms.submit', $this->form), bakeryAnswers())->assertSessionHasNoErrors();

    expect(Submission::query()->count())->toBe(1);
});

it('checks options, dates, phones and checkboxes', function () {
    $form = bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'date', 'name' => 'event_date', 'label' => 'Event date', 'required' => true],
        ['id' => 'f7', 'type' => 'phone', 'name' => 'phone', 'label' => 'Phone'],
        ['id' => 'f8', 'type' => 'checkboxes', 'name' => 'extras', 'label' => 'Extras', 'required' => true, 'options' => ['Candles', 'Gluten free layer', 'Delivery']],
        ['id' => 'f9', 'type' => 'radio', 'name' => 'size', 'label' => 'Size', 'options' => ['Small', 'Large']],
        ['id' => 'f10', 'type' => 'hidden', 'name' => 'campaign', 'label' => 'Campaign', 'value' => 'spring-weddings'],
    ]);

    $this->post(route('forms.submit', $form), bakeryAnswers([
        'event_date' => '14/06/2027', 'phone' => 'call me', 'extras' => ['Fireworks'], 'size' => 'Huge',
    ]))->assertSessionHasErrors(['fields.event_date', 'fields.phone', 'fields.extras.0', 'fields.size'], errorBag: 'form_bakery-contact');

    $this->post(route('forms.submit', $form), bakeryAnswers([
        'event_date' => '2027-06-14', 'phone' => '+351 912 345 678', 'extras' => ['Candles', 'Delivery'], 'size' => 'Large', 'campaign' => 'hacked',
    ]))->assertSessionHasNoErrors();

    expect(Submission::query()->sole()->data)->toMatchArray([
        'event_date' => '2027-06-14',
        'phone' => '+351 912 345 678',
        'extras' => ['Candles', 'Delivery'],
        'size' => 'Large',
        'campaign' => 'spring-weddings',
    ]);
});

it('requires at least one ticked box in a required checkbox group', function () {
    $form = bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'checkboxes', 'name' => 'extras', 'label' => 'Extras', 'required' => true, 'options' => ['Candles']],
    ]);

    $this->post(route('forms.submit', $form), bakeryAnswers(['extras' => []]))
        ->assertSessionHasErrors(['fields.extras'], errorBag: 'form_bakery-contact');
});

it('stores uploaded files on the private disk with their original name', function () {
    Storage::fake('local');
    $form = bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'file', 'name' => 'sketch', 'label' => 'Cake sketch', 'accept' => ['pdf', 'png'], 'max_kb' => 500],
    ]);

    $this->post(route('forms.submit', $form), bakeryAnswers(['sketch' => UploadedFile::fake()->create('cake-sketch.pdf', 120, 'application/pdf')]))
        ->assertSessionHasNoErrors();

    $file = Submission::query()->sole()->files['sketch'];

    expect($file['name'])->toBe('cake-sketch.pdf')
        ->and($file['path'])->toStartWith('forms/'.$form->id.'/')
        ->and($file['path'])->toEndWith('.pdf');
    Storage::disk('local')->assertExists($file['path']);
});

it('refuses files that are too big or of the wrong type', function () {
    Storage::fake('local');
    $form = bakeryContactForm(extraFields: [
        ['id' => 'f6', 'type' => 'file', 'name' => 'sketch', 'label' => 'Cake sketch', 'accept' => ['pdf'], 'max_kb' => 100],
    ]);

    $this->post(route('forms.submit', $form), bakeryAnswers(['sketch' => UploadedFile::fake()->create('cake.pdf', 300, 'application/pdf')]))
        ->assertSessionHasErrors(['fields.sketch'], errorBag: 'form_bakery-contact');
    $this->post(route('forms.submit', $form), bakeryAnswers(['sketch' => UploadedFile::fake()->create('cake.php', 10, 'application/x-php')]))
        ->assertSessionHasErrors(['fields.sketch'], errorBag: 'form_bakery-contact');

    expect(Submission::query()->count())->toBe(0);
});

it('checks the captcha answer with the service when keys are saved', function () {
    app(FormsSettings::class)->save([
        'retention_days' => 365, 'max_links' => 3, 'min_seconds' => 3, 'blocked_words' => '',
        'captcha_provider' => 'turnstile', 'captcha_site_key' => '0x4AAAAAAAsitekey', 'captcha_secret' => '0x4AAAAAAAsecret',
    ]);
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

    $this->post(route('forms.submit', $this->form), bakeryAnswers())
        ->assertSessionHasErrors(['captcha'], errorBag: 'form_bakery-contact');
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['cf-turnstile-response' => 'wrong']))
        ->assertSessionHasErrors(['captcha'], errorBag: 'form_bakery-contact');
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['cf-turnstile-response' => 'right']))
        ->assertSessionHasNoErrors();

    Http::assertSent(fn ($request): bool => $request['secret'] === '0x4AAAAAAAsecret' && $request['response'] === 'right');
    expect(Submission::query()->count())->toBe(1);
});

it('treats a captcha service that cannot be reached as a failed check', function () {
    app(FormsSettings::class)->save([
        'retention_days' => 365, 'max_links' => 3, 'min_seconds' => 3, 'blocked_words' => '',
        'captcha_provider' => 'hcaptcha', 'captcha_site_key' => 'site-key', 'captcha_secret' => 'secret-key',
    ]);
    Http::fake(fn () => throw new ConnectionException('Timed out'));

    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['h-captcha-response' => 'answer']))
        ->assertSessionHasErrors(['captcha'], errorBag: 'form_bakery-contact');
});

it('gives a fresh CSRF token and time trap token that are never cached', function () {
    $this->getJson(route('forms.token'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure(['token', 'timer', 'timer_field'])
        ->assertJson(['timer_field' => FormTimer::FIELD]);
});

it('returns 404 for an unknown form', function () {
    $this->post('/forms/catering-quote', bakeryAnswers())->assertNotFound();
});

it('ignores a page address from another site', function () {
    $this->post(route('forms.submit', $this->form), bakeryAnswers(extra: ['_page' => 'https://evil.example/phish']))
        ->assertRedirect(url('/').'#form-bakery-contact');

    expect(Submission::query()->sole()->page_url)->toBeNull();
});

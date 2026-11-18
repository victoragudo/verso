<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\PluginVerifier;
use Database\Seeders\PluginDemoSeeder;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Mail\SubmissionMail;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Models\Submission;
use Verso\Plugins\Forms\Support\MailTemplate;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    $this->form = bakeryContactForm();
    $this->submission = Submission::query()->create([
        'form_id' => $this->form->id,
        'data' => ['name' => 'Inês Carvalho', 'email' => 'ines@example.pt', 'topic' => 'Wedding cakes', 'message' => "Three tiers.\nLemon & <b>vanilla</b>.", 'consent' => true],
        'email' => 'ines@example.pt',
        'page_url' => 'http://localhost/contact',
    ])->load('form');
});

it('fills field placeholders and keeps unknown ones', function () {
    $text = app(MailTemplate::class)->render('{name} asked about {topic} on {form_title}. {unknown}', $this->submission);

    expect($text)->toBe('Inês Carvalho asked about Wedding cakes on Contact the bakery. {unknown}');
});

it('lists every answer with its label in all_fields', function () {
    $text = app(MailTemplate::class)->render('{all_fields}', $this->submission);

    expect($text)->toContain("Your name: Inês Carvalho\nEmail: ines@example.pt")
        ->toContain('I agree that you store my message.: Yes');
});

it('keeps subjects on one line', function () {
    expect(app(MailTemplate::class)->subject('About {message}', $this->submission))->toBe('About Three tiers. Lemon & <b>vanilla</b>.');
});

it('escapes answers in the HTML email and links to the inbox', function () {
    $mail = new SubmissionMail($this->submission, 'New message', "From {name}:\n{message}");
    $html = $mail->render();

    expect($html)
        ->toContain('Lemon &amp; &lt;b&gt;vanilla&lt;/b&gt;.')
        ->not->toContain('<b>vanilla</b>')
        ->toContain('From Inês Carvalho:<br>')
        ->toContain(route('admin.forms.submissions.show', $this->submission));
});

it('leaves the inbox link out of the confirmation for the sender', function () {
    $mail = new SubmissionMail($this->submission, 'Thanks', 'We got it.', forTeam: false);

    expect($mail->render())->not->toContain('Open in the inbox');
});

it('puts notifications on the configured queue', function () {
    config(['forms.queue' => 'mail']);

    expect((new SubmissionMail($this->submission, 'New', 'Body'))->queue)->toBe('mail');
});

it('adds the demo contact form to the demo Contact page once', function () {
    Form::query()->delete();
    User::factory()->admin()->create();
    $page = Entry::factory()->published()->create(['type' => 'page', 'title' => 'Contact', 'slug' => 'contact', 'blocks' => [
        ['id' => 'b_intro', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Write to hello@verso.test.', 'children' => []],
    ]]);

    $this->seed(PluginDemoSeeder::class);
    $this->seed(PluginDemoSeeder::class);

    $form = Form::query()->sole();
    $blocks = $page->fresh()->blocks;

    expect($form->slug)->toBe('contact')
        ->and($form->emailField()->name)->toBe('email')
        ->and(array_column($blocks, 'type'))->toBe(['paragraph', 'form'])
        ->and($blocks[1]['attrs']['form'])->toBe('contact');

    $this->get($page->path())->assertSee('Send us a message')->assertSee('Send message');
});

it('passes the plugin verification with a low risk', function () {
    $report = app(PluginVerifier::class)->verify(base_path('plugins/forms'), withSandbox: false);

    expect($report->risk)->toBe(RiskLevel::Low);
});

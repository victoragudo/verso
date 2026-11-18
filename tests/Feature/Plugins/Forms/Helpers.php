<?php

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Support\Facades\Crypt;
use Verso\Plugins\Forms\Actions\SaveForm;
use Verso\Plugins\Forms\Models\Form;

// A contact form like the one a bakery would put on its site.
function bakeryContactForm(array $settings = [], array $extraFields = []): Form
{
    return app(SaveForm::class)->handle([
        'title' => 'Contact the bakery',
        'slug' => 'bakery-contact',
        'fields' => [
            ['id' => 'f1', 'type' => 'text', 'name' => 'name', 'label' => 'Your name', 'required' => true, 'width' => 'half'],
            ['id' => 'f2', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'half'],
            ['id' => 'f3', 'type' => 'select', 'name' => 'topic', 'label' => 'Topic', 'required' => true, 'options' => ['Wedding cakes', 'Catering', 'Something else']],
            ['id' => 'f4', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true, 'min_length' => 10, 'help' => 'Tell us the date and the number of guests.'],
            ['id' => 'f5', 'type' => 'consent', 'name' => 'consent', 'label' => 'I agree that you store my message.', 'required' => true],
            ...$extraFields,
        ],
        'settings' => [
            ...Form::DEFAULT_SETTINGS,
            'notify_emails' => ['orders@padaria-lumiar.pt', 'marta@padaria-lumiar.pt'],
            'notify_subject' => 'New {topic} request from {name}',
            ...$settings,
        ],
    ], Form::query()->where('slug', 'bakery-contact')->first());
}

// A time trap token for a form shown a minute ago.
function shownAMinuteAgo(): string
{
    return Crypt::encryptString((string) now()->subMinute()->getTimestamp());
}

// Answers a real visitor would send to the bakery form.
function bakeryAnswers(array $fields = [], array $extra = []): array
{
    return [
        FormTimer::FIELD => shownAMinuteAgo(),
        '_page' => url('/contact'),
        'fields' => [
            'name' => 'Inês Carvalho',
            'email' => 'Ines.Carvalho@example.pt',
            'topic' => 'Wedding cakes',
            'message' => 'We marry on 14 June and expect 80 guests. Can you bake a three tier cake?',
            'consent' => '1',
            ...$fields,
        ],
        ...$extra,
    ];
}

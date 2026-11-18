<?php

namespace Verso\Plugins\Newsletter\Mail;

use App\Verso\Core\Settings\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Verso\Plugins\Newsletter\Models\Subscriber;

// The double opt-in email with the signed confirm link.
class ConfirmSubscriptionMail extends Mailable
{
    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('newsletter::Please confirm your subscription to :site', ['site' => (string) app(Settings::class)->get('site.name', config('app.name'))]));
    }

    public function content(): Content
    {
        return new Content(view: 'newsletter::mail.confirm', with: [
            'confirmUrl' => $this->subscriber->confirmUrl(),
            'siteName' => (string) app(Settings::class)->get('site.name', config('app.name')),
        ]);
    }
}

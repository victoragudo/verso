<?php

namespace Verso\Plugins\Newsletter\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

// One campaign email, already rendered for its subscriber.
class CampaignMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $unsubscribeUrl,
        public string $fromAddress,
        public string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->fromAddress, $this->fromName), subject: $this->subjectLine);
    }

    // Mail apps show their own unsubscribe button from these headers.
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}

<?php

namespace Verso\Plugins\Forms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;
use Verso\Plugins\Forms\Models\Submission;
use Verso\Plugins\Forms\Support\MailTemplate;

// An email about one submission: the notification for the team or the confirmation for the sender.
class SubmissionMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public string $subjectTemplate,
        public string $bodyTemplate,
        public ?string $replyToAddress = null,
        public bool $forTeam = true,
    ) {
        $queue = (string) config('forms.queue');

        if ($queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(MailTemplate::class)->subject($this->subjectTemplate, $this->submission),
            replyTo: $this->replyToAddress !== null ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        $body = app(MailTemplate::class)->render($this->bodyTemplate, $this->submission);

        return new Content(
            view: 'forms::mail.submission',
            text: 'forms::mail.submission-text',
            with: [
                'body' => $body,
                // Plain text mail is not HTML, so the text view prints it without escaping.
                'textBody' => new HtmlString($body),
                'formTitle' => $this->submission->form->title,
                'adminUrl' => $this->forTeam ? route('admin.forms.submissions.show', $this->submission) : null,
            ],
        );
    }
}

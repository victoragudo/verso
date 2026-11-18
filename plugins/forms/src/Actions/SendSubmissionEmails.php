<?php

namespace Verso\Plugins\Forms\Actions;

use Illuminate\Support\Facades\Mail;
use Verso\Plugins\Forms\Mail\SubmissionMail;
use Verso\Plugins\Forms\Models\Submission;

// Queues the team notification and, when the form asks for it, the confirmation for the sender.
class SendSubmissionEmails
{
    public function handle(Submission $submission): void
    {
        $form = $submission->form;
        $recipients = $form->notifyEmails();

        if ($recipients !== []) {
            Mail::to($recipients)->queue(new SubmissionMail(
                submission: $submission,
                subjectTemplate: (string) $form->setting('notify_subject'),
                bodyTemplate: (string) $form->setting('notify_body'),
                replyToAddress: $submission->email,
            ));
        }

        if ($form->setting('confirmation_enabled') && $submission->email !== null) {
            Mail::to($submission->email)->queue(new SubmissionMail(
                submission: $submission,
                subjectTemplate: (string) $form->setting('confirmation_subject'),
                bodyTemplate: (string) $form->setting('confirmation_body'),
                replyToAddress: $recipients[0] ?? null,
                forTeam: false,
            ));
        }
    }
}
